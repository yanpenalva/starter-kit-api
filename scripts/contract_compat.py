"""Verificação de compatibilidade e breaking changes contra base imutável.

Compara o contrato OpenAPI candidato contra o commit base imutável via ferramenta
oasdiff versão fixada (pinned), com fixtures operacionais positivas e negativas
garantindo detecção fail-closed de quebras de contrato.
"""

from __future__ import annotations

import argparse
from dataclasses import dataclass
import json
from pathlib import Path
import re
import subprocess
import sys
import tempfile
from typing import Any

try:  # package import (tests, repo root) or direct-script import (python3 scripts/...)
    from scripts.resolve_contract_baseline import resolve_baseline_sha
except ImportError:  # pragma: no cover - exercised only via direct script execution
    from resolve_contract_baseline import resolve_baseline_sha  # type: ignore[no-redef]

TOOL_NAME: str = "oasdiff"
PINNED_VERSION: str = "1.33.0"
IMMUTABLE_BASE_MAIN: str = "1390009d37f6a3493b21be7d83dbd32ae355d579"
DEFAULT_CONTRACT_PATH: str = "contracts/openapi.json"
DEFAULT_ACCEPTED_BREAKING_CHANGES_PATH: str = "contracts/accepted-breaking-changes.json"
ERROR_LEVEL: int = 3


class CompatibilityError(Exception):
    """Lançada quando a verificação de compatibilidade falha em modo fail-closed."""


@dataclass(frozen=True)
class PinnedChecker:
    """Metadados da ferramenta de verificação fixada."""

    name: str
    version: str
    executable_path: str


@dataclass(frozen=True)
class AcceptedBreakingChange:
    """Uma quebra exata aprovada com justificativa rastreável."""

    id: str
    endpoint: str
    change: str
    justification: str
    pr: str
    oasdiff_id: str
    oasdiff_text: str

    @property
    def signature(self) -> tuple[str, str, str, str]:
        """Assinatura exata do endpoint e da regra emitida pelo oasdiff."""
        method, path = self.endpoint.split(" ", 1)
        return self.oasdiff_id, method, path, self.oasdiff_text


def default_accepted_breaking_changes_path() -> Path:
    """Localiza a allowlist versionada junto ao código do verificador."""
    return Path(__file__).resolve().parent.parent / DEFAULT_ACCEPTED_BREAKING_CHANGES_PATH


def load_accepted_breaking_changes(path: Path) -> list[AcceptedBreakingChange]:
    """Lê e valida a allowlist; qualquer formato ambíguo falha fechado."""
    if not path.is_file():
        raise CompatibilityError(f"Allowlist de breaking changes ausente: '{path}'.")

    try:
        document = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        raise CompatibilityError(f"Allowlist inválida em '{path}': {exc}.") from exc

    if not isinstance(document, dict):
        raise CompatibilityError("Allowlist inválida: a raiz deve ser um objeto JSON.")
    if set(document) != {"schema_version", "accepted_breaking_changes"}:
        raise CompatibilityError("Allowlist inválida: campos raiz inesperados ou ausentes.")
    if type(document["schema_version"]) is not int or document["schema_version"] != 1:
        raise CompatibilityError("Allowlist inválida: schema_version deve ser 1.")

    records = document["accepted_breaking_changes"]
    if not isinstance(records, list):
        raise CompatibilityError("Allowlist inválida: accepted_breaking_changes deve ser uma lista.")

    expected_fields = {
        "id",
        "endpoint",
        "change",
        "justification",
        "pr",
        "oasdiff_id",
        "oasdiff_text",
    }
    accepted: list[AcceptedBreakingChange] = []
    identifiers: set[str] = set()
    signatures: set[tuple[str, str, str, str]] = set()

    for index, record in enumerate(records):
        if not isinstance(record, dict) or set(record) != expected_fields:
            raise CompatibilityError(
                f"Allowlist inválida: entrada {index} requer exatamente {sorted(expected_fields)}."
            )

        values = [record[field] for field in expected_fields]
        if any(not isinstance(value, str) or not value.strip() for value in values):
            raise CompatibilityError(f"Allowlist inválida: entrada {index} contém campo vazio ou não textual.")

        identifier = record["id"]
        endpoint = record["endpoint"]
        if not re.fullmatch(r"[A-Z0-9][A-Z0-9._-]*", identifier):
            raise CompatibilityError(f"Allowlist inválida: id malformado na entrada {index}.")
        if not re.fullmatch(r"(GET|POST|PUT|PATCH|DELETE) /[A-Za-z0-9._~{}:/-]+", endpoint):
            raise CompatibilityError(f"Allowlist inválida: endpoint malformado na entrada {index}.")

        item = AcceptedBreakingChange(
            id=identifier,
            endpoint=endpoint,
            change=record["change"],
            justification=record["justification"],
            pr=record["pr"],
            oasdiff_id=record["oasdiff_id"],
            oasdiff_text=record["oasdiff_text"],
        )
        if item.id in identifiers:
            raise CompatibilityError(f"Allowlist inválida: id duplicado '{item.id}'.")
        if item.signature in signatures:
            raise CompatibilityError(f"Allowlist inválida: assinatura duplicada para '{item.endpoint}'.")

        identifiers.add(item.id)
        signatures.add(item.signature)
        accepted.append(item)

    return accepted


def parse_breaking_findings(output: str) -> list[dict[str, Any]]:
    """Valida a saída JSON estruturada do oasdiff fixado."""
    try:
        findings = json.loads(output)
    except json.JSONDecodeError as exc:
        raise CompatibilityError(f"Saída JSON inválida do oasdiff: {exc}.") from exc

    if not isinstance(findings, list):
        raise CompatibilityError("Saída do oasdiff inválida: era esperada uma lista JSON.")

    required_fields = {"id", "text", "level", "operation", "path"}
    for index, finding in enumerate(findings):
        if not isinstance(finding, dict) or not required_fields.issubset(finding):
            raise CompatibilityError(f"Finding inválido do oasdiff na posição {index}.")
        if any(not isinstance(finding[field], str) or not finding[field] for field in ("id", "text", "operation", "path")):
            raise CompatibilityError(f"Finding inválido do oasdiff na posição {index}: campos textuais ausentes.")
        if type(finding["level"]) is not int or finding["level"] not in {1, 2, ERROR_LEVEL}:
            raise CompatibilityError(f"Finding inválido do oasdiff na posição {index}: nível desconhecido.")

    return findings


def filter_accepted_breaking_findings(
    findings: list[dict[str, Any]], accepted: list[AcceptedBreakingChange]
) -> tuple[list[AcceptedBreakingChange], list[dict[str, Any]]]:
    """Consome cada autorização uma vez e devolve todos os findings sem correspondência exata."""
    remaining = accepted.copy()
    matched: list[AcceptedBreakingChange] = []
    rejected: list[dict[str, Any]] = []

    for finding in findings:
        signature = (
            finding["id"],
            finding["operation"],
            finding["path"],
            finding["text"],
        )
        entry = next((item for item in remaining if item.signature == signature), None)
        if entry is None:
            rejected.append(finding)
            continue

        remaining.remove(entry)
        matched.append(entry)

    return matched, rejected


def resolve_pinned_checker(
    tool_name: str = TOOL_NAME, expected_version: str = PINNED_VERSION
) -> PinnedChecker:
    """Localiza a ferramenta fixada e valida a versão exata."""
    try:
        completed = subprocess.run(
            [tool_name, "-v"], capture_output=True, text=True, check=False
        )
    except FileNotFoundError as exc:
        raise CompatibilityError(
            f"Ferramenta fixada '{tool_name}' não encontrada no ambiente."
        ) from exc

    if completed.returncode != 0:
        raise CompatibilityError(
            f"Erro ao consultar versão de '{tool_name}': {completed.stderr.strip()}"
        )

    output = completed.stdout.strip()
    match = re.search(r"(\d+\.\d+\.\d+)", output)
    if not match:
        raise CompatibilityError(
            f"Formato de versão inesperado da ferramenta '{tool_name}': '{output}'."
        )

    detected = match.group(1)
    if detected != expected_version:
        raise CompatibilityError(
            f"Versão de '{tool_name}' diverge da fixada: esperado={expected_version}, detectado={detected}."
        )

    return PinnedChecker(
        name=tool_name, version=detected, executable_path=tool_name
    )


def verify_commit_exists(commit_sha: str, git_cwd: Path | None = None) -> None:
    """Verifica se o commit baseline existe no repositório git local."""
    if not re.fullmatch(r"[0-9a-fA-F]{40}", commit_sha):
        raise CompatibilityError(
            f"Commit SHA inválido (deve conter 40 caracteres hexadecimais): '{commit_sha}'."
        )

    proc = subprocess.run(
        ["git", "rev-parse", "--verify", f"{commit_sha}^{{commit}}"],
        capture_output=True,
        text=True,
        check=False,
        cwd=git_cwd,
    )
    if proc.returncode != 0:
        raise CompatibilityError(
            f"Commit baseline '{commit_sha}' não existe no repositório git local: {proc.stderr.strip()}."
        )


def is_contract_absent_in_commit(
    commit_sha: str, contract_path: str, git_cwd: Path | None = None
) -> bool:
    """Comprova se o arquivo de contrato está estritamente ausente no commit informado.

    Usa `git ls-tree` com estado tri-partido: exit 0 sem saída prova ausência do
    caminho; exit 0 com saída prova presença; qualquer outro código (objeto
    inválido, repositório corrompido) falha fechado sem bootstrap.
    """
    proc = subprocess.run(
        ["git", "ls-tree", commit_sha, "--", contract_path],
        capture_output=True,
        text=True,
        check=False,
        cwd=git_cwd,
    )
    if proc.returncode != 0:
        raise CompatibilityError(
            f"Erro do Git ao inspecionar caminho '{contract_path}' no commit '{commit_sha}': "
            f"{proc.stderr.strip()}."
        )
    return proc.stdout.strip() == ""


def extract_base_contract(
    commit_sha: str, contract_path: str, git_cwd: Path | None = None
) -> bytes:
    """Extrai os bytes do contrato no commit baseline."""
    proc = subprocess.run(
        ["git", "show", f"{commit_sha}:{contract_path}"],
        capture_output=True,
        check=False,
        cwd=git_cwd,
    )
    if proc.returncode != 0:
        raise CompatibilityError(
            f"Falha ao extrair contrato baseline '{contract_path}' do commit '{commit_sha}': {proc.stderr.decode('utf-8', errors='replace').strip()}."
        )
    return proc.stdout


def run_checker_breaking(
    base_file: Path, candidate_file: Path, checker: PinnedChecker
) -> tuple[int, str]:
    """Executa o comando breaking da ferramenta fixada."""
    cmd = [
        checker.executable_path,
        "breaking",
        str(base_file),
        str(candidate_file),
        "--fail-on",
        "ERR",
        "--allow-external-refs=false",
        "--format",
        "json",
    ]
    proc = subprocess.run(cmd, capture_output=True, text=True, check=False)
    output = proc.stdout.strip() or proc.stderr.strip()
    return proc.returncode, output


def build_fixture_specs() -> (
    tuple[dict[str, Any], list[tuple[str, dict[str, Any]]], dict[str, Any]]
):
    """Constrói especificações base, negativas e positiva para validação operacional."""
    base: dict[str, Any] = {
        "openapi": "3.1.0",
        "info": {"title": "Fixture API", "version": "1.0.0"},
        "paths": {
            "/api/v1/resource": {
                "get": {
                    "operationId": "resource.get",
                    "responses": {
                        "200": {
                            "description": "Sucesso",
                            "content": {
                                "application/json": {
                                    "schema": {
                                        "type": "object",
                                        "properties": {
                                            "id": {"type": "integer"},
                                            "name": {"type": "string"},
                                        },
                                        "required": ["id"],
                                    }
                                }
                            },
                        }
                    },
                },
                "post": {
                    "operationId": "resource.post",
                    "requestBody": {
                        "content": {
                            "application/json": {
                                "schema": {
                                    "type": "object",
                                    "properties": {"name": {"type": "string"}},
                                }
                            }
                        }
                    },
                    "responses": {"204": {"description": "Sem conteúdo"}},
                },
            }
        },
    }

    neg_path = json.loads(json.dumps(base))
    del neg_path["paths"]["/api/v1/resource"]["get"]

    neg_status = json.loads(json.dumps(base))
    neg_status["paths"]["/api/v1/resource"]["get"]["responses"] = {
        "404": {"description": "Não encontrado"}
    }

    neg_type = json.loads(json.dumps(base))
    neg_type["paths"]["/api/v1/resource"]["get"]["responses"]["200"]["content"][
        "application/json"
    ]["schema"]["properties"]["id"]["type"] = "string"

    neg_req_input = json.loads(json.dumps(base))
    neg_req_input["paths"]["/api/v1/resource"]["post"]["requestBody"]["content"][
        "application/json"
    ]["schema"]["required"] = ["name"]

    neg_removed_prop = json.loads(json.dumps(base))
    del neg_removed_prop["paths"]["/api/v1/resource"]["get"]["responses"][
        "200"
    ]["content"]["application/json"]["schema"]["properties"]["id"]

    negatives: list[tuple[str, dict[str, Any]]] = [
        ("path_removido", neg_path),
        ("status_removido", neg_status),
        ("tipo_alterado", neg_type),
        ("campo_obrigatorio_adicionado_requisicao", neg_req_input),
        ("propriedade_obrigatoria_removida_resposta", neg_removed_prop),
    ]

    positive = json.loads(json.dumps(base))
    positive["paths"]["/api/v1/resource"]["get"]["responses"]["200"]["content"][
        "application/json"
    ]["schema"]["properties"]["description"] = {"type": "string"}
    positive["paths"]["/api/v1/nova-rota"] = {
        "get": {"responses": {"200": {"description": "Compatível"}}}
    }

    return base, negatives, positive


def verify_operational_fixtures(checker: PinnedChecker) -> None:
    """Comprova operacionalmente que o checker detecta e rejeita quebras de contrato."""
    base, negatives, positive = build_fixture_specs()

    with tempfile.TemporaryDirectory() as temp_dir:
        base_path = Path(temp_dir) / "base_fixture.json"
        base_path.write_text(json.dumps(base), encoding="utf-8")

        pos_path = Path(temp_dir) / "pos_fixture.json"
        pos_path.write_text(json.dumps(positive), encoding="utf-8")
        pos_code, pos_out = run_checker_breaking(base_path, pos_path, checker)
        if pos_code != 0:
            raise CompatibilityError(
                f"Falha na fixture positiva: mudança compatível foi rejeitada: {pos_out}"
            )

        for name, spec in negatives:
            neg_path = Path(temp_dir) / f"neg_{name}.json"
            neg_path.write_text(json.dumps(spec), encoding="utf-8")
            neg_code, neg_out = run_checker_breaking(
                base_path, neg_path, checker
            )
            if neg_code == 0:
                raise CompatibilityError(
                    f"Falha de segurança na fixture negativa '{name}': quebra não foi detectada pelo checker."
                )


def check_compatibility(
    base_commit: str,
    contract_path: str,
    candidate_path: Path,
    checker: PinnedChecker,
    git_cwd: Path | None = None,
    accepted_breaking_changes_path: Path | None = None,
) -> str:
    """Executa a verificação completa de compatibilidade e proveniência."""
    if contract_path != DEFAULT_CONTRACT_PATH:
        raise CompatibilityError(
            f"Caminho de contrato inválido '{contract_path}': a verificação é restrita exclusivamente a '{DEFAULT_CONTRACT_PATH}'."
        )

    if not candidate_path.is_file():
        raise CompatibilityError(
            f"Arquivo de contrato candidato '{candidate_path}' não encontrado."
        )

    accepted_path = accepted_breaking_changes_path or default_accepted_breaking_changes_path()
    accepted_changes = load_accepted_breaking_changes(accepted_path)

    verify_commit_exists(base_commit, git_cwd)
    verify_operational_fixtures(checker)

    absent = is_contract_absent_in_commit(base_commit, contract_path, git_cwd)
    if absent:
        return (
            f"BOOTSTRAP VÁLIDO: Commit baseline '{base_commit}' existe e caminho '{contract_path}' "
            f"está comprovadamente ausente. Checker fixado '{checker.name} {checker.version}' operacional "
            f"com 5 fixtures negativas rejeitadas e 1 positiva aceita."
        )

    base_bytes = extract_base_contract(base_commit, contract_path, git_cwd)
    with tempfile.TemporaryDirectory() as temp_dir:
        base_file = Path(temp_dir) / "base_extracted.json"
        base_file.write_bytes(base_bytes)
        code, output = run_checker_breaking(
            base_file, candidate_path, checker
        )
        if code == 0:
            return (
                f"COMPATIBILIDADE APROVADA: Comparação real contra {base_commit}:{contract_path} "
                f"sem breaking changes via '{checker.name} {checker.version}'."
            )
        if code != 1:
            raise CompatibilityError(
                f"oasdiff terminou com status inesperado {code} contra baseline {base_commit}:\n{output}"
            )

        findings = parse_breaking_findings(output)
        blocking_findings = [finding for finding in findings if finding["level"] == ERROR_LEVEL]
        if not blocking_findings:
            raise CompatibilityError(
                f"oasdiff retornou status 1 sem finding ERR contra baseline {base_commit}:\n{output}"
            )

        matched, rejected = filter_accepted_breaking_findings(blocking_findings, accepted_changes)
        if rejected:
            details = json.dumps(rejected, ensure_ascii=False, sort_keys=True)
            raise CompatibilityError(
                f"Quebra de compatibilidade não listada contra baseline {base_commit}: {details}"
            )

    accepted_ids = ", ".join(item.id for item in matched)
    return (
        f"COMPATIBILIDADE APROVADA COM EXCEÇÕES LISTADAS: {accepted_ids}; "
        f"todas as demais quebras ERR continuam bloqueadas via '{checker.name} {checker.version}'."
    )


def parse_args(args: list[str] | None = None) -> argparse.Namespace:
    """Analisa argumentos de linha de comando."""
    parser = argparse.ArgumentParser(
        description="Verificador de compatibilidade OpenAPI contra baseline imutável."
    )
    parser.add_argument(
        "--base-commit",
        default=None,
        help="Commit SHA baseline imutável da main (resolvido automaticamente do evento do GitHub se omitido).",
    )
    parser.add_argument(
        "--contract-path",
        default=DEFAULT_CONTRACT_PATH,
        help=f"Caminho relativo do contrato OpenAPI no commit baseline (fixo em {DEFAULT_CONTRACT_PATH}).",
    )
    parser.add_argument(
        "--candidate",
        default=DEFAULT_CONTRACT_PATH,
        help="Caminho do contrato candidato no disco.",
    )
    parser.add_argument(
        "--tool-name",
        default=TOOL_NAME,
        help="Nome ou caminho do executável da ferramenta fixada.",
    )
    parser.add_argument(
        "--expected-version",
        default=PINNED_VERSION,
        help="Versão exata esperada da ferramenta fixada.",
    )
    return parser.parse_args(args)


def main(args: list[str] | None = None) -> int:
    """Ponto de entrada CLI."""
    parsed = parse_args(args)
    try:
        base_commit = parsed.base_commit
        if not base_commit:
            base_commit = resolve_baseline_sha()

        checker = resolve_pinned_checker(
            parsed.tool_name, parsed.expected_version
        )
        message = check_compatibility(
            base_commit,
            parsed.contract_path,
            Path(parsed.candidate),
            checker,
        )
        print(message)
        return 0
    except Exception as exc:
        print(f"FALHA FECHADA: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
