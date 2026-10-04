"""Offline tests for safe ZIP extraction and deterministic export helpers.

No real GitHub calls, no network, no secrets. Fixtures are built in-memory
under the repo-local .cache directory when temporary files are needed.
"""

from __future__ import annotations

import io
import json
import unittest
import zipfile
from pathlib import Path

from scripts.contract_extract import (
    ArchiveSafetyError,
    ExtractionPolicy,
    normalize_entry_name,
    reject_remote_refs,
    safe_extract_archive,
)
from scripts.contract_export import validate_openapi_document, verify_deterministic
from scripts.contract_fetch import FetchPolicy, validate_artifact_metadata, validate_run_metadata


def make_zip(entries: dict[str, bytes]) -> bytes:
    """Build an in-memory ZIP from name->bytes mapping."""
    buffer = io.BytesIO()
    with zipfile.ZipFile(buffer, 'w', zipfile.ZIP_DEFLATED) as handle:
        for name, payload in entries.items():
            handle.writestr(name, payload)
    return buffer.getvalue()


def minimal_spec() -> bytes:
    """Minimal valid OpenAPI 3.1 payload without remote refs."""
    document = {
        'openapi': '3.1.0',
        'info': {'title': 'Starter Kit API', 'version': '1.0.0'},
        'paths': {'/login': {'post': {'responses': {'204': {'description': 'No content'}}}}},
    }
    return json.dumps(document).encode('utf-8')


class SafeExtractTests(unittest.TestCase):
    def test_valid_root_file_extracts(self) -> None:
        """Valid archive with expected root file passes."""
        payload = make_zip({'openapi.json': minimal_spec()})
        result = safe_extract_archive(payload)
        self.assertEqual(result.files, ('openapi.json',))
        self.assertGreater(result.total_bytes, 0)
        self.assertEqual(len(result.file_sha256), 64)

    def test_absolute_path_rejected(self) -> None:
        """Absolute entry paths fail closed."""
        payload = make_zip({'/openapi.json': minimal_spec()})
        with self.assertRaises(ArchiveSafetyError):
            safe_extract_archive(payload)

    def test_traversal_rejected(self) -> None:
        """Parent traversal entries fail closed."""
        payload = make_zip({'../openapi.json': minimal_spec()})
        with self.assertRaises(ArchiveSafetyError):
            safe_extract_archive(payload)

    def test_nested_path_rejected(self) -> None:
        """Nested paths fail closed (root files only)."""
        payload = make_zip({'contracts/openapi.json': minimal_spec()})
        with self.assertRaises(ArchiveSafetyError):
            safe_extract_archive(payload)

    def test_unexpected_file_rejected(self) -> None:
        """Files outside the allowlist fail closed."""
        payload = make_zip({'evil.json': b'{}'})
        with self.assertRaises(ArchiveSafetyError):
            safe_extract_archive(payload)

    def test_missing_expected_file_rejected(self) -> None:
        """Archives missing the expected spec fail closed."""
        policy = ExtractionPolicy(expected_files=frozenset({'openapi.json', 'extra.json'}))
        payload = make_zip({'openapi.json': minimal_spec()})
        with self.assertRaises(ArchiveSafetyError):
            safe_extract_archive(payload, policy)

    def test_duplicate_entry_rejected(self) -> None:
        """Duplicate normalized names fail closed."""
        payload = make_zip({'openapi.json': minimal_spec(), './openapi.json': minimal_spec()})
        with self.assertRaises(ArchiveSafetyError):
            safe_extract_archive(payload)

    def test_remote_ref_rejected(self) -> None:
        """Remote $ref URLs in the contract fail closed (SSRF guard)."""
        evil = b'{"openapi":"3.1.0","paths":{},"remote":{"$ref":"https://evil.example/spec.json"}}'
        with self.assertRaises(ArchiveSafetyError):
            reject_remote_refs(evil, 'openapi.json')

    def test_normalize_rejects_backslash_traversal(self) -> None:
        """Backslash traversal normalizes to traversal and fails."""
        with self.assertRaises(ArchiveSafetyError):
            normalize_entry_name('..\\openapi.json')


class ExportHelperTests(unittest.TestCase):
    def test_deterministic_identical_passes(self) -> None:
        """Identical passes verify."""
        cache = Path('.cache')
        cache.mkdir(parents=True, exist_ok=True)
        first = cache / 'test-det-a.json'
        second = cache / 'test-det-b.json'
        first.write_bytes(b'{"a":1}')
        second.write_bytes(b'{"a":1}')
        payload = verify_deterministic(first, second)
        self.assertEqual(payload, b'{"a":1}')

    def test_deterministic_divergence_fails(self) -> None:
        """Divergent passes fail closed."""
        from scripts.contract_export import ExportError

        cache = Path('.cache')
        cache.mkdir(parents=True, exist_ok=True)
        first = cache / 'test-det-c.json'
        second = cache / 'test-det-d.json'
        first.write_bytes(b'{"a":1}')
        second.write_bytes(b'{"a":2}')
        with self.assertRaises(ExportError):
            verify_deterministic(first, second)

    def test_validate_openapi_requires_310(self) -> None:
        """Non-3.1 documents fail validation."""
        from scripts.contract_export import ExportError

        with self.assertRaises(ExportError):
            validate_openapi_document(b'{"openapi":"3.0.0","info":{"version":"1"},"paths":{}}')

    def test_validate_openapi_accepts_minimal(self) -> None:
        """Minimal 3.1 document passes."""
        validate_openapi_document(minimal_spec())


class FetchPolicyTests(unittest.TestCase):
    def make_policy(self) -> FetchPolicy:
        """Build a trusted fetch policy fixture."""
        return FetchPolicy(
            owner='yanpenalva',
            repo='starter-kit-api',
            expected_sha='a' * 40,
            expected_workflow_path='.github/workflows/openapi.yml',
            expected_workflow_name='OpenAPI Contract',
            expected_workflow_id=123,
            expected_artifact_name='openapi-' + 'a' * 40,
            run_id=456,
            artifact_id=789,
        )

    def test_valid_run_metadata_passes(self) -> None:
        """Matching run metadata passes."""
        run = {
            'status': 'completed',
            'conclusion': 'success',
            'head_sha': 'a' * 40,
            'head_branch': 'main',
            'event': 'workflow_dispatch',
            'path': '.github/workflows/openapi.yml',
            'name': 'OpenAPI Contract',
            'workflow_id': 123,
            'id': 456,
        }
        validate_run_metadata(run, self.make_policy())

    def test_wrong_sha_fails(self) -> None:
        """Mismatched head SHA fails closed."""
        from scripts.contract_fetch import FetchError

        run = {
            'status': 'completed',
            'conclusion': 'success',
            'head_sha': 'b' * 40,
            'head_branch': 'main',
            'event': 'workflow_dispatch',
            'path': '.github/workflows/openapi.yml',
            'name': 'OpenAPI Contract',
            'workflow_id': 123,
            'id': 456,
        }
        with self.assertRaises(FetchError):
            validate_run_metadata(run, self.make_policy())

    def test_failed_conclusion_fails(self) -> None:
        """Non-success conclusions fail closed."""
        from scripts.contract_fetch import FetchError

        run = {
            'status': 'completed',
            'conclusion': 'failure',
            'head_sha': 'a' * 40,
            'head_branch': 'main',
            'event': 'workflow_dispatch',
            'path': '.github/workflows/openapi.yml',
            'name': 'OpenAPI Contract',
            'workflow_id': 123,
            'id': 456,
        }
        with self.assertRaises(FetchError):
            validate_run_metadata(run, self.make_policy())

    def test_expired_artifact_fails(self) -> None:
        """Expired artifacts fail closed."""
        from scripts.contract_fetch import FetchError

        artifact = {'name': 'openapi-' + 'a' * 40, 'id': 789, 'expired': True}
        with self.assertRaises(FetchError):
            validate_artifact_metadata(artifact, self.make_policy())

    def test_valid_artifact_passes(self) -> None:
        """Matching artifact metadata passes."""
        artifact = {'name': 'openapi-' + 'a' * 40, 'id': 789, 'expired': False}
        validate_artifact_metadata(artifact, self.make_policy())


if __name__ == '__main__':
    unittest.main()
