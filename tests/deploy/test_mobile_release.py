"""Check isolated fixture extraction and preservation of production files."""

import importlib.util
from pathlib import Path
import tempfile
import unittest
from contextlib import redirect_stderr
from io import StringIO
import json
from types import SimpleNamespace
from unittest.mock import Mock, patch


ROOT = Path(__file__).resolve().parents[2]


def load(name, path):
    spec = importlib.util.spec_from_file_location(name, ROOT / path)
    result = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(result)
    return result


verification = load('mobile_verification_test', 'deploy/test_mobile.py')
release = load('mobile_release_test', 'deploy/release_mobile.py')


class MobileReleaseSafetyTest(unittest.TestCase):
    def assert_release_is_blocked_before_server_access(self, report):
        with tempfile.TemporaryDirectory() as folder:
            results = Path(folder)
            (results / 'verification.json').write_text(json.dumps(report))
            verification_stub = SimpleNamespace(RESULTS=results, source_digest=lambda: 'current-source')
            deployment_stub = SimpleNamespace(preflight=Mock())
            with patch.object(release, 'module', side_effect=lambda name, filename: verification_stub if name == 'mobile_verification' else deployment_stub), patch.object(release.sys, 'argv', ['release_mobile.py']), redirect_stderr(StringIO()):
                self.assertEqual(1, release.main())
            deployment_stub.preflight.assert_not_called()

    def test_filtered_browser_success_cannot_authorize_a_release(self):
        self.assert_release_is_blocked_before_server_access({'status': 'passed', 'php': 'passed', 'browser': 'passed', 'python': 'passed', 'full_suite': False, 'source_sha256': 'current-source'})

    def test_changed_source_cannot_use_an_older_passing_report(self):
        self.assert_release_is_blocked_before_server_access({'status': 'passed', 'php': 'passed', 'browser': 'passed', 'python': 'passed', 'full_suite': True, 'source_sha256': 'old-source'})

    def test_failed_backend_suite_cannot_authorize_a_release(self):
        self.assert_release_is_blocked_before_server_access({'status': 'passed', 'php': 'failed', 'browser': 'passed', 'python': 'passed', 'full_suite': True, 'source_sha256': 'current-source'})

    def test_explicit_skip_does_not_require_a_report_but_still_runs_server_preflight(self):
        verification_stub = SimpleNamespace(source_digest=lambda: 'current-source')
        deployment_stub = SimpleNamespace(preflight=Mock(side_effect=RuntimeError('Host access unavailable')))
        with patch.object(release, 'module', side_effect=lambda name, filename: verification_stub if name == 'mobile_verification' else deployment_stub), patch.object(release.sys, 'argv', ['release_mobile.py', '--skip-tests']), redirect_stderr(StringIO()):
            self.assertEqual(1, release.main())
        deployment_stub.preflight.assert_called_once()

    def test_explicit_skip_builds_before_changing_the_production_installation(self):
        verification_stub = SimpleNamespace(source_digest=lambda: 'current-source')
        deployment_stub = SimpleNamespace(preflight=Mock(), run=Mock(side_effect=RuntimeError('Build failed')))
        with patch.object(release, 'module', side_effect=lambda name, filename: verification_stub if name == 'mobile_verification' else deployment_stub), patch.object(release.sys, 'argv', ['release_mobile.py', '--skip-tests']), redirect_stderr(StringIO()):
            self.assertEqual(1, release.main())
        deployment_stub.preflight.assert_called_once()
        deployment_stub.run.assert_called_once()
        self.assertEqual(['docker', 'build'], deployment_stub.run.call_args.args[0][:2])

    def test_isolated_database_extracts_schema_without_customer_records(self):
        schema, permissions = verification.schema_and_permissions((ROOT / 'db.sql').read_text())
        self.assertEqual(106, schema.count('CREATE TABLE'))
        self.assertNotIn('INSERT INTO', schema)
        self.assertNotIn('DROP DATABASE', schema)
        self.assertNotIn('USE `', schema)
        self.assertIn('sales-add', permissions)

    def test_unrecognized_schema_is_rejected(self):
        with self.assertRaises(ValueError):
            verification.schema_and_permissions('CREATE TABLE unknown (id INTEGER);')

    def test_overlay_retains_credentials_uploads_runtime_and_dependencies(self):
        retained = ['.env', 'db.sql', 'storage/app/public/upload.jpg', 'public/images/product/upload.jpg',
                    'public/logo/logo.png', 'public/documents/invoice.pdf', 'bootstrap/cache/config.php',
                    'vendor/autoload.php', 'deploy/state/deployment.json']
        updated = ['app/Http/Kernel.php', 'public/vendor/bootstrap/css/bootstrap.min.css',
                   'public/js/salepro-mobile.js', 'public/manifest.json']
        with tempfile.TemporaryDirectory() as folder:
            source, destination = Path(folder) / 'source', Path(folder) / 'destination'
            for name in retained + updated:
                for root, content in [(source, 'source value'), (destination, 'production value')]:
                    path = root / name
                    path.parent.mkdir(parents=True, exist_ok=True)
                    path.write_text(content)
            release.copy_code(source, destination)
            for name in retained:
                self.assertEqual('production value', (destination / name).read_text(), name)
            for name in updated:
                self.assertEqual('source value', (destination / name).read_text(), name)


if __name__ == '__main__':
    unittest.main()
