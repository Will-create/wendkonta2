"""Deployment data-safety checks. Run: python3 -m unittest discover -s tests/deploy -v"""

import importlib.util
from pathlib import Path
import tempfile
import unittest
from subprocess import CompletedProcess
from unittest.mock import patch


ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location("deployment", ROOT / "deploy/deploy.py")
deployment = importlib.util.module_from_spec(spec)
spec.loader.exec_module(deployment)


class DumpSafetyTest(unittest.TestCase):
    def setUp(self):
        self.original = (ROOT / "db.sql").read_text()

    def test_real_dump_keeps_every_table_and_record_and_removes_only_database_preamble(self):
        expected = "\n".join(
            "" if line.startswith(("DROP DATABASE ", "CREATE DATABASE ", "USE ")) else line
            for line in self.original.split("\n")
        )
        self.assertEqual(expected, deployment.sanitize_dump(self.original))

    def test_refuses_missing_source_database_directive(self):
        with self.assertRaises(ValueError):
            deployment.sanitize_dump(self.original.replace("USE `salepro`;", ""))

    def test_refuses_duplicate_source_database_directive(self):
        with self.assertRaises(ValueError):
            deployment.sanitize_dump(self.original + "\nUSE `salepro`;\n")

    def test_refuses_additional_database_selection(self):
        with self.assertRaises(ValueError):
            deployment.sanitize_dump(self.original + "\nUSE `other_database`;\n")

    def test_refuses_qualified_source_table_reference(self):
        with self.assertRaises(ValueError):
            deployment.sanitize_dump(self.original + "\nDROP TABLE `salepro`.`users`;\n")

    @patch.object(deployment, "run")
    def test_import_uses_database_scoped_account_and_keeps_password_out_of_argv(self, run):
        password = "a" * 64
        deployment.import_dump("SELECT 1;", password)
        self.assertNotIn(password, " ".join(run.call_args.args[0]))
        self.assertIn("--user=wendkonta_app", run.call_args.args[0][-1])
        self.assertIn("--database=wendkonta", run.call_args.args[0][-1])
        self.assertEqual(password + "\nSELECT 1;", run.call_args.kwargs["data"])


class ExistingDeploymentTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.state = Path(self.temporary.name) / "deployment.json"
        self.patches = [
            patch.object(deployment, "STATE_FILE", self.state),
            patch.object(deployment, "VHOST", Path(self.temporary.name) / "site.conf"),
            patch.object(deployment, "APP", Path(self.temporary.name) / "application"),
        ]
        for item in self.patches:
            item.start()
            self.addCleanup(item.stop)

    @patch.object(deployment, "stage_application")
    @patch.object(deployment, "run")
    @patch.object(deployment, "mysql", return_value="1")
    def test_refuses_preexisting_database_before_creating_user_or_importing(self, mysql, run, stage):
        with self.assertRaisesRegex(RuntimeError, "database already exists"):
            deployment.deploy(False, None)
        self.assertEqual(1, mysql.call_count)
        self.assertTrue(mysql.call_args.args[0].startswith("SELECT COUNT(*)"))
        run.assert_not_called()
        stage.assert_not_called()

    @patch.object(deployment, "mysql")
    def test_requires_explicit_resume_for_saved_deployment(self, mysql):
        self.state.write_text('{"phase":"imported"}')
        with self.assertRaisesRegex(RuntimeError, "Use --resume"):
            deployment.deploy(False, None)
        mysql.assert_not_called()

    @patch.object(deployment, "mysql")
    def test_resume_refuses_changed_dump_before_database_access(self, mysql):
        self.state.write_text('{"phase":"imported","dump_sha256":"wrong"}')
        with self.assertRaisesRegex(RuntimeError, "Dump changed"):
            deployment.deploy(True, None)
        mysql.assert_not_called()

    @patch.object(deployment, "run")
    def test_failed_nginx_reload_restores_previous_configuration(self, run):
        deployment.VHOST.write_text("previous configuration\n")
        run.side_effect = [None, RuntimeError("reload failed"), None, None]
        with self.assertRaisesRegex(RuntimeError, "reload failed"):
            deployment.install_nginx("nginx-http.conf")
        self.assertEqual("previous configuration\n", deployment.VHOST.read_text())

    @patch.object(deployment, "run")
    def test_invalid_new_vhost_is_removed_when_no_previous_vhost_existed(self, run):
        run.side_effect = [RuntimeError("invalid configuration"), None, None]
        with self.assertRaisesRegex(RuntimeError, "invalid configuration"):
            deployment.install_nginx("nginx-http.conf")
        self.assertFalse(deployment.VHOST.exists())

    @patch.object(deployment.os, "chown")
    def test_staged_checkout_has_private_credentials_and_a_working_relative_storage_link(self, chown):
        deployment.stage_application("a" * 64)
        env = deployment.APP / ".env"
        self.assertEqual(0o640, env.stat().st_mode & 0o777)
        chown.assert_any_call(env, 0, 33)
        self.assertIn("APP_ENV=production\n", env.read_text())
        self.assertNotIn("GENERATED_BY_DEPLOYMENT", env.read_text())
        self.assertFalse((deployment.APP / "db.sql").exists())
        css = Path("public/vendor/bootstrap/css/bootstrap.min.css")
        self.assertEqual((ROOT / css).read_bytes(), (deployment.APP / css).read_bytes())
        self.assertEqual("../storage/app/public", str((deployment.APP / "public/storage").readlink()))
        self.assertEqual((deployment.APP / "storage/app/public").resolve(), (deployment.APP / "public/storage").resolve())
        self.assertEqual([], list((deployment.APP / "bootstrap/cache").glob("*.php")))

    def test_resume_restores_missing_static_assets_without_overwriting_existing_files(self):
        source = Path(self.temporary.name) / "source"
        vendor = source / "public/vendor/bootstrap/css"
        vendor.mkdir(parents=True)
        (vendor / "bootstrap.min.css").write_text("bootstrap css")
        (vendor / "bootstrap.css").write_text("repository css")
        existing = deployment.APP / "public/vendor/bootstrap/css/bootstrap.css"
        existing.parent.mkdir(parents=True)
        existing.write_text("existing deployed css")
        (deployment.APP / ".env").write_text("existing application key and database credentials")
        upload = deployment.APP / "public/images/product/upload.jpg"
        upload.parent.mkdir(parents=True)
        upload.write_bytes(b"existing upload")

        with patch.object(deployment, "SOURCE", source):
            deployment.restore_missing_vendor_assets()
            deployment.restore_missing_vendor_assets()

        self.assertEqual("bootstrap css", existing.with_name("bootstrap.min.css").read_text())
        self.assertEqual("existing deployed css", existing.read_text())
        self.assertEqual("existing application key and database credentials", (deployment.APP / ".env").read_text())
        self.assertEqual(b"existing upload", upload.read_bytes())


class NginxReadinessTest(unittest.TestCase):
    @patch.object(deployment.time, "sleep")
    @patch.object(deployment.subprocess, "run")
    def test_retries_old_worker_404_until_new_worker_serves_the_exact_token(self, run, sleep):
        run.side_effect = [
            CompletedProcess([], 0, "not found\n404", ""),
            CompletedProcess([], 0, "wrong token\n200", ""),
            CompletedProcess([], 0, "challenge-token\n200", ""),
        ]
        deployment.wait_for_url("http://pos.test/challenge", label="Local ACME challenge",
                                resolve="pos.test:80:127.0.0.1", expected_body="challenge-token")
        self.assertEqual(3, run.call_count)
        self.assertEqual(2, sleep.call_count)
        args = run.call_args.args[0]
        self.assertIn("--noproxy", args)
        self.assertIn("pos.test:80:127.0.0.1", args)

    @patch.object(deployment.time, "sleep")
    @patch.object(deployment.time, "monotonic", side_effect=[0, 0, 31, 31])
    @patch.object(deployment.subprocess, "run", return_value=CompletedProcess([], 0, "not found\n404", ""))
    def test_persistent_public_404_reports_the_stage_url_and_status(self, run, monotonic, sleep):
        with self.assertRaisesRegex(RuntimeError, r"Public ACME challenge.*HTTP 404.*http://pos.test/challenge"):
            deployment.wait_for_url("http://pos.test/challenge", label="Public ACME challenge",
                                    expected_body="challenge-token")
        self.assertNotIn("--resolve", run.call_args.args[0])

    @patch.object(deployment.time, "sleep")
    @patch.object(deployment.subprocess, "run")
    def test_https_wait_retries_until_the_new_certificate_is_loaded(self, run, sleep):
        run.side_effect = [
            CompletedProcess([], 60, "\n000", "certificate name mismatch"),
            CompletedProcess([], 0, "login page\n200", ""),
        ]
        deployment.wait_for_url("https://pos.test/login", label="Local HTTPS application",
                                resolve="pos.test:443:127.0.0.1")
        self.assertEqual(2, run.call_count)
        self.assertNotIn("--insecure", run.call_args.args[0])


if __name__ == "__main__":
    unittest.main()
