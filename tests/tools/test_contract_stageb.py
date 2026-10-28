"""Offline tests for safe ZIP extraction and deterministic export helpers.

No real GitHub calls, no network, no secrets. Fixtures are built in-memory
under the repo-local .cache directory when temporary files are needed.
"""

from __future__ import annotations

import hashlib
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
        'openapi': '3.1.2',
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


class CompatibilityCheckerTests(unittest.TestCase):
    def test_resolve_pinned_checker_passes_on_pinned(self) -> None:
        """Pinned checker resolves matching tool and version."""
        from scripts.contract_compat import resolve_pinned_checker, PINNED_VERSION

        checker = resolve_pinned_checker()
        self.assertEqual(checker.version, PINNED_VERSION)

    def test_resolve_pinned_checker_rejects_missing_tool(self) -> None:
        """Missing tool fails closed."""
        from scripts.contract_compat import CompatibilityError, resolve_pinned_checker

        with self.assertRaises(CompatibilityError):
            resolve_pinned_checker('non_existent_tool_binary_xyz')

    def test_resolve_pinned_checker_rejects_wrong_version(self) -> None:
        """Version mismatch fails closed."""
        from scripts.contract_compat import CompatibilityError, resolve_pinned_checker

        with self.assertRaises(CompatibilityError):
            resolve_pinned_checker(expected_version='99.99.99')

    def test_verify_commit_exists_accepts_main_baseline(self) -> None:
        """Immutable main baseline commit exists."""
        from scripts.contract_compat import IMMUTABLE_BASE_MAIN, verify_commit_exists

        verify_commit_exists(IMMUTABLE_BASE_MAIN)

    def test_verify_commit_exists_rejects_invalid_hex(self) -> None:
        """Non-40-hex commit format fails closed."""
        from scripts.contract_compat import CompatibilityError, verify_commit_exists

        with self.assertRaises(CompatibilityError):
            verify_commit_exists('not-a-valid-sha')

    def test_verify_commit_exists_rejects_nonexistent_sha(self) -> None:
        """Non-existent commit SHA fails closed."""
        from scripts.contract_compat import CompatibilityError, verify_commit_exists

        with self.assertRaises(CompatibilityError):
            verify_commit_exists('0' * 40)

    def test_is_contract_absent_in_commit_proves_absent_on_main(self) -> None:
        """Contract is proven absent in baseline main commit."""
        from scripts.contract_compat import (
            DEFAULT_CONTRACT_PATH,
            IMMUTABLE_BASE_MAIN,
            is_contract_absent_in_commit,
        )

        absent = is_contract_absent_in_commit(IMMUTABLE_BASE_MAIN, DEFAULT_CONTRACT_PATH)
        self.assertTrue(absent)

    def test_operational_fixtures_prove_negatives_and_positive(self) -> None:
        """Operational fixtures prove checker detects breaking changes and accepts compatible diffs."""
        from scripts.contract_compat import resolve_pinned_checker, verify_operational_fixtures

        checker = resolve_pinned_checker()
        verify_operational_fixtures(checker)

    def test_check_compatibility_bootstrap_passes_on_main(self) -> None:
        """Bootstrap mode succeeds on absent base contract with candidate spec."""
        from scripts.contract_compat import (
            DEFAULT_CONTRACT_PATH,
            IMMUTABLE_BASE_MAIN,
            check_compatibility,
            resolve_pinned_checker,
        )

        cache = Path('.cache')
        cache.mkdir(parents=True, exist_ok=True)
        cand = cache / 'test_candidate.json'
        cand.write_bytes(minimal_spec())
        checker = resolve_pinned_checker()
        msg = check_compatibility(IMMUTABLE_BASE_MAIN, DEFAULT_CONTRACT_PATH, cand, checker)
        self.assertIn('BOOTSTRAP VÁLIDO', msg)

    def test_check_compatibility_fails_on_missing_candidate(self) -> None:
        """Missing candidate file fails closed."""
        from scripts.contract_compat import (
            CompatibilityError,
            DEFAULT_CONTRACT_PATH,
            IMMUTABLE_BASE_MAIN,
            check_compatibility,
            resolve_pinned_checker,
        )

        checker = resolve_pinned_checker()
        with self.assertRaises(CompatibilityError):
            check_compatibility(IMMUTABLE_BASE_MAIN, DEFAULT_CONTRACT_PATH, Path('non_existent.json'), checker)


class AcceptedBreakingChangesTests(unittest.TestCase):
    def test_only_exactly_listed_finding_is_accepted(self) -> None:
        """An exception covers one exact endpoint, rule, and change description."""
        from scripts.contract_compat import (
            AcceptedBreakingChange,
            filter_accepted_breaking_findings,
        )

        accepted = AcceptedBreakingChange(
            id='BRK-2026-10-13-001',
            endpoint='POST /api/v1/users',
            change='Require roles when creating an administrative user',
            justification='Product decision: roles are mandatory for user creation.',
            pr='https://github.com/yanpenalva/starter-kit-api/pull/94',
            oasdiff_id='new-required-request-property',
            oasdiff_text='added the new required request property `roles`',
        )
        listed = {
            'id': 'new-required-request-property',
            'text': 'added the new required request property `roles`',
            'level': 3,
            'operation': 'POST',
            'path': '/api/v1/users',
        }
        other_endpoint = {**listed, 'path': '/api/v1/other'}
        other_change = {**listed, 'text': 'added the new required request property `email`'}

        allowed, rejected = filter_accepted_breaking_findings(
            [listed, listed, other_endpoint, other_change], [accepted]
        )

        self.assertEqual([accepted], allowed)
        self.assertEqual([listed, other_endpoint, other_change], rejected)

    def test_allowlist_loader_rejects_missing_audit_fields(self) -> None:
        """A malformed exception file fails closed instead of weakening the gate."""
        import tempfile

        from scripts.contract_compat import CompatibilityError, load_accepted_breaking_changes

        with tempfile.TemporaryDirectory() as temp_dir:
            allowlist_path = Path(temp_dir) / 'accepted-breaking-changes.json'
            allowlist_path.write_text(
                json.dumps({'schema_version': 1, 'accepted_breaking_changes': [{'id': 'BRK-1'}]}),
                encoding='utf-8',
            )

            with self.assertRaises(CompatibilityError):
                load_accepted_breaking_changes(allowlist_path)

    def test_oasdiff_parser_rejects_unknown_severity(self) -> None:
        """A checker output with a new or malformed level cannot be allowlisted."""
        from scripts.contract_compat import CompatibilityError, parse_breaking_findings

        finding = {
            'id': 'new-required-request-property',
            'text': 'added the new required request property `roles`',
            'level': 4,
            'operation': 'POST',
            'path': '/api/v1/users',
        }
        with self.assertRaises(CompatibilityError):
            parse_breaking_findings(json.dumps([finding]))


class ToolIntegrityTests(unittest.TestCase):
    def test_verify_asset_digest_passes_on_official_sha(self) -> None:
        """Valid digest passes integrity verification."""
        from scripts.install_oasdiff import verify_asset_digest

        cache = Path('.cache')
        cache.mkdir(parents=True, exist_ok=True)
        sample = cache / 'test_asset.tar.gz'
        sample.write_bytes(b'test-content-for-sha')
        expected = hashlib.sha256(b'test-content-for-sha').hexdigest()
        verify_asset_digest(sample, expected)

    def test_verify_asset_digest_rejects_mismatch(self) -> None:
        """Tampered or mismatched digest fails closed with IntegrityError."""
        from scripts.install_oasdiff import IntegrityError, verify_asset_digest

        cache = Path('.cache')
        cache.mkdir(parents=True, exist_ok=True)
        sample = cache / 'test_tampered.tar.gz'
        sample.write_bytes(b'tampered-content')
        with self.assertRaises(IntegrityError):
            verify_asset_digest(sample, '0' * 64)

    def test_pinned_hashes_match_verified_official_values(self) -> None:
        """Pinned archive and binary digests equal the release-verified values."""
        from scripts.install_oasdiff import OFFICIAL_SHA256, PINNED_BINARY_SHA256

        self.assertEqual(
            OFFICIAL_SHA256,
            '43a4e328e2d13ba1552d760aa68d2485c75c5621f309f6ff64ae895188345247',
        )
        self.assertEqual(
            PINNED_BINARY_SHA256,
            'fa65aae43c867e9f79da9cb121d3c1a113637883afe014c0680fd5b50696f416',
        )

    def test_safe_extract_rejects_symlink(self) -> None:
        """Tar archive containing symlinks fails closed."""
        from scripts.install_oasdiff import IntegrityError, safe_extract_binary
        import tarfile

        cache = Path('.cache')
        cache.mkdir(parents=True, exist_ok=True)
        tar_path = cache / 'symlink_test.tar.gz'
        with tarfile.open(tar_path, 'w:gz') as tar:
            info = tarfile.TarInfo(name='oasdiff')
            info.type = tarfile.SYMTYPE
            info.linkname = '/etc/passwd'
            tar.addfile(info)

        with self.assertRaises(IntegrityError):
            safe_extract_binary(tar_path, cache / 'out_bin')

    def test_safe_extract_rejects_missing_member(self) -> None:
        """Tarball without the expected 'oasdiff' member fails closed."""
        from scripts.install_oasdiff import IntegrityError, safe_extract_binary
        import io
        import tarfile

        cache = Path('.cache')
        cache.mkdir(parents=True, exist_ok=True)
        tar_path = cache / 'missing_member.tar.gz'
        with tarfile.open(tar_path, 'w:gz') as tar:
            info = tarfile.TarInfo(name='LICENSE')
            payload = b'MIT'
            info.size = len(payload)
            tar.addfile(info, io.BytesIO(payload))

        with self.assertRaises(IntegrityError):
            safe_extract_binary(tar_path, cache / 'out_missing')

    def test_safe_extract_rejects_oversized_member(self) -> None:
        """Member exceeding the size bound fails closed before extraction."""
        from unittest.mock import patch

        from scripts.install_oasdiff import IntegrityError, safe_extract_binary
        import io
        import tarfile

        cache = Path('.cache')
        cache.mkdir(parents=True, exist_ok=True)
        tar_path = cache / 'oversized.tar.gz'
        with tarfile.open(tar_path, 'w:gz') as tar:
            info = tarfile.TarInfo(name='oasdiff')
            info.size = 9
            tar.addfile(info, io.BytesIO(b'x' * 9))

        with patch('scripts.install_oasdiff.MAX_BINARY_BYTES', 8):
            with self.assertRaises(IntegrityError):
                safe_extract_binary(tar_path, cache / 'out_oversized')

    def test_safe_extract_rejects_non_regular_member(self) -> None:
        """Directory member named 'oasdiff' fails closed."""
        from scripts.install_oasdiff import IntegrityError, safe_extract_binary
        import tarfile

        cache = Path('.cache')
        cache.mkdir(parents=True, exist_ok=True)
        tar_path = cache / 'dir_member.tar.gz'
        with tarfile.open(tar_path, 'w:gz') as tar:
            info = tarfile.TarInfo(name='oasdiff')
            info.type = tarfile.DIRTYPE
            tar.addfile(info)

        with self.assertRaises(IntegrityError):
            safe_extract_binary(tar_path, cache / 'out_dir')

    def _fake_binary_tarball(self, tar_path: Path, binary_payload: bytes) -> str:
        """Build a tar.gz with an executable fake oasdiff and return its archive sha256."""
        import io
        import tarfile

        with tarfile.open(tar_path, 'w:gz') as tar:
            info = tarfile.TarInfo(name='oasdiff')
            info.size = len(binary_payload)
            info.mode = 0o755
            tar.addfile(info, io.BytesIO(binary_payload))
        return hashlib.sha256(tar_path.read_bytes()).hexdigest()

    def test_install_and_verify_happy_path_with_fake_binary(self) -> None:
        """Full pipeline passes on a matching archive+binary digest and pinned version."""
        import os
        import tempfile

        from scripts.install_oasdiff import install_and_verify

        fake_binary = (
            '#!/bin/sh\necho "oasdiff version 1.33.0"\n'
        ).encode('utf-8')
        with tempfile.TemporaryDirectory() as temp_dir:
            temp_path = Path(temp_dir)
            archive = temp_path / 'fake_oasdiff.tar.gz'
            archive_sha = self._fake_binary_tarball(archive, fake_binary)
            result = install_and_verify(
                target_dir=temp_path / 'bin',
                source_archive=archive,
                expected_sha256=archive_sha,
                expected_binary_sha256=hashlib.sha256(fake_binary).hexdigest(),
            )
            self.assertEqual(result.version, '1.33.0')
            self.assertTrue(os.access(result.binary_path, os.X_OK))

    def test_install_and_verify_rejects_binary_digest_mismatch(self) -> None:
        """Extracted binary diverging from the pinned binary digest fails before execution."""
        import tempfile

        from scripts.install_oasdiff import IntegrityError, install_and_verify

        fake_binary = (
            '#!/bin/sh\necho "oasdiff version 1.33.0"\n'
        ).encode('utf-8')
        with tempfile.TemporaryDirectory() as temp_dir:
            temp_path = Path(temp_dir)
            archive = temp_path / 'fake_oasdiff.tar.gz'
            archive_sha = self._fake_binary_tarball(archive, fake_binary)
            with self.assertRaises(IntegrityError) as ctx:
                install_and_verify(
                    target_dir=temp_path / 'bin',
                    source_archive=archive,
                    expected_sha256=archive_sha,
                    expected_binary_sha256='0' * 64,
                )
            self.assertIn('binário extraído', str(ctx.exception))


class BaselineResolverTests(unittest.TestCase):
    def test_validate_full_sha_accepts_valid_40_hex(self) -> None:
        """Valid 40-character lowercase hex SHA is accepted."""
        from scripts.resolve_contract_baseline import validate_full_sha

        valid = 'a' * 40
        self.assertEqual(validate_full_sha(valid), valid)

    def test_validate_full_sha_rejects_zero_sha(self) -> None:
        """Zero SHA fails closed."""
        from scripts.resolve_contract_baseline import BaselineResolutionError, validate_full_sha

        with self.assertRaises(BaselineResolutionError):
            validate_full_sha('0' * 40)

    def test_validate_full_sha_rejects_malformed(self) -> None:
        """Non-40 or non-hex string fails closed."""
        from scripts.resolve_contract_baseline import BaselineResolutionError, validate_full_sha

        with self.assertRaises(BaselineResolutionError):
            validate_full_sha('invalid-sha')
        with self.assertRaises(BaselineResolutionError):
            validate_full_sha('a' * 39)
        with self.assertRaises(BaselineResolutionError):
            validate_full_sha('a' * 41)
        with self.assertRaises(BaselineResolutionError):
            validate_full_sha('g' * 40)

    def test_extract_base_sha_from_pull_request_event(self) -> None:
        """pull_request event extracts pull_request.base.sha strictly (never head)."""
        from scripts.resolve_contract_baseline import extract_base_sha

        base_sha = '1' * 40
        head_sha = '2' * 40
        payload = {'pull_request': {'base': {'sha': base_sha}, 'head': {'sha': head_sha}}}
        extracted = extract_base_sha('pull_request', payload)
        self.assertEqual(extracted, base_sha)

    def test_extract_base_sha_from_push_event_on_main(self) -> None:
        """push event on refs/heads/main extracts 'before' SHA."""
        from scripts.resolve_contract_baseline import extract_base_sha

        before_sha = '3' * 40
        payload = {'before': before_sha, 'ref': 'refs/heads/main'}
        extracted = extract_base_sha('push', payload, 'refs/heads/main')
        self.assertEqual(extracted, before_sha)

    def test_extract_base_sha_rejects_push_on_non_main(self) -> None:
        """push event on non-main branch fails closed."""
        from scripts.resolve_contract_baseline import BaselineResolutionError, extract_base_sha

        payload = {'before': '3' * 40, 'ref': 'refs/heads/feature'}
        with self.assertRaises(BaselineResolutionError):
            extract_base_sha('push', payload, 'refs/heads/feature')

    def test_extract_base_sha_rejects_unknown_event(self) -> None:
        """Events outside pull_request and push fail closed."""
        from scripts.resolve_contract_baseline import BaselineResolutionError, extract_base_sha

        with self.assertRaises(BaselineResolutionError):
            extract_base_sha('workflow_dispatch', {})

    def test_verify_not_current_head_rejects_same_head(self) -> None:
        """Current HEAD SHA cannot be used as baseline."""
        from scripts.resolve_contract_baseline import (
            BaselineResolutionError,
            get_current_head_sha,
            verify_not_current_head,
        )

        current_head = get_current_head_sha()
        with self.assertRaises(BaselineResolutionError):
            verify_not_current_head(current_head)


class RealGitLifecycleCompatibilityTests(unittest.TestCase):
    """Real Git lifecycle fixtures: init, commit, break detection, fail-closed paths."""

    def setUp(self) -> None:
        import subprocess
        import tempfile

        self.temp_dir = tempfile.TemporaryDirectory()
        self.repo_dir = Path(self.temp_dir.name)
        subprocess.run(['git', 'init', '-b', 'main', str(self.repo_dir)], check=True, capture_output=True)
        subprocess.run(['git', 'config', 'user.name', 'CI Tester'], cwd=self.repo_dir, check=True)
        subprocess.run(['git', 'config', 'user.email', 'ci@example.com'], cwd=self.repo_dir, check=True)

    def tearDown(self) -> None:
        self.temp_dir.cleanup()

    def _git(self, *argv: str) -> str:
        """Run git in the fixture repo and return stdout."""
        import subprocess

        proc = subprocess.run(
            ['git', *argv], cwd=self.repo_dir, capture_output=True, text=True, check=True
        )
        return proc.stdout.strip()

    def _commit_file(self, relative_path: str, content: bytes, message: str) -> str:
        """Write a file, commit it, and return the new full commit SHA."""
        target = self.repo_dir / relative_path
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(content)
        self._git('add', relative_path)
        self._git('commit', '-m', message)
        return self._git('rev-parse', 'HEAD')

    def _finding_lines(self, error_message: str) -> list[str]:
        """Structured oasdiff findings included in an unlisted compatibility error."""
        marker = 'Quebra de compatibilidade não listada contra baseline '
        summary = error_message.partition(marker)[2]
        _, _, payload = summary.partition(': ')
        return json.loads(payload)

    def test_real_git_lifecycle_accepts_only_listed_breaking_change(self) -> None:
        """One listed required request field passes; another breaking change remains blocked."""
        from scripts.contract_compat import (
            DEFAULT_CONTRACT_PATH,
            CompatibilityError,
            check_compatibility,
            resolve_pinned_checker,
        )

        base_doc = {
            'openapi': '3.1.0',
            'info': {'title': 'API', 'version': '1.0.0'},
            'paths': {
                '/api/v1/users': {
                    'post': {
                        'operationId': 'users.store',
                        'requestBody': {
                            'required': True,
                            'content': {
                                'application/json': {
                                    'schema': {
                                        'type': 'object',
                                        'properties': {'name': {'type': 'string'}},
                                        'required': ['name'],
                                    }
                                }
                            },
                        },
                        'responses': {'201': {'description': 'Created'}},
                    }
                }
            },
        }
        base_commit = self._commit_file(
            DEFAULT_CONTRACT_PATH,
            json.dumps(base_doc).encode('utf-8'),
            'feat: add users endpoint contract',
        )
        allowlist_path = self.repo_dir / 'accepted-breaking-changes.json'
        allowlist_path.write_text(
            json.dumps(
                {
                    'schema_version': 1,
                    'accepted_breaking_changes': [
                        {
                            'id': 'BRK-2026-10-13-001',
                            'endpoint': 'POST /api/v1/users',
                            'change': 'Require roles when creating an administrative user',
                            'justification': 'Product decision: user creation requires an explicit role.',
                            'pr': 'https://github.com/yanpenalva/starter-kit-api/pull/94',
                            'oasdiff_id': 'new-required-request-property',
                            'oasdiff_text': 'added the new required request property `roles`',
                        }
                    ],
                }
            ),
            encoding='utf-8',
        )

        allowed_doc = json.loads(json.dumps(base_doc))
        schema = allowed_doc['paths']['/api/v1/users']['post']['requestBody']['content'][
            'application/json'
        ]['schema']
        schema['properties']['roles'] = {'type': 'array', 'items': {'type': 'string'}}
        schema['required'].append('roles')
        allowed_candidate = self.repo_dir / 'allowed.json'
        allowed_candidate.write_text(json.dumps(allowed_doc), encoding='utf-8')

        accepted_message = check_compatibility(
            base_commit,
            DEFAULT_CONTRACT_PATH,
            allowed_candidate,
            resolve_pinned_checker(),
            git_cwd=self.repo_dir,
            accepted_breaking_changes_path=allowlist_path,
        )
        self.assertIn('BRK-2026-10-13-001', accepted_message)

        extra_breaking_doc = json.loads(json.dumps(allowed_doc))
        del extra_breaking_doc['paths']['/api/v1/users']['post']['responses']['201']
        extra_breaking_candidate = self.repo_dir / 'extra-breaking.json'
        extra_breaking_candidate.write_text(json.dumps(extra_breaking_doc), encoding='utf-8')
        with self.assertRaises(CompatibilityError) as ctx:
            check_compatibility(
                base_commit,
                DEFAULT_CONTRACT_PATH,
                extra_breaking_candidate,
                resolve_pinned_checker(),
                git_cwd=self.repo_dir,
                accepted_breaking_changes_path=allowlist_path,
            )
        self.assertIn('removed', str(ctx.exception))

    def test_real_git_lifecycle_breaking_pr_and_push_before_exit_1(self) -> None:
        """Real Git lifecycle: breaking PR baseline and push-before baseline fail with exactly 1 finding."""
        from scripts.contract_compat import (
            DEFAULT_CONTRACT_PATH,
            CompatibilityError,
            check_compatibility,
            resolve_pinned_checker,
        )

        checker = resolve_pinned_checker()

        # Commit 1: initial commit without contract (historical absence)
        commit1 = self._commit_file('README.md', b'initial\n', 'chore: initial')

        # Bootstrap is allowed: commit exists, path genuinely absent
        bootstrap_candidate = self.repo_dir / 'bootstrap_candidate.json'
        bootstrap_candidate.write_bytes(minimal_spec())
        bootstrap_msg = check_compatibility(
            commit1, DEFAULT_CONTRACT_PATH, bootstrap_candidate, checker, git_cwd=self.repo_dir
        )
        self.assertIn('BOOTSTRAP VÁLIDO', bootstrap_msg)

        # Commit 2: main gains contracts/openapi.json
        commit2 = self._commit_file(
            'contracts/openapi.json', minimal_spec(), 'feat: add openapi contract'
        )

        # Breaking candidate: removes the only API path
        breaking_doc = {
            'openapi': '3.1.0',
            'info': {'title': 'API', 'version': '1.0.0'},
            'paths': {},
        }
        breaking_candidate = self.repo_dir / 'breaking.json'
        breaking_candidate.write_text(json.dumps(breaking_doc), encoding='utf-8')

        # Scenario A: PR baseline (pull_request.base.sha) -> CompatibilityError, exactly 1 finding
        with self.assertRaises(CompatibilityError) as ctx_pr:
            check_compatibility(
                commit2, DEFAULT_CONTRACT_PATH, breaking_candidate, checker, git_cwd=self.repo_dir
            )
        self.assertEqual(len(self._finding_lines(str(ctx_pr.exception))), 1)

        # Scenario B: push baseline (before SHA) -> CompatibilityError, exactly 1 finding
        with self.assertRaises(CompatibilityError) as ctx_push:
            check_compatibility(
                commit2, DEFAULT_CONTRACT_PATH, breaking_candidate, checker, git_cwd=self.repo_dir
            )
        self.assertEqual(len(self._finding_lines(str(ctx_push.exception))), 1)

        # Scenario C: compatible candidate passes
        compatible_doc = {
            'openapi': '3.1.0',
            'info': {'title': 'API', 'version': '1.0.0'},
            'paths': {
                '/login': {'post': {'responses': {'204': {'description': 'No content'}}}},
                '/api/v1/health': {'get': {'responses': {'200': {'description': 'OK'}}}},
            },
        }
        compatible_candidate = self.repo_dir / 'compatible.json'
        compatible_candidate.write_text(json.dumps(compatible_doc), encoding='utf-8')
        compat_msg = check_compatibility(
            commit2, DEFAULT_CONTRACT_PATH, compatible_candidate, checker, git_cwd=self.repo_dir
        )
        self.assertIn('COMPATIBILIDADE APROVADA', compat_msg)

    def test_real_git_lifecycle_bogus_sha_fails_closed(self) -> None:
        """Well-formed but nonexistent baseline SHA fails closed (no bootstrap)."""
        from scripts.contract_compat import (
            DEFAULT_CONTRACT_PATH,
            CompatibilityError,
            check_compatibility,
            resolve_pinned_checker,
        )

        self._commit_file('README.md', b'initial\n', 'chore: initial')
        candidate = self.repo_dir / 'candidate.json'
        candidate.write_bytes(minimal_spec())
        bogus = 'f' * 40
        with self.assertRaises(CompatibilityError) as ctx:
            check_compatibility(
                bogus, DEFAULT_CONTRACT_PATH, candidate, resolve_pinned_checker(), git_cwd=self.repo_dir
            )
        self.assertIn('não existe no repositório', str(ctx.exception))

    def test_real_git_lifecycle_bogus_path_fails_closed(self) -> None:
        """Any contract path other than the fixed path fails closed."""
        from scripts.contract_compat import CompatibilityError, check_compatibility, resolve_pinned_checker

        commit1 = self._commit_file('README.md', b'initial\n', 'chore: initial')
        candidate = self.repo_dir / 'candidate.json'
        candidate.write_bytes(minimal_spec())
        with self.assertRaises(CompatibilityError) as ctx:
            check_compatibility(
                commit1, 'specs/other.json', candidate, resolve_pinned_checker(), git_cwd=self.repo_dir
            )
        self.assertIn('Caminho de contrato inválido', str(ctx.exception))

    def test_real_git_lifecycle_corrupt_repo_fails_closed(self) -> None:
        """Git errors outside genuine path absence fail closed instead of bootstrapping."""
        import subprocess
        import tempfile

        from scripts.contract_compat import (
            DEFAULT_CONTRACT_PATH,
            CompatibilityError,
            is_contract_absent_in_commit,
        )

        commit1 = self._commit_file('README.md', b'initial\n', 'chore: initial')
        not_a_repo = Path(tempfile.mkdtemp(prefix='not-a-repo-'))
        with self.assertRaises(CompatibilityError) as ctx:
            is_contract_absent_in_commit(commit1, DEFAULT_CONTRACT_PATH, git_cwd=not_a_repo)
        self.assertIn('Erro do Git', str(ctx.exception))

        # Force-push style rewritten history: dangling commit object fails closed too.
        subprocess.run(['git', 'checkout', '--orphan', 'orphaned'], cwd=self.repo_dir, check=True, capture_output=True)
        self._git('commit', '-m', 'orphan')
        dangling = '0123456789abcdef' * 2 + '0123'
        with self.assertRaises(CompatibilityError):
            is_contract_absent_in_commit(dangling, DEFAULT_CONTRACT_PATH, git_cwd=self.repo_dir)


class BaselineResolverEndToEndTests(unittest.TestCase):
    """Resolver pipeline over real event payload files and a real fixture repository."""

    def setUp(self) -> None:
        import subprocess
        import tempfile

        self.temp_dir = tempfile.TemporaryDirectory()
        self.repo_dir = Path(self.temp_dir.name)
        subprocess.run(['git', 'init', '-b', 'main', str(self.repo_dir)], check=True, capture_output=True)
        subprocess.run(['git', 'config', 'user.name', 'CI Tester'], cwd=self.repo_dir, check=True)
        subprocess.run(['git', 'config', 'user.email', 'ci@example.com'], cwd=self.repo_dir, check=True)
        (self.repo_dir / 'README.md').write_text('initial\n', encoding='utf-8')
        subprocess.run(['git', 'add', 'README.md'], cwd=self.repo_dir, check=True, capture_output=True)
        subprocess.run(['git', 'commit', '-m', 'chore: initial'], cwd=self.repo_dir, check=True, capture_output=True)
        self.base_commit = subprocess.run(
            ['git', 'rev-parse', 'HEAD'], cwd=self.repo_dir, capture_output=True, text=True, check=True
        ).stdout.strip()
        # Advance HEAD past the baseline so self-comparison guard does not trip.
        (self.repo_dir / 'contracts').mkdir(parents=True, exist_ok=True)
        (self.repo_dir / 'contracts' / 'openapi.json').write_text('{}\n', encoding='utf-8')
        subprocess.run(['git', 'add', 'contracts/openapi.json'], cwd=self.repo_dir, check=True, capture_output=True)
        subprocess.run(['git', 'commit', '-m', 'feat: contract'], cwd=self.repo_dir, check=True, capture_output=True)
        self.head_commit = subprocess.run(
            ['git', 'rev-parse', 'HEAD'], cwd=self.repo_dir, capture_output=True, text=True, check=True
        ).stdout.strip()

    def tearDown(self) -> None:
        self.temp_dir.cleanup()

    def _write_payload(self, payload: dict[str, object]) -> Path:
        """Persist an event payload JSON file inside the fixture repo."""
        payload_path = self.repo_dir / 'event.json'
        payload_path.write_text(json.dumps(payload), encoding='utf-8')
        return payload_path

    def test_pull_request_event_resolves_base_sha(self) -> None:
        """pull_request event resolves pull_request.base.sha end-to-end."""
        from scripts.resolve_contract_baseline import resolve_baseline_sha

        payload_path = self._write_payload(
            {'pull_request': {'base': {'sha': self.base_commit}, 'head': {'sha': 'b' * 40}}}
        )
        resolved = resolve_baseline_sha(
            event_name='pull_request', event_path=payload_path, git_cwd=self.repo_dir
        )
        self.assertEqual(resolved, self.base_commit)

    def test_push_event_on_main_resolves_before_sha(self) -> None:
        """push event on refs/heads/main resolves the pre-push 'before' SHA end-to-end."""
        from scripts.resolve_contract_baseline import resolve_baseline_sha

        payload_path = self._write_payload({'before': self.base_commit, 'after': 'c' * 40})
        resolved = resolve_baseline_sha(
            event_name='push',
            event_path=payload_path,
            git_ref='refs/heads/main',
            git_cwd=self.repo_dir,
        )
        self.assertEqual(resolved, self.base_commit)

    def test_push_zero_before_fails_closed(self) -> None:
        """push event carrying zero 'before' (new branch) fails closed."""
        from scripts.resolve_contract_baseline import BaselineResolutionError, resolve_baseline_sha

        payload_path = self._write_payload({'before': '0' * 40, 'after': 'c' * 40})
        with self.assertRaises(BaselineResolutionError):
            resolve_baseline_sha(
                event_name='push',
                event_path=payload_path,
                git_ref='refs/heads/main',
                git_cwd=self.repo_dir,
            )

    def test_baseline_equal_to_head_fails_closed(self) -> None:
        """Baseline identical to the checked-out HEAD is rejected (self-comparison)."""
        from scripts.resolve_contract_baseline import BaselineResolutionError, resolve_baseline_sha

        payload_path = self._write_payload(
            {'pull_request': {'base': {'sha': self.head_commit}, 'head': {'sha': 'b' * 40}}}
        )
        with self.assertRaises(BaselineResolutionError):
            resolve_baseline_sha(
                event_name='pull_request', event_path=payload_path, git_cwd=self.repo_dir
            )

    def test_unavailable_commit_in_repo_fails_closed(self) -> None:
        """Baseline SHA missing from the repository (and unfetchable remote) fails closed."""
        from scripts.resolve_contract_baseline import BaselineResolutionError, resolve_baseline_sha

        payload_path = self._write_payload(
            {'pull_request': {'base': {'sha': 'd' * 40}, 'head': {'sha': 'b' * 40}}}
        )
        with self.assertRaises(BaselineResolutionError):
            resolve_baseline_sha(
                event_name='pull_request',
                event_path=payload_path,
                git_cwd=self.repo_dir,
                remote='origin-unreachable-fixture',
            )


if __name__ == '__main__':
    unittest.main()
