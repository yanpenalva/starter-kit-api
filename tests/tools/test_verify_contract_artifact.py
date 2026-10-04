"""Tests for artifact provenance verification script (scripts/verify_contract_artifact.py).

Verifies fail-closed provenance requirements:
- Validates pinned exact 40-character commit SHA from 'main'.
- Rejects non-pinned tags, 'latest', or malformed SHA formats.
- Enforces event allowlist (workflow_dispatch, release, workflow_run).
- Enforces SHA256 file digest match against receipt metadata.
- Rejects incomplete, malformed, or missing receipt fields.
- Rejects untrusted source branches (anything other than 'main').
- Rejects tampered artifacts or divergent digests.
- Validates CLI execution codes and fail-closed error signaling.
- Zero external network/gh API calls; zero secrets.
"""

from __future__ import annotations

import hashlib
import io
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

from scripts.verify_contract_artifact import (
    ProvenanceVerificationError,
    build_receipt,
    compute_file_sha256,
    run_cli,
    validate_commit_sha,
    validate_sha256_digest,
    verify_provenance,
)

SAMPLE_SHA: str = "5d8c198296949b7f3c66b2bd56f086cb8f9ceafe"
SAMPLE_WORKFLOW_NAME: str = "OpenAPI Contract"
SAMPLE_WORKFLOW_ID: int = 12345678
SAMPLE_RUN_ID: int = 37215996931
SAMPLE_RUN_URL: str = "https://github.com/yanpenalva/starter-kit-api/actions/runs/37215996931"
SAMPLE_ARTIFACT_ID: int = 987654321
SAMPLE_ARTIFACT_NAME: str = "openapi-contract"
SAMPLE_EVENT: str = "workflow_dispatch"
SAMPLE_CREATED_AT: str = "2026-10-04T16:13:55Z"


class VerifyContractArtifactTests(unittest.TestCase):
    def setUp(self) -> None:
        self.temp_dir = tempfile.TemporaryDirectory()
        self.root = Path(self.temp_dir.name)
        self.artifact_path = self.root / "openapi.json"
        self.artifact_content = b'{"openapi": "3.1.0", "info": {"title": "Test", "version": "1.0"}}'
        self.artifact_path.write_bytes(self.artifact_content)
        self.artifact_sha256 = hashlib.sha256(self.artifact_content).hexdigest()

        self.receipt_path = self.root / "contract-receipt.json"
        self.valid_receipt = build_receipt(
            workflow_name=SAMPLE_WORKFLOW_NAME,
            workflow_id=SAMPLE_WORKFLOW_ID,
            run_id=SAMPLE_RUN_ID,
            run_url=SAMPLE_RUN_URL,
            artifact_id=SAMPLE_ARTIFACT_ID,
            artifact_name=SAMPLE_ARTIFACT_NAME,
            commit_sha=SAMPLE_SHA,
            head_branch="main",
            event=SAMPLE_EVENT,
            created_at=SAMPLE_CREATED_AT,
            artifact_sha256=self.artifact_sha256,
        )
        self.receipt_path.write_text(json.dumps(self.valid_receipt), encoding="utf-8")

    def tearDown(self) -> None:
        self.temp_dir.cleanup()

    def test_valid_provenance_verification_passes(self) -> None:
        result = verify_provenance(
            artifact_path=self.artifact_path,
            receipt_path=self.receipt_path,
            expected_sha=SAMPLE_SHA,
            expected_branch="main",
        )
        self.assertEqual(result["status"], "verified")
        self.assertEqual(result["commit_sha"], SAMPLE_SHA)
        self.assertEqual(result["head_branch"], "main")
        self.assertEqual(result["artifact_sha256"], self.artifact_sha256)

    def test_missing_receipt_file_fails_closed(self) -> None:
        missing_receipt = self.root / "does-not-exist.json"
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=missing_receipt,
                expected_sha=SAMPLE_SHA,
            )
        self.assertIn("Receipt file not found", str(ctx.exception))

    def test_missing_artifact_file_fails_closed(self) -> None:
        missing_artifact = self.root / "missing-artifact.json"
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=missing_artifact,
                receipt_path=self.receipt_path,
                expected_sha=SAMPLE_SHA,
            )
        self.assertIn("Artifact file not found", str(ctx.exception))

    def test_malformed_receipt_json_fails_closed(self) -> None:
        self.receipt_path.write_text("NOT_JSON{", encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                expected_sha=SAMPLE_SHA,
            )
        self.assertIn("Receipt file is malformed JSON", str(ctx.exception))

    def test_missing_required_field_in_receipt_fails_closed(self) -> None:
        required_fields = (
            "workflow_name",
            "workflow_id",
            "run_id",
            "run_url",
            "artifact_id",
            "artifact_name",
            "commit_sha",
            "head_branch",
            "event",
            "created_at",
            "artifact_sha256",
        )
        for field in required_fields:
            corrupted = dict(self.valid_receipt)
            del corrupted[field]
            self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
            with self.subTest(missing_field=field):
                with self.assertRaises(ProvenanceVerificationError) as ctx:
                    verify_provenance(
                        artifact_path=self.artifact_path,
                        receipt_path=self.receipt_path,
                        expected_sha=SAMPLE_SHA,
                    )
                self.assertIn("Receipt missing or empty required fields", str(ctx.exception))

    def test_empty_required_field_in_receipt_fails_closed(self) -> None:
        corrupted = dict(self.valid_receipt)
        corrupted["workflow_name"] = "   "
        self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                expected_sha=SAMPLE_SHA,
            )
        self.assertIn("Receipt missing or empty required fields", str(ctx.exception))

    def test_expected_sha_not_40_hex_fails_closed(self) -> None:
        invalid_shas = ("latest", "HEAD", "v1.0.0", "5d8c198", "not-a-valid-hex-commit-sha-at-all!!", "")
        for invalid in invalid_shas:
            with self.subTest(invalid_sha=invalid):
                with self.assertRaises(ProvenanceVerificationError) as ctx:
                    verify_provenance(
                        artifact_path=self.artifact_path,
                        receipt_path=self.receipt_path,
                        expected_sha=invalid,
                    )
                self.assertIn("Invalid expected_sha", str(ctx.exception))

    def test_receipt_commit_sha_not_40_hex_fails_closed(self) -> None:
        corrupted = dict(self.valid_receipt)
        corrupted["commit_sha"] = "latest"
        self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                expected_sha=SAMPLE_SHA,
            )
        self.assertIn("Invalid receipt commit_sha", str(ctx.exception))

    def test_commit_sha_mismatch_fails_closed(self) -> None:
        divergent_sha = "0000000000000000000000000000000000000000"
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                expected_sha=divergent_sha,
            )
        self.assertIn("Commit SHA mismatch", str(ctx.exception))

    def test_head_branch_outside_main_fails_closed(self) -> None:
        corrupted = dict(self.valid_receipt)
        corrupted["head_branch"] = "feat/unreviewed-branch"
        self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                expected_sha=SAMPLE_SHA,
                expected_branch="main",
            )
        self.assertIn("Head branch mismatch", str(ctx.exception))

    def test_event_outside_allowlist_fails_closed(self) -> None:
        untrusted_events = ("pull_request", "issues", "fork", "schedule", "repository_dispatch")
        for untrusted in untrusted_events:
            corrupted = dict(self.valid_receipt)
            corrupted["event"] = untrusted
            self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
            with self.subTest(untrusted_event=untrusted):
                with self.assertRaises(ProvenanceVerificationError) as ctx:
                    verify_provenance(
                        artifact_path=self.artifact_path,
                        receipt_path=self.receipt_path,
                        expected_sha=SAMPLE_SHA,
                    )
                self.assertIn("not in allowed workflow events", str(ctx.exception))

    def test_artifact_digest_divergence_fails_closed(self) -> None:
        # Tamper with the artifact content after receipt generation
        self.artifact_path.write_bytes(b'{"tampered": true}')
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                expected_sha=SAMPLE_SHA,
            )
        self.assertIn("Artifact digest mismatch", str(ctx.exception))

    def test_invalid_sha256_format_in_receipt_fails_closed(self) -> None:
        corrupted = dict(self.valid_receipt)
        corrupted["artifact_sha256"] = "short-hash"
        self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                expected_sha=SAMPLE_SHA,
            )
        self.assertIn("must be exact 64-character hexadecimal SHA256 digest", str(ctx.exception))

    def test_invalid_created_at_timestamp_fails_closed(self) -> None:
        corrupted = dict(self.valid_receipt)
        corrupted["created_at"] = "invalid-date-format"
        self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                expected_sha=SAMPLE_SHA,
            )
        self.assertIn("Invalid ISO 8601 created_at timestamp", str(ctx.exception))

    def test_invalid_positive_ids_fail_closed(self) -> None:
        for field in ("run_id", "artifact_id"):
            corrupted = dict(self.valid_receipt)
            corrupted[field] = -5
            self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
            with self.subTest(invalid_id_field=field):
                with self.assertRaises(ProvenanceVerificationError) as ctx:
                    verify_provenance(
                        artifact_path=self.artifact_path,
                        receipt_path=self.receipt_path,
                        expected_sha=SAMPLE_SHA,
                    )
                self.assertIn("must be a positive integer", str(ctx.exception))

    def test_invalid_run_url_fails_closed(self) -> None:
        corrupted = dict(self.valid_receipt)
        corrupted["run_url"] = "ftp://invalid-url.local"
        self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                expected_sha=SAMPLE_SHA,
            )
        self.assertIn("must be a valid URL", str(ctx.exception))

    def test_cli_success(self) -> None:
        argv = [
            "--artifact-file", str(self.artifact_path),
            "--receipt-file", str(self.receipt_path),
            "--expected-sha", SAMPLE_SHA,
            "--expected-branch", "main",
        ]
        with patch("sys.stdout", new=io.StringIO()) as fake_out:
            exit_code = run_cli(argv)
        self.assertEqual(exit_code, 0)
        self.assertIn("Artifact provenance verified", fake_out.getvalue())

    def test_cli_failure_on_tampered_artifact(self) -> None:
        self.artifact_path.write_bytes(b'{"malicious": "payload"}')
        argv = [
            "--artifact-file", str(self.artifact_path),
            "--receipt-file", str(self.receipt_path),
            "--expected-sha", SAMPLE_SHA,
        ]
        with patch("sys.stderr", new=io.StringIO()) as fake_err:
            exit_code = run_cli(argv)
        self.assertEqual(exit_code, 1)
        self.assertIn("FAIL CLOSED: Provenance verification failed: Artifact digest mismatch", fake_err.getvalue())

    def test_cli_failure_on_invalid_sha(self) -> None:
        argv = [
            "--artifact-file", str(self.artifact_path),
            "--receipt-file", str(self.receipt_path),
            "--expected-sha", "latest",
        ]
        with patch("sys.stderr", new=io.StringIO()) as fake_err:
            exit_code = run_cli(argv)
        self.assertEqual(exit_code, 1)
        self.assertIn("FAIL CLOSED: Provenance verification failed: Invalid expected_sha", fake_err.getvalue())


if __name__ == "__main__":
    unittest.main()
