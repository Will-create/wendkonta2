"""Failed-only reruns must retain exact browser cases and the prior evidence."""

from contextlib import redirect_stderr, redirect_stdout
import importlib.util
from io import StringIO
import json
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch


ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('failed_browser_runner', ROOT / 'deploy/test_mobile.py')
runner = importlib.util.module_from_spec(spec)
spec.loader.exec_module(runner)


class FailedBrowserRerunTest(unittest.TestCase):
    def setUp(self):
        folder = tempfile.TemporaryDirectory()
        self.addCleanup(folder.cleanup)
        self.results = Path(folder.name) / 'mobile'
        (self.results / 'artifacts').mkdir(parents=True)
        self.state_file = self.results / 'artifacts/.last-run.json'
        self.state = {'status': 'failed', 'failedTests': ['android-transfer', 'ios-offline']}
        self.state_file.write_text(json.dumps(self.state))
        (self.results / 'verification.json').write_text(json.dumps({'source_sha256': 'previous-source', 'python': 'passed', 'php': 'passed', 'status': 'failed'}))
        (self.results / 'browser.json').write_text(json.dumps({'suites': [{
            'specs': [
                {'id': 'android-transfer', 'title': 'Stock transfer', 'tests': [{'projectName': 'android-chromium'}]},
                {'id': 'desktop-transfer', 'title': 'Stock transfer', 'tests': [{'projectName': 'desktop-chromium'}]},
            ],
            'suites': [{'specs': [{'id': 'ios-offline', 'title': 'Offline navigation', 'tests': [{'projectName': 'ios-webkit'}]}]}],
        }]}))

    def test_selection_uses_project_specific_ids_and_excludes_a_passing_case_with_the_same_title(self):
        failed = runner.previous_failures(self.results)
        self.assertEqual(self.state, failed['state'])
        self.assertEqual(['android-chromium', 'ios-webkit'], [case['project'] for case in failed['cases']])
        self.assertNotIn('desktop-transfer', [case['id'] for case in failed['cases']])
        self.assertEqual('previous-source', failed['baseline']['source_sha256'])

    def test_archiving_preserves_old_artifacts_and_restores_the_native_failure_marker(self):
        (self.results / 'artifacts/failure.txt').write_text('previous failure evidence')
        failed = runner.previous_failures(self.results)
        with redirect_stdout(StringIO()):
            runner.prepare_results(self.results, failed['state'])
        self.assertEqual(self.state, json.loads(self.state_file.read_text()))
        archives = list((self.results.parent / 'mobile-history').iterdir())
        self.assertEqual(1, len(archives))
        self.assertEqual('previous failure evidence', (archives[0] / 'artifacts/failure.txt').read_text())
        self.assertEqual(self.state, json.loads((archives[0] / 'artifacts/.last-run.json').read_text()))
        self.assertFalse((self.results / 'verification.json').exists())

    def test_missing_marker_never_falls_back_to_a_full_run(self):
        self.state_file.unlink()
        with self.assertRaisesRegex(ValueError, 'No previous browser failure list'):
            runner.previous_failures(self.results)

    def test_no_failures_never_falls_back_to_a_full_run(self):
        self.state_file.write_text(json.dumps({'status': 'passed', 'failedTests': []}))
        with self.assertRaisesRegex(ValueError, 'no recorded failed'):
            runner.previous_failures(self.results)

    def test_invalid_or_duplicate_ids_are_rejected(self):
        for ids in [['duplicate', 'duplicate'], [None], [''], 'not-an-array']:
            self.state_file.write_text(json.dumps({'status': 'failed', 'failedTests': ids}))
            with self.assertRaises(ValueError):
                runner.previous_failures(self.results)

    def test_failed_only_mode_skips_passing_suites_and_keeps_selection_after_a_docker_blocker(self):
        with patch.object(runner, 'RESULTS', self.results), patch.object(runner, 'source_digest', return_value='updated-source'), patch.object(runner.sys, 'argv', ['test_mobile.py', '--last-failed']), patch.object(runner.subprocess, 'run', return_value=subprocess.CompletedProcess([], 1, '', 'Docker unavailable')) as command, redirect_stdout(StringIO()), redirect_stderr(StringIO()):
            self.assertEqual(1, runner.main())
        command.assert_called_once_with(['docker', 'version', '--format', '{{.Server.Version}}'], capture_output=True, text=True)
        report = json.loads((self.results / 'verification.json').read_text())
        self.assertEqual('last-failed', report['scope'])
        self.assertFalse(report['full_suite'])
        self.assertEqual('not_run', report['python'])
        self.assertEqual('not_run', report['php'])
        self.assertEqual('not_run', report['javascript'])
        self.assertEqual(self.state['failedTests'], report['failed_test_ids'])
        self.assertEqual(self.state, json.loads(self.state_file.read_text()))


if __name__ == '__main__':
    unittest.main()
