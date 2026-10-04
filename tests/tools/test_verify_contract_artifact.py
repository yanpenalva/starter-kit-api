"""Tests for artifact provenance verification script (scripts/verify_contract_artifact.py).

Verifies fail-closed provenance requirements:
- Validates pinned exact 40-character commit SHA from 'main'.
- Rejects non-pinned tags, 'latest', or malformed SHA formats.
- Enforces event allowlist (workflow_dispatch, release, workflow_run).
- Enforces strict workflow identity (name, path, artifact name, workflow ID).
- Enforces strict run_url (https, exact host, run ID match, no query/fragment).
- Rejects duplicate JSON keys in receipt.
- Enforces strict integer types for IDs (rejects bool, float, string numeric).
- Enforces nominal file reading bounds (MAX_RECEIPT_BYTES, MAX_ARTIFACT_BYTES).
- Enforces dual digests (archive_sha256 provider ZIP vs artifact_file_sha256 spec).
- Validates expiration modes (download-fresh vs verify-committed).
- Validates CLI execution codes and fail-closed error signaling.
- Zero external network/gh API calls; zero secrets.
"""

from __future__ import annotations

from dataclasses import FrozenInstanceError, replace
from datetime import datetime, timedelta, timezone
import hashlib
import io
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch

from scripts.verify_contract_artifact import (
    DEFAULT_ALLOWED_EVENTS,
    DEFAULT_BRANCH,
    DEFAULT_MAX_AGE_DAYS,
    DEFAULT_WORKFLOW_NAME,
    DEFAULT_WORKFLOW_PATH,
    MAX_ARTIFACT_BYTES,
    MAX_RECEIPT_BYTES,
    MODE_DOWNLOAD_FRESH,
    MODE_VERIFY_COMMITTED,
    ProvenanceVerificationError,
    ReceiptRecord,
    VerificationPolicy,
    build_receipt,
    read_bounded_bytes,
    run_cli,
    validate_receipt_identity,
    verify_provenance,
)


SAMPLE_SHA: str = "5d8c198296949b7f3c66b2bd56f086cb8f9ceafe"
SAMPLE_WORKFLOW_NAME: str = "OpenAPI Contract"
SAMPLE_WORKFLOW_PATH: str = ".github/workflows/openapi.yml"
SAMPLE_WORKFLOW_ID: int = 12345678
SAMPLE_RUN_ID: int = 37215996931
SAMPLE_RUN_URL: str = "https://github.com/yanpenalva/starter-kit-api/actions/runs/37215996931"
SAMPLE_ARTIFACT_ID: int = 987654321
SAMPLE_ARTIFACT_NAME: str = f"openapi-{SAMPLE_SHA}"
SAMPLE_EVENT: str = "workflow_dispatch"
SAMPLE_CREATED_AT: str = "2026-10-04T16:13:55Z"
SAMPLE_ARCHIVE_SHA256: str = "a" * 64


def make_sample_record(artifact_file_sha256: str) -> ReceiptRecord:
    return ReceiptRecord(
        workflow_name=SAMPLE_WORKFLOW_NAME,
        workflow_path=SAMPLE_WORKFLOW_PATH,
        workflow_id=SAMPLE_WORKFLOW_ID,
        run_id=SAMPLE_RUN_ID,
        run_url=SAMPLE_RUN_URL,
        artifact_id=SAMPLE_ARTIFACT_ID,
        artifact_name=SAMPLE_ARTIFACT_NAME,
        commit_sha=SAMPLE_SHA,
        head_branch="main",
        event=SAMPLE_EVENT,
        created_at=SAMPLE_CREATED_AT,
        archive_sha256=SAMPLE_ARCHIVE_SHA256,
        artifact_file_sha256=artifact_file_sha256,
    )


class VerifyContractArtifactTests(unittest.TestCase):
    def setUp(self) -> None:
        self.temp_dir = tempfile.TemporaryDirectory()
        self.root = Path(self.temp_dir.name)
        self.artifact_path = self.root / "openapi.json"
        self.artifact_content = b'{"openapi": "3.1.0", "info": {"title": "Test", "version": "1.0"}}'
        self.artifact_path.write_bytes(self.artifact_content)
        self.artifact_file_sha256 = hashlib.sha256(self.artifact_content).hexdigest()

        self.receipt_path = self.root / "contract-receipt.json"
        self.sample_record = make_sample_record(self.artifact_file_sha256)
        self.valid_receipt = build_receipt(self.sample_record)
        self.receipt_path.write_text(json.dumps(self.valid_receipt), encoding="utf-8")
        self.default_policy = VerificationPolicy(expected_sha=SAMPLE_SHA)

    def tearDown(self) -> None:
        self.temp_dir.cleanup()

    def test_valid_provenance_verification_committed_mode_passes(self) -> None:
        policy = VerificationPolicy(
            expected_sha=SAMPLE_SHA,
            expected_branch="main",
            expected_workflow_name=SAMPLE_WORKFLOW_NAME,
            expected_workflow_path=SAMPLE_WORKFLOW_PATH,
            expected_workflow_id=SAMPLE_WORKFLOW_ID,
        )
        result = verify_provenance(
            artifact_path=self.artifact_path,
            receipt_path=self.receipt_path,
            policy=policy,
            mode=MODE_VERIFY_COMMITTED,
        )
        self.assertEqual(result["status"], "verified")
        self.assertEqual(result["commit_sha"], SAMPLE_SHA)
        self.assertEqual(result["head_branch"], "main")
        self.assertEqual(result["artifact_file_sha256"], self.artifact_file_sha256)
        self.assertEqual(result["archive_sha256"], SAMPLE_ARCHIVE_SHA256)

    def test_valid_provenance_verification_download_fresh_mode_passes(self) -> None:
        recent_receipt = dict(self.valid_receipt)
        recent_receipt["created_at"] = datetime.now(timezone.utc).isoformat()
        self.receipt_path.write_text(json.dumps(recent_receipt), encoding="utf-8")

        result = verify_provenance(
            artifact_path=self.artifact_path,
            receipt_path=self.receipt_path,
            policy=self.default_policy,
            mode=MODE_DOWNLOAD_FRESH,
        )
        self.assertEqual(result["status"], "verified")

    def test_missing_receipt_file_fails_closed(self) -> None:
        missing_receipt = self.root / "does-not-exist.json"
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=missing_receipt,
                policy=self.default_policy,
            )
        self.assertIn("Receipt file not found", str(ctx.exception))

    def test_missing_artifact_file_fails_closed(self) -> None:
        missing_artifact = self.root / "missing-artifact.json"
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=missing_artifact,
                receipt_path=self.receipt_path,
                policy=self.default_policy,
            )
        self.assertIn("Artifact file not found", str(ctx.exception))

    def test_malformed_receipt_json_fails_closed(self) -> None:
        self.receipt_path.write_text("NOT_JSON{", encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                policy=self.default_policy,
            )
        self.assertIn("Receipt file is malformed JSON", str(ctx.exception))

    def test_duplicate_json_keys_in_receipt_fails_closed(self) -> None:
        raw_json = (
            '{\n'
            f'  "workflow_name": "{SAMPLE_WORKFLOW_NAME}",\n'
            f'  "workflow_name": "{SAMPLE_WORKFLOW_NAME}",\n'
            f'  "workflow_path": "{SAMPLE_WORKFLOW_PATH}",\n'
            f'  "workflow_id": {SAMPLE_WORKFLOW_ID},\n'
            f'  "run_id": {SAMPLE_RUN_ID},\n'
            f'  "run_url": "{SAMPLE_RUN_URL}",\n'
            f'  "artifact_id": {SAMPLE_ARTIFACT_ID},\n'
            f'  "artifact_name": "{SAMPLE_ARTIFACT_NAME}",\n'
            f'  "commit_sha": "{SAMPLE_SHA}",\n'
            '  "head_branch": "main",\n'
            f'  "event": "{SAMPLE_EVENT}",\n'
            f'  "created_at": "{SAMPLE_CREATED_AT}",\n'
            f'  "archive_sha256": "{SAMPLE_ARCHIVE_SHA256}",\n'
            f'  "artifact_file_sha256": "{self.artifact_file_sha256}"\n'
            '}'
        )
        self.receipt_path.write_text(raw_json, encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                policy=self.default_policy,
            )
        self.assertIn("Duplicate JSON key in receipt", str(ctx.exception))

    def test_missing_required_field_in_receipt_fails_closed(self) -> None:
        required_fields = (
            "workflow_name",
            "workflow_path",
            "workflow_id",
            "run_id",
            "run_url",
            "artifact_id",
            "artifact_name",
            "commit_sha",
            "head_branch",
            "event",
            "created_at",
            "archive_sha256",
            "artifact_file_sha256",
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
                        policy=self.default_policy,
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
                policy=self.default_policy,
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
                        policy=VerificationPolicy(expected_sha=invalid),
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
                policy=self.default_policy,
            )
        self.assertIn("Invalid receipt commit_sha", str(ctx.exception))

    def test_commit_sha_mismatch_fails_closed(self) -> None:
        divergent_sha = "0000000000000000000000000000000000000000"
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                policy=VerificationPolicy(expected_sha=divergent_sha),
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
                policy=self.default_policy,
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
                        policy=self.default_policy,
                    )
                self.assertIn("not in allowed workflow events", str(ctx.exception))

    def test_workflow_name_mismatch_fails_closed(self) -> None:
        corrupted = dict(self.valid_receipt)
        corrupted["workflow_name"] = "Untrusted Workflow"
        self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                policy=self.default_policy,
            )
        self.assertIn("Workflow name mismatch", str(ctx.exception))

    def test_workflow_path_mismatch_fails_closed(self) -> None:
        corrupted = dict(self.valid_receipt)
        corrupted["workflow_path"] = ".github/workflows/malicious.yml"
        self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                policy=self.default_policy,
            )
        self.assertIn("Workflow path mismatch", str(ctx.exception))

    def test_artifact_name_latest_rejected_fails_closed(self) -> None:
        corrupted = dict(self.valid_receipt)
        corrupted["artifact_name"] = "latest"
        self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                policy=replace(self.default_policy, expected_artifact_name="latest"),
            )
        self.assertIn("Artifact name cannot be 'latest'", str(ctx.exception))

    def test_artifact_name_mismatch_fails_closed(self) -> None:
        corrupted = dict(self.valid_receipt)
        corrupted["artifact_name"] = "openapi-wrong-sha"
        self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                policy=self.default_policy,
            )
        self.assertIn("Artifact name mismatch", str(ctx.exception))

    def test_workflow_id_mismatch_fails_closed(self) -> None:
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                policy=replace(self.default_policy, expected_workflow_id=99999999),
            )
        self.assertIn("Workflow ID mismatch", str(ctx.exception))

    def test_run_url_not_https_fails_closed(self) -> None:
        corrupted = dict(self.valid_receipt)
        corrupted["run_url"] = f"http://github.com/yanpenalva/starter-kit-api/actions/runs/{SAMPLE_RUN_ID}"
        self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                policy=self.default_policy,
            )
        self.assertIn("Strict run_url mismatch", str(ctx.exception))

    def test_run_url_strange_host_fails_closed(self) -> None:
        corrupted = dict(self.valid_receipt)
        corrupted["run_url"] = f"https://evil.github.com/yanpenalva/starter-kit-api/actions/runs/{SAMPLE_RUN_ID}"
        self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                policy=self.default_policy,
            )
        self.assertIn("Strict run_url mismatch", str(ctx.exception))

    def test_run_url_with_query_params_fails_closed(self) -> None:
        corrupted = dict(self.valid_receipt)
        corrupted["run_url"] = f"{SAMPLE_RUN_URL}?token=evil"
        self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                policy=self.default_policy,
            )
        self.assertIn("Strict run_url mismatch", str(ctx.exception))

    def test_run_url_with_fragment_fails_closed(self) -> None:
        corrupted = dict(self.valid_receipt)
        corrupted["run_url"] = f"{SAMPLE_RUN_URL}#section"
        self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                policy=self.default_policy,
            )
        self.assertIn("Strict run_url mismatch", str(ctx.exception))

    def test_run_url_id_mismatch_confused_deputy_fails_closed(self) -> None:
        corrupted = dict(self.valid_receipt)
        corrupted["run_url"] = "https://github.com/yanpenalva/starter-kit-api/actions/runs/111111"
        self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                policy=self.default_policy,
            )
        self.assertIn("Strict run_url mismatch", str(ctx.exception))

    def test_strict_int_id_rejects_bool(self) -> None:
        for field in ("run_id", "artifact_id", "workflow_id"):
            corrupted = dict(self.valid_receipt)
            corrupted[field] = True
            self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
            with self.subTest(bool_field=field):
                with self.assertRaises(ProvenanceVerificationError) as ctx:
                    verify_provenance(
                        artifact_path=self.artifact_path,
                        receipt_path=self.receipt_path,
                        policy=self.default_policy,
                    )
                self.assertIn("must be a strict integer, got bool", str(ctx.exception))

    def test_strict_int_id_rejects_float(self) -> None:
        corrupted = dict(self.valid_receipt)
        corrupted["run_id"] = 123.45
        self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                policy=self.default_policy,
            )
        self.assertIn("must be a strict integer, got float", str(ctx.exception))

    def test_strict_int_id_rejects_numeric_string(self) -> None:
        corrupted = dict(self.valid_receipt)
        corrupted["run_id"] = "37215996931"
        self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                policy=self.default_policy,
            )
        self.assertIn("must be a strict integer, got str", str(ctx.exception))

    def test_strict_int_id_rejects_zero_or_negative(self) -> None:
        for val in (0, -10):
            corrupted = dict(self.valid_receipt)
            corrupted["run_id"] = val
            self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
            with self.subTest(invalid_id=val):
                with self.assertRaises(ProvenanceVerificationError) as ctx:
                    verify_provenance(
                        artifact_path=self.artifact_path,
                        receipt_path=self.receipt_path,
                        policy=self.default_policy,
                    )
                self.assertIn("must be a positive integer", str(ctx.exception))

    def test_receipt_file_exceeds_max_bytes_fails_closed(self) -> None:
        oversized = self.root / "oversized-receipt.json"
        oversized.write_bytes(b" " * (MAX_RECEIPT_BYTES + 10))
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=oversized,
                policy=self.default_policy,
            )
        self.assertIn("exceeds nominal limit", str(ctx.exception))

    def test_artifact_file_exceeds_max_bytes_fails_closed(self) -> None:
        oversized = self.root / "oversized-artifact.json"
        oversized.write_bytes(b'{"openapi": "3.1.0"}')
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            read_bounded_bytes(oversized, 5, "Artifact")
        self.assertIn("exceeds nominal limit of 5 bytes", str(ctx.exception))

    def test_future_created_at_in_download_fresh_mode_fails_closed(self) -> None:
        future_dt = datetime.now(timezone.utc) + timedelta(minutes=10)
        corrupted = dict(self.valid_receipt)
        corrupted["created_at"] = future_dt.isoformat()
        self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                policy=self.default_policy,
                mode=MODE_DOWNLOAD_FRESH,
            )
        self.assertIn("is in the future beyond", str(ctx.exception))

    def test_expired_created_at_in_download_fresh_mode_fails_closed(self) -> None:
        old_dt = datetime.now(timezone.utc) - timedelta(days=95)
        corrupted = dict(self.valid_receipt)
        corrupted["created_at"] = old_dt.isoformat()
        self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                policy=self.default_policy,
                mode=MODE_DOWNLOAD_FRESH,
            )
        self.assertIn("Artifact expired", str(ctx.exception))

    def test_old_created_at_in_verify_committed_mode_passes(self) -> None:
        old_dt = datetime.now(timezone.utc) - timedelta(days=120)
        corrupted = dict(self.valid_receipt)
        corrupted["created_at"] = old_dt.isoformat()
        self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
        result = verify_provenance(
            artifact_path=self.artifact_path,
            receipt_path=self.receipt_path,
            policy=self.default_policy,
            mode=MODE_VERIFY_COMMITTED,
        )
        self.assertEqual(result["status"], "verified")

    def test_artifact_file_digest_divergence_fails_closed(self) -> None:
        self.artifact_path.write_bytes(b'{"tampered": true}')
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                policy=self.default_policy,
            )
        self.assertIn("Artifact file digest mismatch", str(ctx.exception))

    def test_invalid_archive_sha256_format_fails_closed(self) -> None:
        corrupted = dict(self.valid_receipt)
        corrupted["archive_sha256"] = "short-hash"
        self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                policy=self.default_policy,
            )
        self.assertIn("must be exact 64-character hexadecimal SHA256 digest", str(ctx.exception))

    def test_invalid_artifact_file_sha256_format_fails_closed(self) -> None:
        corrupted = dict(self.valid_receipt)
        corrupted["artifact_file_sha256"] = "short-hash"
        self.receipt_path.write_text(json.dumps(corrupted), encoding="utf-8")
        with self.assertRaises(ProvenanceVerificationError) as ctx:
            verify_provenance(
                artifact_path=self.artifact_path,
                receipt_path=self.receipt_path,
                policy=self.default_policy,
            )
        self.assertIn("must be exact 64-character hexadecimal SHA256 digest", str(ctx.exception))

    def test_build_receipt_helper(self) -> None:
        record = make_sample_record(self.artifact_file_sha256)
        receipt = build_receipt(record)
        self.assertEqual(receipt["workflow_name"], SAMPLE_WORKFLOW_NAME)
        self.assertEqual(receipt["workflow_path"], SAMPLE_WORKFLOW_PATH)
        self.assertEqual(receipt["workflow_id"], SAMPLE_WORKFLOW_ID)
        self.assertEqual(receipt["archive_sha256"], SAMPLE_ARCHIVE_SHA256)
        self.assertEqual(receipt["artifact_file_sha256"], self.artifact_file_sha256)

    def test_validate_receipt_identity_direct_call_passes(self) -> None:
        validate_receipt_identity(self.valid_receipt, self.default_policy)

    def test_frozen_policy_and_receipt_record_immutability(self) -> None:
        with self.assertRaises(FrozenInstanceError):
            self.default_policy.expected_sha = "0" * 40  # type: ignore[misc]
        with self.assertRaises(FrozenInstanceError):
            self.sample_record.workflow_name = "Mutated"  # type: ignore[misc]

    def test_cli_success_committed_mode(self) -> None:
        argv = [
            "--artifact-file", str(self.artifact_path),
            "--receipt-file", str(self.receipt_path),
            "--expected-sha", SAMPLE_SHA,
            "--mode", MODE_VERIFY_COMMITTED,
        ]
        with patch("sys.stdout", new=io.StringIO()) as fake_out:
            exit_code = run_cli(argv)
        self.assertEqual(exit_code, 0)
        self.assertIn("Artifact provenance verified", fake_out.getvalue())

    def test_cli_success_download_fresh_mode(self) -> None:
        recent_receipt = dict(self.valid_receipt)
        recent_receipt["created_at"] = datetime.now(timezone.utc).isoformat()
        self.receipt_path.write_text(json.dumps(recent_receipt), encoding="utf-8")

        argv = [
            "--artifact-file", str(self.artifact_path),
            "--receipt-file", str(self.receipt_path),
            "--expected-sha", SAMPLE_SHA,
            "--mode", MODE_DOWNLOAD_FRESH,
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
        self.assertIn("FAIL CLOSED: Provenance verification failed: Artifact file digest mismatch", fake_err.getvalue())

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

    def test_cli_failure_on_unexpected_error_exit_2(self) -> None:
        self.receipt_path.write_bytes(b"\xff\xff\xff")
        argv = [
            "--artifact-file", str(self.artifact_path),
            "--receipt-file", str(self.receipt_path),
            "--expected-sha", SAMPLE_SHA,
        ]
        with patch("sys.stderr", new=io.StringIO()) as fake_err:
            exit_code = run_cli(argv)
        self.assertEqual(exit_code, 2)
        self.assertIn("FAIL CLOSED: Unexpected error:", fake_err.getvalue())


class ContractLoadingAndCliProcessTests(unittest.TestCase):
    def setUp(self) -> None:
        self.temp_dir = tempfile.TemporaryDirectory()
        self.root = Path(self.temp_dir.name)
        self.artifact_path = self.root / "openapi.json"
        self.artifact_content = b'{"openapi": "3.1.0", "info": {"title": "Test", "version": "1.0"}}'
        self.artifact_path.write_bytes(self.artifact_content)
        self.artifact_file_sha256 = hashlib.sha256(self.artifact_content).hexdigest()
        self.receipt_path = self.root / "provenance.json"
        self.valid_receipt = build_receipt(make_sample_record(self.artifact_file_sha256))
        self.receipt_path.write_text(json.dumps(self.valid_receipt), encoding="utf-8")
        self.repo_root = Path(__file__).resolve().parents[2]
        self.vca_script = self.repo_root / "scripts" / "verify_contract_artifact.py"
        self.cli_script = self.repo_root / "scripts" / "contract_cli.py"

    def tearDown(self) -> None:
        self.temp_dir.cleanup()

    def test_direct_script_process_exit_0_across_unrelated_cwd(self) -> None:
        with tempfile.TemporaryDirectory() as unrelated_cwd:
            cmd = [
                sys.executable,
                str(self.vca_script),
                "--artifact-file", str(self.artifact_path),
                "--receipt-file", str(self.receipt_path),
                "--expected-sha", SAMPLE_SHA,
                "--mode", MODE_VERIFY_COMMITTED,
            ]
            res = subprocess.run(cmd, capture_output=True, text=True, cwd=unrelated_cwd)
            self.assertEqual(res.returncode, 0)
            self.assertIn("SUCCESS: Artifact provenance verified", res.stdout)

    def test_direct_script_process_exit_1_on_verification_failure(self) -> None:
        with tempfile.TemporaryDirectory() as unrelated_cwd:
            cmd = [
                sys.executable,
                str(self.vca_script),
                "--artifact-file", str(self.artifact_path),
                "--receipt-file", str(self.receipt_path),
                "--expected-sha", "0" * 40,
                "--mode", MODE_VERIFY_COMMITTED,
            ]
            res = subprocess.run(cmd, capture_output=True, text=True, cwd=unrelated_cwd)
            self.assertEqual(res.returncode, 1)
            self.assertIn("FAIL CLOSED: Provenance verification failed:", res.stderr)

    def test_direct_script_process_exit_2_on_invalid_arguments(self) -> None:
        with tempfile.TemporaryDirectory() as unrelated_cwd:
            cmd = [
                sys.executable,
                str(self.vca_script),
                "--artifact-file", str(self.artifact_path),
                "--receipt-file", str(self.receipt_path),
                "--expected-sha", SAMPLE_SHA,
                "--unrecognized-flag",
            ]
            res = subprocess.run(cmd, capture_output=True, text=True, cwd=unrelated_cwd)
            self.assertEqual(res.returncode, 2)
            self.assertIn("unrecognized arguments", res.stderr)

    def test_module_execution_m_flag_exit_0(self) -> None:
        cmd = [
            sys.executable,
            "-m",
            "scripts.verify_contract_artifact",
            "--artifact-file", str(self.artifact_path),
            "--receipt-file", str(self.receipt_path),
            "--expected-sha", SAMPLE_SHA,
            "--mode", MODE_VERIFY_COMMITTED,
        ]
        res = subprocess.run(cmd, capture_output=True, text=True, cwd=str(self.repo_root))
        self.assertEqual(res.returncode, 0)
        self.assertIn("SUCCESS: Artifact provenance verified", res.stdout)

    def test_contract_cli_script_process_exit_0(self) -> None:
        with tempfile.TemporaryDirectory() as unrelated_cwd:
            cmd = [
                sys.executable,
                str(self.cli_script),
                "--artifact-file", str(self.artifact_path),
                "--receipt-file", str(self.receipt_path),
                "--expected-sha", SAMPLE_SHA,
                "--mode", MODE_VERIFY_COMMITTED,
            ]
            res = subprocess.run(cmd, capture_output=True, text=True, cwd=unrelated_cwd)
            self.assertEqual(res.returncode, 0)
            self.assertIn("SUCCESS: Artifact provenance verified", res.stdout)

    def test_direct_sibling_imports_and_loading_order(self) -> None:
        from scripts.contract_receipt import build_receipt as br, ReceiptRecord as rr
        from scripts.contract_cli import run_cli as rc, parse_args as pa, main as mn
        from scripts.contract_core import VerificationPolicy as vp
        self.assertTrue(callable(br))
        self.assertTrue(callable(rc))
        self.assertTrue(callable(pa))
        self.assertTrue(callable(mn))
        self.assertTrue(isinstance(rr, type))
        self.assertTrue(isinstance(vp, type))

        code_order_receipt_first = (
            "import sys; "
            "import scripts.contract_receipt; "
            "import scripts.verify_contract_artifact; "
            "import scripts.contract_cli; "
            "print('ORDER_OK')"
        )
        res1 = subprocess.run(
            [sys.executable, "-c", code_order_receipt_first],
            capture_output=True,
            text=True,
            cwd=str(self.repo_root),
        )
        self.assertEqual(res1.returncode, 0)
        self.assertIn("ORDER_OK", res1.stdout)

        code_order_cli_first = (
            "import sys; "
            "import scripts.contract_cli; "
            "import scripts.contract_receipt; "
            "import scripts.verify_contract_artifact; "
            "print('ORDER_OK')"
        )
        res2 = subprocess.run(
            [sys.executable, "-c", code_order_cli_first],
            capture_output=True,
            text=True,
            cwd=str(self.repo_root),
        )
        self.assertEqual(res2.returncode, 0)
        self.assertIn("ORDER_OK", res2.stdout)

    def test_direct_script_process_exit_2_on_unexpected_error(self) -> None:
        with tempfile.TemporaryDirectory() as unrelated_cwd:
            bad_receipt = Path(unrelated_cwd) / "bad_receipt.json"
            bad_receipt.write_bytes(b"\xff\xff\xff")
            cmd = [
                sys.executable,
                str(self.vca_script),
                "--artifact-file", str(self.artifact_path),
                "--receipt-file", str(bad_receipt),
                "--expected-sha", SAMPLE_SHA,
            ]
            res = subprocess.run(cmd, capture_output=True, text=True, cwd=unrelated_cwd)
            self.assertEqual(res.returncode, 2)
            self.assertIn("FAIL CLOSED: Unexpected error:", res.stderr)

    def test_spec_from_file_location_isolated_subprocess(self) -> None:
        with tempfile.TemporaryDirectory() as isolated_cwd:
            code = (
                "import importlib.util, sys\n"
                f"for p in list(sys.path):\n"
                f"    if '{self.repo_root}' in p:\n"
                f"        sys.path.remove(p)\n"
                f"spec = importlib.util.spec_from_file_location('isolated_verifier', '{self.vca_script}')\n"
                "mod = importlib.util.module_from_spec(spec)\n"
                "spec.loader.exec_module(mod)\n"
                "assert hasattr(mod, 'verify_provenance')\n"
                "assert hasattr(mod, 'build_receipt')\n"
                "assert hasattr(mod, 'run_cli')\n"
                "assert hasattr(mod, 'ProvenanceVerificationError')\n"
                "assert hasattr(mod, 'VerificationPolicy')\n"
                "assert hasattr(mod, 'ReceiptRecord')\n"
                "print('ISOLATED_LOAD_OK')\n"
            )
            res = subprocess.run([sys.executable, "-c", code], capture_output=True, text=True, cwd=isolated_cwd)
            self.assertEqual(res.returncode, 0)
            self.assertIn("ISOLATED_LOAD_OK", res.stdout)

    def test_no_double_module_execution_and_identity(self) -> None:
        with tempfile.TemporaryDirectory() as probe_cwd:
            code = (
                "import subprocess, sys\n"
                f"res = subprocess.run([sys.executable, '{self.vca_script}', '--expected-sha', '0'*40, "
                f"'--artifact-file', '{self.artifact_path}', '--receipt-file', '{self.receipt_path}'], "
                "capture_output=True, text=True)\n"
                "assert res.returncode == 1, f'Expected returncode 1, got {res.returncode}'\n"
                "assert 'FAIL CLOSED: Provenance verification failed:' in res.stderr\n"
                "print('IDENTITY_AND_SINGLE_EXEC_OK')\n"
            )
            res = subprocess.run([sys.executable, "-c", code], capture_output=True, text=True, cwd=probe_cwd)
            self.assertEqual(res.returncode, 0)
            self.assertIn("IDENTITY_AND_SINGLE_EXEC_OK", res.stdout)


if __name__ == "__main__":
    unittest.main()
