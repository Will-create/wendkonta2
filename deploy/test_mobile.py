#!/usr/bin/env python3
"""Run SalePro's PHP and real-browser regressions in an isolated Docker stack."""

import argparse
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import subprocess
import sys
import tempfile
import time

ROOT = Path(__file__).resolve().parent.parent
RESULTS = ROOT / "test-results/mobile"


def source_digest():
    digest = hashlib.sha256()
    for directory in ["app", "config", "routes", "resources/views", "lang", "Modules", "public/css", "public/js", "public/icons", "public/vendor", "database/migrations", "tests/e2e", "tests/Feature", "tests/js", "tests/deploy"]:
        for path in sorted((ROOT / directory).rglob("*")):
            if path.is_file() and path.suffix not in [".pyc"]:
                digest.update(str(path.relative_to(ROOT)).encode())
                digest.update(path.read_bytes())
    for name in ["public/index.php", "public/manifest.json", "public/service-worker.js", "public/offline.html", "bootstrap/app.php", "modules_statuses.json", "composer.json", "composer.lock", "package.json", "playwright.config.cjs", "deploy/Dockerfile", "deploy/php.ini", "deploy/deploy.py", "deploy/test_mobile.py", "deploy/release_mobile.py", "deploy/compose.yaml", "deploy/nginx.conf", "db.sql"]:
        digest.update((ROOT / name).read_bytes())
    return digest.hexdigest()


def schema_and_permissions(dump):
    tables = re.findall(r"^CREATE TABLE IF NOT EXISTS `[^`]+` \(.*?^\) ENGINE=[^\n]+;", dump, re.M | re.S)
    expected = len(re.findall(r"^CREATE TABLE", dump, re.M))
    if not tables or len(tables) != expected:
        raise ValueError("Could not extract the complete schema. No database import was attempted.")
    permissions = []
    for block in re.findall(r"INSERT INTO `permissions`.*?;", dump, re.S):
        permissions.extend(re.findall(r"\(\d+,\s*'([^']+)'", block))
    if not permissions:
        raise ValueError("No permission definitions were found for the synthetic owner.")
    return "SET FOREIGN_KEY_CHECKS=0;\n" + "\n".join(tables) + "\nSET FOREIGN_KEY_CHECKS=1;\n", sorted(set(permissions))


def previous_failures(results):
    state_file = results / 'artifacts/.last-run.json'
    if not state_file.is_file():
        raise ValueError('No previous browser failure list was found. No tests were started.')
    state = json.loads(state_file.read_text())
    if not isinstance(state, dict):
        raise ValueError('The previous browser failure list is invalid. No tests were started.')
    ids = state.get('failedTests')
    if state.get('status') != 'failed' or not isinstance(ids, list) or not ids:
        raise ValueError('There are no recorded failed browser tests to rerun. No tests were started.')
    if any(not isinstance(test_id, str) or not test_id for test_id in ids) or len(set(ids)) != len(ids):
        raise ValueError('The previous browser failure list is invalid. No tests were started.')
    verification_file = results / 'verification.json'
    verification = json.loads(verification_file.read_text()) if verification_file.is_file() else {}
    cases = []
    browser_file = results / 'browser.json'
    if browser_file.is_file():
        def collect(suites):
            for suite in suites:
                for spec in suite.get('specs', []):
                    if spec.get('id') in ids:
                        for test in spec.get('tests', []):
                            cases.append({'id': spec['id'], 'project': test.get('projectName'), 'title': spec.get('title')})
                collect(suite.get('suites', []))
        collect(json.loads(browser_file.read_text()).get('suites', []))
    return {'state': state, 'cases': cases or verification.get('failed_cases', []),
            'baseline': {key: verification.get(key) for key in ['source_sha256', 'python', 'php', 'started_at', 'finished_at']}}


def prepare_results(results, failed_state=None):
    if (results / 'verification.json').exists():
        history = results.parent / 'mobile-history' / (time.strftime('%Y%m%dT%H%M%SZ', time.gmtime()) + '-' + str(os.getpid()) + '-' + secrets.token_hex(3))
        history.parent.mkdir(parents=True, exist_ok=True)
        results.rename(history)
        print('Previous test artifacts retained:', history, flush=True)
    results.mkdir(parents=True, exist_ok=True)
    if failed_state is not None:
        target = results / 'artifacts/.last-run.json'
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(json.dumps(failed_state, indent=2) + '\n')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--keep", action="store_true", help="Keep the isolated failed stack for diagnosis")
    parser.add_argument('--project', choices=['android-chromium', 'ios-webkit', 'desktop-chromium'], help='Run one browser project for diagnosis; cannot authorize release')
    parser.add_argument('--grep', help='Run matching browser tests for diagnosis; cannot authorize release')
    parser.add_argument('--last-failed', action='store_true', help='Rerun only previously failed browser cases; skip passing Python/PHP/JS suites; cannot authorize release')
    args = parser.parse_args()
    if args.last_failed and (args.project or args.grep):
        parser.error('--last-failed cannot be combined with --project or --grep; it selects the exact previous failures across all projects.')
    try:
        failures = previous_failures(RESULTS) if args.last_failed else None
    except (ValueError, OSError) as error:
        parser.exit(2, str(error) + '\n')
    prepare_results(RESULTS, failures['state'] if failures else None)
    report = {"status": "blocked", "source_sha256": source_digest(), "physical_devices": "not tested", 'full_suite': not (args.project or args.grep or args.last_failed),
              "production_data_used": False, "started_at": time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())}
    if failures:
        report.update({'scope': 'last-failed', 'failed_test_ids': failures['state']['failedTests'], 'failed_cases': failures['cases'],
                       'baseline': failures['baseline'], 'python': 'not_run', 'php': 'not_run', 'javascript': 'not_run'})
        print('Rerunning only', len(failures['state']['failedTests']), 'previously failed browser cases. Python/PHP/JS suites are skipped.', flush=True)
        for case in failures['cases']:
            print(' -', str(case.get('project') or 'unknown') + ':', case.get('title'), flush=True)
    log = (RESULTS / "runner.log").open("w")
    project = "salepro-mobile-" + str(os.getpid())
    folder = None
    compose_file = None

    def run(command, data=None, capture=False):
        print("Running:", " ".join(map(str, command)), flush=True)
        if not capture and data is None:
            process = subprocess.Popen(list(map(str, command)), stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)
            for line in process.stdout:
                log.write(line); log.flush(); print(line, end="", flush=True)
            if process.wait():
                raise RuntimeError(f"Command failed ({process.returncode}); see {RESULTS / 'runner.log'}")
            return ""
        result = subprocess.run(list(map(str, command)), input=data, text=True, capture_output=True)
        log.write(result.stdout + result.stderr); log.flush()
        if not capture:
            print(result.stdout, end="", flush=True)
            print(result.stderr, end="", file=sys.stderr, flush=True)
        if result.returncode:
            raise RuntimeError(f"Command failed ({result.returncode}); see {RESULTS / 'runner.log'}")
        return result.stdout.strip()

    def compose(*command, **kwargs):
        return run(["docker", "compose", "-p", project, "-f", compose_file, *command], **kwargs)

    try:
        if not args.last_failed:
            run([sys.executable, '-m', 'unittest', 'discover', '-s', ROOT / 'tests/deploy', '-v'])
            report['python'] = 'passed'
        check = subprocess.run(["docker", "version", "--format", "{{.Server.Version}}"], capture_output=True, text=True)
        if check.returncode:
            raise PermissionError(check.stderr.strip())
        schema, permissions = schema_and_permissions((ROOT / "db.sql").read_text())
        folder = Path(tempfile.mkdtemp(prefix="salepro-mobile-stack-"))
        report['stack_dir'] = str(folder)
        report['compose_project'] = project
        app = folder / "app"
        spec = importlib.util.spec_from_file_location("deployment", ROOT / "deploy/deploy.py")
        deployment = importlib.util.module_from_spec(spec); spec.loader.exec_module(deployment)
        deployment.APP = app
        password = secrets.token_hex(24)
        deployment.stage_application(password)
        database = "salepro_mobile_e2e_" + str(os.getpid())
        env = (app / ".env").read_text().replace("APP_ENV=production", "APP_ENV=testing")
        env = env.replace("DB_HOST=host.docker.internal", "DB_HOST=db").replace("DB_DATABASE=wendkonta", "DB_DATABASE=" + database)
        env = env.replace("DB_USERNAME=wendkonta_app", "DB_USERNAME=salepro_e2e").replace("SESSION_SECURE_COOKIE=true", "SESSION_SECURE_COOKIE=false")
        env += "\nSALEPRO_E2E=true\n"
        (app / ".env").write_text(env)
        (app / "tests/e2e/permissions.json").write_text(json.dumps(permissions))
        dictionary = {}
        for path in (ROOT / 'resources/views').rglob('*.php'):
            for key in re.findall(r'''__\(\s*['"]db\.([^'"]+)['"]\s*\)''', path.read_text()):
                dictionary.setdefault(key.lower(), {'key': key, 'value': key.replace('_', ' ').capitalize()})
        (app / 'tests/e2e/dictionary.json').write_text(json.dumps(list(dictionary.values())))
        nginx = folder / "nginx.conf"
        nginx.write_text('''events { worker_connections 256; }
http {
 include /etc/nginx/mime.types;
 server {
  listen 80; root /var/www/html/public; index index.php; client_max_body_size 24M;
  add_header X-SalePro-E2E isolated always;
  location = /service-worker.js { add_header Cache-Control "no-cache"; try_files $uri =404; }
  location ~ /\\. { return 404; }
  location ~ \\.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; fastcgi_pass php:9000; }
  location / { try_files $uri $uri/ /index.php?$query_string; }
 }
}
''')
        compose_file = folder / "compose.json"
        compose_file.write_text(json.dumps({"services": {
            "db": {"image": "mysql:8.0", "environment": {"MYSQL_ROOT_PASSWORD": secrets.token_hex(24), "MYSQL_DATABASE": database, "MYSQL_USER": "salepro_e2e", "MYSQL_PASSWORD": password},
                   "tmpfs": ["/var/lib/mysql"], "healthcheck": {"test": ["CMD-SHELL", "MYSQL_PWD=$$MYSQL_ROOT_PASSWORD mysqladmin ping -h127.0.0.1 -uroot --silent"], "interval": "3s", "timeout": "3s", "retries": 40}},
            "php": {"image": "salepro-mobile-php:8.3", "volumes": [str(app) + ":/var/www/html"], "depends_on": {"db": {"condition": "service_healthy"}}},
            "nginx": {"image": "nginx:1.28-alpine", "ports": ["127.0.0.1::80"], "volumes": [str(app) + ":/var/www/html:ro", str(nginx) + ":/etc/nginx/nginx.conf:ro"], "depends_on": ["php"]}
        }}))
        compose_file.chmod(0o600)
        run(["docker", "build", "-t", "salepro-mobile-php:8.3", "-f", ROOT / "deploy/Dockerfile", ROOT])
        report['php_image_id'] = run(['docker', 'image', 'inspect', '--format', '{{.Id}}', 'salepro-mobile-php:8.3'], capture=True)
        compose("up", "-d", "--wait", "--wait-timeout", "150", "db")
        compose("run", "--rm", "--no-deps", "-e", "COMPOSER_ALLOW_SUPERUSER=1", "php", "composer", "install", "--prefer-dist", "--no-interaction")
        if not args.last_failed:
            for file in ["tests/Feature/ProductionDatabaseResetTest.php", "tests/Feature/SaleProductSelectionTest.php", "tests/Feature/OperationalSubmissionTest.php", "tests/Feature/OperationalInputTest.php", "tests/Feature/MobileDashboardTest.php", "tests/Feature/MobileTranslationTest.php", "tests/Feature/ProductFlagCompatibilityTest.php", "tests/Feature/ComboStockTest.php", "tests/Feature/CustomerAccountTest.php", "tests/Feature/PurchaseDefaultsTest.php", "tests/Feature/OperationalViewsTest.php", "tests/Feature/ProductInventoryTest.php", "tests/Feature/QuickProductTest.php"]:
                compose("run", "--rm", "--no-deps", "php", "vendor/bin/phpunit", file)
            report["php"] = "passed"
        compose("exec", "-T", "db", "sh", "-c", 'export MYSQL_PWD="$MYSQL_PASSWORD"; exec mysql --user="$MYSQL_USER" "$MYSQL_DATABASE"', data=schema, capture=True)
        compose("run", "--rm", "--no-deps", "--user", "0:0", "php", "chown", "-R", "33:33", "storage", "bootstrap/cache", "public/logo")
        for migration in ["2026_11_14_200000_create_operation_receipts_table.php", "2026_11_15_000100_create_purchase_defaults_table.php", "2026_11_15_000200_add_customer_deposit_receipts.php"]:
            compose("run", "--rm", "--no-deps", "--user", "33:33", "php", "php", "artisan", "migrate", "--force", "--path=database/migrations/" + migration)
        compose("run", "--rm", "--no-deps", "--user", "33:33", "php", "php", "tests/e2e/seed.php")
        compose("up", "-d", "php", "nginx")
        port = compose("port", "nginx", "80", capture=True).rsplit(":", 1)[1]
        base = "http://127.0.0.1:" + port
        env = re.sub(r"^APP_URL=.*$", "APP_URL=" + base, env, flags=re.M)
        (app / ".env").write_text(env)
        report["base_url"] = base
        deployment.wait_for_url(base + "/__e2e/status", label="Isolated application")
        browser_args = []
        if args.project: browser_args += ['--project', args.project]
        if args.grep: browser_args += ['--grep', args.grep]
        if args.last_failed: browser_args += ['--last-failed']
        javascript_checks = '' if args.last_failed else 'node tests/js/mobile-shell.dom.test.cjs; node tests/js/mobile-ajax.dom.test.cjs; node tests/js/mobile-summary.dom.test.cjs; node tests/js/pwa-worker.test.cjs; node tests/js/mobile-charts.dom.test.cjs; node tests/js/sale-product-select.dom.test.cjs; node tests/js/purchase-product-picker.dom.test.cjs; node tests/js/mobile-pagination.dom.test.cjs; node tests/js/followup.dom.test.cjs; '
        run(["docker", "run", "--rm", "--network", "host", "--shm-size=1g",
             "--env", "BASE_URL=" + base, "--env", "NODE_PATH=/runner/node_modules",
             "--volume", str(app) + ":/work", "--volume", str(RESULTS) + ":/results",
             "--volume", "salepro-mobile-npm-cache:/root/.npm", "mcr.microsoft.com/playwright:v1.63.0-noble", "sh", "-ec",
             "mkdir /runner; cd /runner; npm install --no-save --package-lock=false @playwright/test@1.63.0 jsdom@30.1.0; "
             'cd /work; ' + javascript_checks +
             '/runner/node_modules/.bin/playwright test --config=playwright.config.cjs "$@"', 'sh', *browser_args])
        report["browser"] = "passed"
        report["status"] = "passed" if report['full_suite'] else 'partial'
    except Exception as error:
        report["error"] = str(error)
        report["status"] = "blocked" if isinstance(error, PermissionError) else "failed"
        print(report["error"], file=sys.stderr)
    finally:
        report["finished_at"] = time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())
        (RESULTS / "verification.json").write_text(json.dumps(report, indent=2) + "\n")
        if compose_file:
            try:
                compose("logs", "--no-color", "--tail", "100", capture=True)
                if not args.keep or report["status"] in ['passed', 'partial']: compose("down", "-v", "--remove-orphans", capture=True)
            except Exception:
                pass
        log.close()
        if folder and (not args.keep or report["status"] in ['passed', 'partial']):
            shutil.rmtree(folder, ignore_errors=True)
        elif folder:
            print("Isolated stack retained:", folder, "project:", project)
        print("Verification report:", RESULTS / "verification.json")
    return 0 if report["status"] in ['passed', 'partial'] else 1


if __name__ == '__main__':
    sys.exit(main())
