#!/usr/bin/env python3
"""Deploy this checkout on its existing Nginx/Docker host. No Python packages required."""

import argparse
import base64
from contextlib import contextmanager
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import socket
import subprocess
import sys
import tempfile
import time


SOURCE = Path(__file__).resolve().parent.parent
DOMAIN = "wendkonta.logicielyaar.site"
APP = Path("/www/www/logicielyaar_site-wendkonta")
VHOST = Path("/www/nginx/logicielyaar_site-wendkonta.conf")
STATE_DIR = SOURCE / "deploy/state"
STATE_FILE = STATE_DIR / "deployment.json"
MYSQL_CONTAINER = "mysql-container"
DATABASE = "wendkonta"
DATABASE_USER = "wendkonta_app"
WRITABLE = (
    "storage", "bootstrap/cache", "public/images", "public/logo",
    "public/documents", "public/product/files", "public/stock_count",
    "public/invoices", "public/landlord/images", "public/downloads",
    "public/sale", "public/purchase", "public/transfer",
)


def run(args, *, data=None, capture=False):
    result = subprocess.run(
        [str(arg) for arg in args], input=data, text=True,
        stdout=subprocess.PIPE if capture else None,
        stderr=subprocess.PIPE if capture else None,
    )
    if result.returncode:
        # Never include SQL input or credentials in deployment errors.
        raise RuntimeError(f"Command failed ({result.returncode}): {' '.join(map(str, args))}")
    return result.stdout.strip() if capture else ""


def compose(*args, capture=False):
    return run(["docker", "compose", "-f", APP / "deploy/compose.yaml", *args], capture=capture)


def mysql(sql, database=None):
    args = [
        "docker", "exec", "-i", MYSQL_CONTAINER, "sh", "-c",
        'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; '
        'exec mysql --user=root --batch --skip-column-names "$@"', "sh",
    ]
    if database:
        args.append(f"--database={database}")
    return run(args, data=sql, capture=True)


def import_dump(sql, password):
    # The import has only application-database privileges, even if SQL is unexpected.
    # Read the password from stdin rather than putting it in argv or host environment.
    return run([
        "docker", "exec", "-i", MYSQL_CONTAINER, "sh", "-c",
        'IFS= read -r MYSQL_PWD; export MYSQL_PWD; '
        'exec mysql --protocol=TCP --host=127.0.0.1 --user=wendkonta_app '
        '--batch --skip-column-names --database=wendkonta',
    ], data=password + "\n" + sql, capture=True)


def sanitize_dump(sql):
    """Remove only this known dump's database-level directives; never rewrite data."""
    directives = (
        r"^DROP DATABASE IF EXISTS `salepro`;\r?$",
        r"^CREATE DATABASE IF NOT EXISTS `salepro`[^\r\n]*;\r?$",
        r"^USE `salepro`;\r?$",
    )
    for pattern in directives:
        sql, count = re.subn(pattern, "", sql, flags=re.MULTILINE)
        if count != 1:
            raise ValueError("Unexpected SQL dump preamble; refusing to import.")
    executable = re.sub(r"^--.*$", "", sql, flags=re.MULTILINE)
    if re.search(r"^\s*(?:(?:CREATE|DROP|ALTER)\s+(?:DATABASE|SCHEMA)|USE)\b", executable, re.I | re.M):
        raise ValueError("Additional database-level SQL found; refusing to import.")
    if re.search(r"`salepro`\s*\.", executable, re.I):
        raise ValueError("Qualified source database references found; refusing to import.")
    return sql


def save_state(state, phase):
    STATE_DIR.mkdir(mode=0o700, parents=True, exist_ok=True)
    os.chmod(STATE_DIR, 0o700)
    state["phase"] = phase
    temporary = STATE_FILE.with_suffix(".tmp")
    temporary.write_text(json.dumps(state, indent=2) + "\n")
    os.chmod(temporary, 0o600)
    temporary.replace(STATE_FILE)


@contextmanager
def deployment_lock():
    STATE_DIR.mkdir(mode=0o700, parents=True, exist_ok=True)
    with (STATE_DIR / "deployment.lock").open("w") as lock:
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            raise RuntimeError("Another deployment or rollback is running.")
        yield


def preflight():
    failures = []
    for command in ("docker", "nginx", "certbot", "systemctl", "curl"):
        if not shutil.which(command):
            failures.append(f"Required command unavailable: {command}")
    if os.geteuid() != 0:
        failures.append("Run on the server as root.")
    if shutil.which("docker"):
        for args in (["docker", "version", "--format", "{{.Server.Version}}"],
                     ["docker", "compose", "version"]):
            result = subprocess.run(args, capture_output=True, text=True)
            if result.returncode:
                failures.append(result.stderr.strip() or "Docker Compose is unavailable.")
    try:
        addresses = sorted({x[4][0] for x in socket.getaddrinfo(DOMAIN, 80, type=socket.SOCK_STREAM)})
        print(f"DNS: {DOMAIN} -> {', '.join(addresses)}", flush=True)
    except OSError as error:
        failures.append(f"Cannot resolve {DOMAIN}: {error}")
    for path in (Path("/www/nginx"), Path("/www/www"), Path("/etc/letsencrypt")):
        if not path.is_dir():
            failures.append(f"Expected server directory missing: {path}")
    # Parse and validate the local inputs before any deployment changes.
    json.loads((SOURCE / "composer.lock").read_text())
    sanitize_dump((SOURCE / "db.sql").read_text())
    if failures:
        raise RuntimeError("Preflight failed:\n" + "\n".join(f"- {x}" for x in failures))
    print("Server preflight passed.", flush=True)


def checkout_ignore(directory, names):
    excluded = {".git", ".env", ".env.backup", "node_modules", "__pycache__"}
    if Path(directory) == SOURCE:
        excluded.update({"vendor", "db.sql", "test-results", "playwright-report", ".phpunit.cache"})
    if Path(directory) == SOURCE / "deploy":
        excluded.add("state")
    return excluded.intersection(names)


def restore_missing_vendor_assets():
    source = SOURCE / "public/vendor"
    target = APP / "public/vendor"
    copied = 0
    for root, directories, files in os.walk(source):
        destination = target / Path(root).relative_to(source)
        existed = destination.exists()
        destination.mkdir(mode=0o755, parents=True, exist_ok=True)
        if not existed:
            destination.chmod(0o755)
        for name in files:
            asset = destination / name
            if not asset.exists():
                shutil.copyfile(Path(root) / name, asset)
                asset.chmod(0o644)
                copied += 1
    if copied:
        print(f"Restored {copied} missing public vendor assets; existing files retained.", flush=True)


def test_php():
    with tempfile.TemporaryDirectory(prefix="wendkonta-php-tests-") as folder:
        checkout = Path(folder) / "app"
        shutil.copytree(SOURCE, checkout, ignore=checkout_ignore)
        for path in (checkout / "bootstrap/cache").iterdir():
            if path.is_file() and path.name != ".gitignore":
                path.unlink()
        for directory in ("storage/framework/cache/data", "storage/framework/sessions",
                          "storage/framework/views", "storage/logs"):
            (checkout / directory).mkdir(parents=True, exist_ok=True)
        key = "base64:" + base64.b64encode(secrets.token_bytes(32)).decode()
        env = (checkout / ".env.example").read_text().replace("APP_ENV=local", "APP_ENV=testing")
        (checkout / ".env").write_text(env.replace("APP_KEY=\n", f"APP_KEY={key}\n"))
        run([
            "docker", "run", "--rm", "--entrypoint", "sh",
            "--env", "APP_ENV=testing", "--env", "COMPOSER_ALLOW_SUPERUSER=1",
            "--mount", f"type=bind,source={checkout},target=/var/www/html",
            "wendkonta-php:8.3", "-ec",
            "composer validate --no-check-publish; "
            "composer install --prefer-dist --no-interaction; "
            "vendor/bin/phpunit tests/Feature/ProductionDatabaseResetTest.php; "
            "vendor/bin/phpunit tests/Feature/SaleProductSelectionTest.php",
        ])


def stage_application(password):

    shutil.copytree(SOURCE, APP, ignore=checkout_ignore)
    for root, directories, files in os.walk(APP):
        os.chmod(root, 0o755)
        for name in files:
            path = Path(root) / name
            if not path.is_symlink():
                os.chmod(path, 0o644)
    # Cached manifests came from another installation and include absent packages.
    for path in (APP / "bootstrap/cache").iterdir():
        if path.is_file() and path.name != ".gitignore":
            path.unlink()
    for directory in ("storage/app/public", "storage/framework/cache/data",
                      "storage/framework/sessions", "storage/framework/views", "storage/logs"):
        (APP / directory).mkdir(parents=True, exist_ok=True)
    public_storage = APP / "public/storage"
    if public_storage.is_dir() and not public_storage.is_symlink():
        if any(path.name != ".gitignore" for path in public_storage.iterdir()):
            raise RuntimeError("public/storage contains files; refusing to replace it.")
        shutil.rmtree(public_storage)
    # A relative link works both on host Nginx and inside the PHP container.
    if not public_storage.is_symlink():
        public_storage.symlink_to("../storage/app/public", target_is_directory=True)
    env = (SOURCE / "deploy/.env.production.example").read_text()
    key = "base64:" + base64.b64encode(secrets.token_bytes(32)).decode()
    env = env.replace("APP_KEY=GENERATED_BY_DEPLOYMENT", f"APP_KEY={key}")
    env = env.replace("DB_PASSWORD=GENERATED_BY_DEPLOYMENT", f"DB_PASSWORD={password}")
    (APP / ".env").write_text(env)
    os.chown(APP / ".env", 0, 33)
    os.chmod(APP / ".env", 0o640)
    for directory in WRITABLE:
        target = APP / directory
        target.mkdir(parents=True, exist_ok=True)
        for root, directories, files in os.walk(target):
            os.chown(root, 33, 33)
            os.chmod(root, 0o755)
            for name in files:
                path = Path(root) / name
                if not path.is_symlink():
                    os.chown(path, 33, 33)
                    os.chmod(path, 0o644)


def install_nginx(template):
    previous = VHOST.read_bytes() if VHOST.exists() else None
    VHOST.write_bytes((SOURCE / "deploy" / template).read_bytes())
    try:
        run(["nginx", "-t"])
        run(["systemctl", "reload", "nginx"])
    except Exception:
        if previous is None:
            VHOST.unlink(missing_ok=True)
        else:
            VHOST.write_bytes(previous)
        run(["nginx", "-t"])
        run(["systemctl", "reload", "nginx"])
        raise


def rollback(state):
    if "original_vhost" not in state:
        raise RuntimeError("No deployment backup exists; refusing rollback.")
    previous = state["original_vhost"]
    if previous is None:
        VHOST.unlink(missing_ok=True)
    else:
        VHOST.write_bytes(base64.b64decode(previous))
    run(["nginx", "-t"])
    run(["systemctl", "reload", "nginx"])
    if APP.exists():
        compose("stop", "php")
    print("Wendkonta vhost restored and PHP container stopped. Database and files retained.")


def wait_for_url(url, *, label, resolve=None, expected_body=None, timeout=30):
    """Wait for the new Nginx workers, distinguishing local routing from public DNS."""
    deadline = time.monotonic() + timeout
    last_error = "no response"
    print(f"Waiting for {label}: {url}", flush=True)
    while True:
        remaining = deadline - time.monotonic()
        if remaining <= 0:
            raise RuntimeError(f"{label} failed after {timeout}s: {last_error}. URL: {url}")
        args = ["curl", "--silent", "--show-error", "--noproxy", "*",
                "--connect-timeout", "2", "--max-time", str(min(3, remaining)),
                "--write-out", "\n%{http_code}"]
        if resolve:
            args.extend(["--resolve", resolve])
        result = subprocess.run([*args, url], capture_output=True, text=True)
        if result.returncode:
            last_error = f"curl error {result.returncode}: {result.stderr.strip()[:300]}"
        else:
            body, _, status = result.stdout.rpartition("\n")
            if status == "200" and (expected_body is None or body == expected_body):
                print(f"{label} passed.", flush=True)
                return
            last_error = f"HTTP {status or 'unknown'}"
            if status == "200":
                last_error += "; response did not contain the expected challenge token"
        remaining = deadline - time.monotonic()
        if remaining > 0:
            time.sleep(min(1, remaining))


def verify():
    checks = {
        "/login": "200", "/dashboard": "302",
        "/vendor/bootstrap/css/bootstrap.min.css": "200", "/css/auth.css": "200",
        "/.env": "404", "/db.sql": "404", "/migrate": "404",
        "/clear": "404", "/install/step-1": "404", "/index.php/migrate": "404",
        "/images/upload.php": "404",
    }
    for path, expected in checks.items():
        status = run([
            "curl", "--silent", "--show-error", "--noproxy", "*", "--max-time", "20",
            "--resolve", f"{DOMAIN}:443:127.0.0.1", "--output", "/dev/null",
            "--write-out", "%{http_code}", f"https://{DOMAIN}{path}",
        ], capture=True)
        if status != expected:
            raise RuntimeError(f"HTTPS check failed for {path}: expected {expected}, got {status}")
    headers = run(["curl", "--silent", "--show-error", "--noproxy", "*", "--max-time", "20",
                   "--resolve", f"{DOMAIN}:443:127.0.0.1", "--dump-header", "-",
                   "--output", "/dev/null", f"https://{DOMAIN}/login"], capture=True)
    cookies = [line.lower() for line in headers.splitlines() if line.lower().startswith("set-cookie: wendkonta_session=")]
    if not cookies or not all("; secure" in line and "; httponly" in line for line in cookies):
        raise RuntimeError("Login session cookie is missing Secure or HttpOnly.")
    redirect = run(["curl", "--silent", "--show-error", "--noproxy", "*", "--max-time", "20",
                    "--resolve", f"{DOMAIN}:80:127.0.0.1", "--dump-header", "-",
                    "--output", "/dev/null", f"http://{DOMAIN}/login"], capture=True)
    if f"location: https://{DOMAIN}/login" not in redirect.lower():
        raise RuntimeError("HTTP does not redirect to the expected HTTPS URL.")
    print("HTTPS, assets, protected routes, session cookies, and HTTP redirect passed.")


def deploy(resume, email):
    dump = (SOURCE / "db.sql").read_text()
    sql = sanitize_dump(dump)
    digest = hashlib.sha256((SOURCE / "db.sql").read_bytes()).hexdigest()
    state = json.loads(STATE_FILE.read_text()) if STATE_FILE.exists() else {}
    if state and not resume:
        raise RuntimeError("Deployment state already exists. Use --resume to retain its database and key.")
    if resume and not state:
        raise RuntimeError("No deployment state to resume.")
    if state and state["dump_sha256"] != digest:
        raise RuntimeError("Dump changed since deployment started; refusing to resume.")
    for path in VHOST.parent.glob("*.conf"):
        if path != VHOST and re.search(r"\bserver_name\s+[^;]*\b" + re.escape(DOMAIN) + r"(?=[\s;])", path.read_text()):
            raise RuntimeError(f"Domain already configured in {path}; refusing conflicting vhost.")
    if VHOST.exists() and DOMAIN not in VHOST.read_text():
        raise RuntimeError("Target vhost belongs to another site; refusing to replace it.")
    exists = mysql(f"SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='{DATABASE}';") == "1"
    imported = state.get("phase") in ("imported", "ready", "active", "complete")
    if exists and not imported:
        raise RuntimeError("wendkonta database already exists without a completed import. No data changed; inspect it manually.")
    if imported and (not exists or not (APP / ".env").is_file()):
        raise RuntimeError("Saved deployment is missing its database or .env; refusing to regenerate credentials.")
    if not mysql("SHOW COLLATION LIKE 'utf8mb4_0900_ai_ci';"):
        raise RuntimeError("MySQL does not support the dump's utf8mb4_0900_ai_ci collation.")
    if not state:
        if APP.exists():
            raise RuntimeError(f"Deployment directory already exists: {APP}. No files overwritten.")
        if mysql(f"SELECT COUNT(*) FROM mysql.user WHERE User='{DATABASE_USER}';") != "0":
            raise RuntimeError("Application database user already exists; refusing to replace its credentials.")
        with socket.socket() as probe:
            probe.bind(("127.0.0.1", 19083))
        result = subprocess.run(["docker", "container", "inspect", "wendkonta-php"], capture_output=True)
        if result.returncode == 0:
            raise RuntimeError("Container wendkonta-php already exists; refusing to replace it.")
        run(["nginx", "-t"])
        state = {"dump_sha256": digest, "original_vhost":
                 base64.b64encode(VHOST.read_bytes()).decode() if VHOST.exists() else None}
        save_state(state, "building")
    if not APP.exists():
        run(["docker", "compose", "-f", SOURCE / "deploy/compose.yaml", "build", "--pull", "php"])
        test_php()
        stage_application(secrets.token_hex(32))
        save_state(state, "staged")
    if not imported:
        if state.get("phase") != "staged":
            raise RuntimeError("Application staging was interrupted; inspect files before resuming.")
        password = next(line.split("=", 1)[1] for line in (APP / ".env").read_text().splitlines() if line.startswith("DB_PASSWORD="))
        if not re.fullmatch(r"[a-f0-9]{64}", password):
            raise RuntimeError("Unexpected database password format in staged .env.")
        mysql(f"CREATE DATABASE `{DATABASE}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;\n"
              f"CREATE USER '{DATABASE_USER}'@'%' IDENTIFIED BY '{password}';\n"
              f"GRANT ALL PRIVILEGES ON `{DATABASE}`.* TO '{DATABASE_USER}'@'%';")
        save_state(state, "database_created")
        import_dump(sql, password)
        expected = len(re.findall(r"^CREATE TABLE", dump, re.M))
        actual = int(mysql(f"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='{DATABASE}';"))
        if actual != expected:
            raise RuntimeError(f"Imported table count mismatch: expected {expected}, got {actual}.")
        save_state(state, "imported")
        print(f"Imported {actual} tables into {DATABASE}; other databases unchanged.", flush=True)
    restore_missing_vendor_assets()
    compose("run", "--rm", "--no-deps", "-e", "COMPOSER_ALLOW_SUPERUSER=1", "php",
            "composer", "validate", "--no-check-publish")
    compose("run", "--rm", "--no-deps", "-e", "COMPOSER_ALLOW_SUPERUSER=1", "php",
            "composer", "install", "--no-dev", "--prefer-dist", "--optimize-autoloader", "--no-interaction")
    compose("run", "--rm", "--no-deps", "php", "composer", "check-platform-reqs", "--no-dev")
    for command in ("optimize:clear", "view:cache", "migrate:status", "schedule:list"):
        output = compose("run", "--rm", "--no-deps", "--user", "33:33", "php", "php", "artisan", command, capture=True)
        if command == "migrate:status" and "Pending" in output:
            raise RuntimeError("Unexpected pending migrations; no migrations were run.")
        if command == "schedule:list" and "reset:db" in output:
            raise RuntimeError("Destructive demo reset is still scheduled.")
        print(output, flush=True)
    save_state(state, "ready")
    try:
        activate(state, email)
    except (Exception, KeyboardInterrupt):
        try:
            rollback(state)
        except Exception as error:
            print(f"Automatic rollback failed: {error}", file=sys.stderr)
        raise


def activate(state, email):
    compose("up", "-d", "--wait", "--wait-timeout", "90", "php")
    Path("/www/logs").mkdir(exist_ok=True)
    challenge_dir = Path("/www/acme/.well-known/acme-challenge")
    challenge_dir.mkdir(parents=True, exist_ok=True)
    token = secrets.token_hex(16)
    challenge = challenge_dir / token
    challenge.write_text(token)
    challenge.chmod(0o644)
    try:
        install_nginx("nginx-http.conf")
        url = f"http://{DOMAIN}/.well-known/acme-challenge/{token}"
        wait_for_url(url, label="Local ACME challenge", resolve=f"{DOMAIN}:80:127.0.0.1", expected_body=token)
        wait_for_url(url, label="Public ACME challenge", expected_body=token)
    finally:
        challenge.unlink(missing_ok=True)
    account = ["--email", email] if email else ["--register-unsafely-without-email"]
    run(["certbot", "certonly", "--non-interactive", "--agree-tos", "--webroot", "-w", "/www/acme",
         "--cert-name", DOMAIN, "-d", DOMAIN, "--keep-until-expiring", *account])
    install_nginx("nginx.conf")
    save_state(state, "active")
    wait_for_url(f"https://{DOMAIN}/login", label="Local HTTPS application", resolve=f"{DOMAIN}:443:127.0.0.1")
    verify()
    hook = Path("/etc/letsencrypt/renewal-hooks/deploy/wendkonta-nginx")
    hook.parent.mkdir(parents=True, exist_ok=True)
    hook.write_text('#!/bin/sh\nset -eu\ncase " ${RENEWED_DOMAINS:-} " in\n'
                    f'    *" {DOMAIN} "*) /usr/sbin/nginx -t && /bin/systemctl reload nginx ;;\nesac\n')
    hook.chmod(0o755)
    run(["systemctl", "enable", "--now", "certbot.timer"])
    run(["certbot", "renew", "--cert-name", DOMAIN, "--dry-run", "--no-random-sleep-on-renew"])
    save_state(state, "complete")
    print(f"Deployment verified: https://{DOMAIN}")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument("--check", action="store_true", help="Read-only server and input checks")
    mode.add_argument("--resume", action="store_true", help="Continue this deployment without reimporting completed data")
    mode.add_argument("--rollback", action="store_true", help="Restore the original vhost and stop PHP; retain data")
    mode.add_argument("--verify", action="store_true", help="Verify the deployed HTTPS site")
    parser.add_argument("--email", help="Optional Let's Encrypt account email")
    args = parser.parse_args()
    try:
        if args.rollback:
            with deployment_lock():
                rollback(json.loads(STATE_FILE.read_text()))
        elif args.verify:
            verify()
        else:
            preflight()
            if not args.check:
                with deployment_lock():
                    deploy(args.resume, args.email)
    except (RuntimeError, ValueError, OSError) as error:
        print(str(error), file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
