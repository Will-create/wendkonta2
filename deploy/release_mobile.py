#!/usr/bin/env python3
"""Release the mobile/PWA update without importing or resetting production data."""

import argparse
import importlib.util
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import time

ROOT = Path(__file__).resolve().parent.parent
APP = Path('/www/www/logicielyaar_site-wendkonta')
VHOST = Path('/www/nginx/logicielyaar_site-wendkonta.conf')
MIGRATIONS = (
    'database/migrations/2026_11_14_200000_create_operation_receipts_table.php',
    'database/migrations/2026_11_15_000100_create_purchase_defaults_table.php',
    'database/migrations/2026_11_15_000200_add_customer_deposit_receipts.php',
)


def module(name, filename):
    spec = importlib.util.spec_from_file_location(name, ROOT / filename)
    result = importlib.util.module_from_spec(spec); spec.loader.exec_module(result)
    return result


def copy_code(source, destination):
    """Keep production environment, dependencies and all runtime/upload directories."""
    retained = {'storage', 'bootstrap/cache', 'public/storage', 'public/images', 'public/logo', 'public/documents',
                'public/product/files', 'public/stock_count', 'public/invoices', 'public/landlord/images',
                'public/downloads', 'public/sale', 'public/purchase', 'public/transfer', 'vendor', '.git', 'deploy/state',
                'test-results', 'node_modules', '__pycache__'}
    def ignored(directory, names):
        relative = Path(directory).relative_to(source)
        return [name for name in names if name in {'.env', '.env.backup', 'db.sql', '__pycache__'}
                or str(relative / name) in retained]
    shutil.copytree(source, destination, dirs_exist_ok=True, ignore=ignored, symlinks=True)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument('--test-and-release', action='store_true', help='Run isolated verification before releasing')
    mode.add_argument('--skip-tests', action='store_true', help='Explicitly deploy current source without requiring regression results; retain backups and deployment health checks')
    args = parser.parse_args()
    tests = module('mobile_verification', 'deploy/test_mobile.py')
    deploy = module('deployment', 'deploy/deploy.py')
    backup = None
    stopped = False
    vhost = None
    backup_ready = False
    maintenance = False
    previous_image = None
    image_changed = False
    try:
        if args.test_and_release:
            subprocess.run([sys.executable, str(ROOT / 'deploy/test_mobile.py')], check=True)
        if args.skip_tests:
            report = {'status': 'not_run', 'regression_verification': 'skipped_by_request', 'source_sha256': tests.source_digest()}
            print('Regression test gate skipped by explicit request. Building the current source for deployment.', flush=True)
        else:
            verification = tests.RESULTS / 'verification.json'
            report = json.loads(verification.read_text())
            if report.get('status') != 'passed' or report.get('full_suite') is not True or report.get('python') != 'passed' or report.get('php') != 'passed' or report.get('browser') != 'passed':
                raise RuntimeError('Release blocked: isolated PHP and full-browser verification must pass.')
            if report.get('source_sha256') != tests.source_digest():
                raise RuntimeError('Release blocked: source changed after verification. Run deploy/test_mobile.py again.')
        deploy.preflight()
        if args.skip_tests:
            candidate = 'wendkonta-mobile-candidate:' + report['source_sha256'][:16]
            deploy.run(['docker', 'build', '-t', candidate, '-f', ROOT / 'deploy/Dockerfile', ROOT])
            verified_image = deploy.run(['docker', 'image', 'inspect', '--format', '{{.Id}}', candidate], capture=True)
            report['php_image_id'] = verified_image
        else:
            verified_image = deploy.run(['docker', 'image', 'inspect', '--format', '{{.Id}}', 'salepro-mobile-php:8.3'], capture=True)
            if verified_image != report.get('php_image_id'):
                raise RuntimeError('Release blocked: the verified PHP image is missing or changed. Run verification again.')
        previous_image = deploy.run(['docker', 'image', 'inspect', '--format', '{{.Id}}', 'wendkonta-php:8.3'], capture=True)
        if not (APP / '.env').exists():
            raise RuntimeError('Production installation is missing. This command only updates an existing deployment.')
        environment = (APP / '.env').read_text()
        database = re.search(r'^DB_DATABASE=(.+)$', environment, re.M)
        if not database or not re.fullmatch(r'[A-Za-z0-9_]+', database.group(1)):
            raise RuntimeError('Cannot identify the production database safely.')
        with deploy.deployment_lock():
            stamp = time.strftime('%Y%m%dT%H%M%SZ', time.gmtime())
            backup = ROOT / 'deploy/state/releases' / stamp
            backup.mkdir(parents=True, mode=0o700)
            (backup / 'application').mkdir()
            vhost = VHOST.read_bytes()
            (backup / 'nginx.conf').write_bytes(vhost)
            deploy.install_nginx('nginx.conf')
            deploy.compose('exec', '-T', '--user', '33:33', 'php', 'php', 'artisan', 'down', '--retry=60')
            maintenance = True
            # The image's SIGQUIT drains in-flight requests before the backup/update.
            deploy.compose('stop', '-t', '150', 'php')
            stopped = True
            shutil.copytree(APP, backup / 'application', dirs_exist_ok=True, symlinks=True)
            dump = backup / 'database.sql'
            with dump.open('w') as output:
                dump.chmod(0o600)
                result = subprocess.run(['docker', 'exec', deploy.MYSQL_CONTAINER, 'sh', '-c',
                    'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mysqldump --user=root --single-transaction --no-tablespaces --set-gtid-purged=OFF "$1"',
                    'sh', database.group(1)], stdout=output, stderr=subprocess.PIPE, text=True)
            if result.returncode:
                raise RuntimeError('Database backup failed; update stopped before code or schema changes.')
            backup_ready = True
            (backup / 'verification.json').write_text(json.dumps(report, indent=2) + '\n')
            (backup / 'previous-image.txt').write_text(previous_image + '\n')
            deploy.run(['docker', 'image', 'tag', verified_image, 'wendkonta-php:8.3'])
            image_changed = True
            copy_code(ROOT, APP)
            deploy.compose('run', '--rm', '--no-deps', '-e', 'COMPOSER_ALLOW_SUPERUSER=1', 'php', 'composer', 'install', '--no-dev', '--prefer-dist', '--optimize-autoloader', '--no-interaction')
            deploy.compose('run', '--rm', '--no-deps', 'php', 'composer', 'check-platform-reqs', '--no-dev')
            for migration in MIGRATIONS:
                deploy.compose('run', '--rm', '--no-deps', 'php', 'php', 'artisan', 'migrate', '--force', '--path=' + migration)
            for command in ['optimize:clear', 'view:cache', 'up']:
                deploy.compose('run', '--rm', '--no-deps', '--user', '33:33', 'php', 'php', 'artisan', command)
            deploy.compose('up', '-d', '--wait', '--wait-timeout', '90', 'php')
            stopped = False
            deploy.wait_for_url('https://' + deploy.DOMAIN + '/login', label='Released application', resolve=deploy.DOMAIN + ':443:127.0.0.1')
            deploy.verify()
            deploy.run(['curl','--fail','--silent','--show-error','--noproxy','*','--resolve',deploy.DOMAIN+':443:127.0.0.1','--output','/dev/null','https://'+deploy.DOMAIN+'/manifest.json'])
            deploy.run(['curl','--fail','--silent','--show-error','--noproxy','*','--resolve',deploy.DOMAIN+':443:127.0.0.1','--output','/dev/null','https://'+deploy.DOMAIN+'/service-worker.js'])
            (backup / 'released.json').write_text(json.dumps({'source_sha256':report['source_sha256'],'released_at':stamp,'domain':deploy.DOMAIN,
                'regression_verification':'skipped_by_request' if args.skip_tests else 'passed','deployment_checks':'passed','php_image_id':verified_image},indent=2))
            print('Mobile/PWA deployment completed: https://' + deploy.DOMAIN)
            print('HTTPS and asset checks passed. Regression verification: ' + ('skipped by request.' if args.skip_tests else 'passed.'))
            print('Protected rollback backup:', backup)
    except Exception as error:
        print(str(error), file=sys.stderr)
        if backup_ready:
            try:
                if not stopped: deploy.compose('stop', '-t', '150', 'php')
                copy_code(backup / 'application', APP)
                if (backup / 'application/vendor').is_dir():
                    shutil.rmtree(APP / 'vendor')
                    shutil.copytree(backup / 'application/vendor', APP / 'vendor', symlinks=True)
                if vhost: VHOST.write_bytes(vhost)
                if image_changed:
                    deploy.run(['docker', 'image', 'tag', previous_image, 'wendkonta-php:8.3'])
                deploy.compose('run', '--rm', '--no-deps', '--user', '33:33', 'php', 'php', 'artisan', 'optimize:clear')
                deploy.compose('run', '--rm', '--no-deps', '--user', '33:33', 'php', 'php', 'artisan', 'up')
                deploy.compose('up', '-d', '--wait', '--wait-timeout', '90', 'php')
                deploy.run(['nginx', '-t']); deploy.run(['systemctl', 'reload', 'nginx'])
                print('Previous code restored. Production records and the additive safety table were retained.')
            except Exception as rollback_error:
                print('Rollback needs attention: ' + str(rollback_error), file=sys.stderr)
        elif maintenance or stopped or vhost is not None:
            try:
                if vhost is not None:
                    VHOST.write_bytes(vhost)
                    deploy.run(['nginx', '-t']); deploy.run(['systemctl', 'reload', 'nginx'])
                if maintenance:
                    deploy.compose('run', '--rm', '--no-deps', '--user', '33:33', 'php', 'php', 'artisan', 'up')
                if stopped:
                    deploy.compose('up', '-d', '--wait', '--wait-timeout', '90', 'php')
            except Exception as restart_error: print(str(restart_error), file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())
