"""Regression checks for consuming-project validation and source integrity."""
import hashlib
import json
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]


class ContextValidationTests(unittest.TestCase):
    def run_validator(self, root, *args):
        result = subprocess.run(
            [sys.executable, str(root / "scripts/validate_context.py"), "--root", str(root), "--strict", "--json", *args],
            capture_output=True, text=True, check=False,
        )
        return result.returncode, json.loads(result.stdout)

    def test_project_stack_and_optional_examples(self):
        status, report = self.run_validator(ROOT, "--examples")
        self.assertEqual(status, 0, report)
        self.assertTrue(report["ok"])
        self.assertIn("No example suite", " ".join(report["warnings"]))

    def test_source_hashes(self):
        manifest = json.loads((ROOT / ".csd-source.json").read_text())
        for file in manifest["files"]:
            with self.subTest(path=file["path"]):
                self.assertEqual(hashlib.sha256((ROOT / file["path"]).read_bytes()).hexdigest(), file["sha256"])

    def test_invalid_context_is_rejected(self):
        changes = [
            ("required file", ".context/workflows/core.md", None, "missing required file"),
            ("placeholder", ".context/project/overview.md", "# Overview\n<UNRESOLVED>\n", "unresolved placeholder"),
            ("link", ".context/project/overview.md", "[missing](missing.md)\n", "broken link"),
            ("malformed JSON", ".context/config.json", "{", "invalid config JSON"),
        ]
        for name, path, content, expected in changes:
            with self.subTest(case=name), tempfile.TemporaryDirectory() as directory:
                fixture = Path(directory) / "repo"
                shutil.copytree(ROOT, fixture, ignore=shutil.ignore_patterns(".git", "__pycache__", "node_modules"))
                target = fixture / path
                if content is None:
                    target.unlink()
                else:
                    target.write_text(content)
                status, report = self.run_validator(fixture)
                self.assertEqual(status, 1, report)
                self.assertIn(expected, " ".join(report["errors"]))

    def test_invalid_active_work_item_is_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            fixture = Path(directory) / "repo"
            shutil.copytree(ROOT, fixture, ignore=shutil.ignore_patterns(".git", "__pycache__", "node_modules"))
            item = fixture / ".context/work/FEAT-99/work-item.json"
            item.parent.mkdir()
            item.write_text(json.dumps({"schema_version": "invalid"}))
            status, report = self.run_validator(fixture)
            self.assertEqual(status, 1, report)
            self.assertIn("FEAT-99", " ".join(report["errors"]))

    def test_installed_example_suite_is_validated(self):
        with tempfile.TemporaryDirectory() as directory:
            fixture = Path(directory) / "repo"
            shutil.copytree(ROOT, fixture, ignore=shutil.ignore_patterns(".git", "__pycache__", "node_modules"))
            (fixture / "examples").mkdir()
            status, report = self.run_validator(fixture, "--examples")
            self.assertEqual(status, 1, report)
            self.assertTrue(any("example" in error for error in report["errors"]), report)

    def test_force_push_policy_is_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            fixture = Path(directory) / "repo"
            shutil.copytree(ROOT, fixture, ignore=shutil.ignore_patterns(".git", "__pycache__", "node_modules"))
            path = fixture / ".context/orchestration/config.json"
            config = json.loads(path.read_text())
            config["git"]["allow_force_push"] = True
            path.write_text(json.dumps(config))
            status, report = self.run_validator(fixture)
            self.assertEqual(status, 1, report)
            self.assertIn("allow_force_push must be false", " ".join(report["errors"]))


if __name__ == "__main__":
    unittest.main()
