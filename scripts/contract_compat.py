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
import tempfile
from typing import Any

TOOL_NAME: str = "oasdiff"
PINNED_VERSION: str = "1.33.0"
IMMUTABLE_BASE_MAIN: str = "1390009d37f6a3493b21be7d83dbd32ae355d579"
DEFAULT_CONTRACT_PATH: str = "contracts/openapi.json"


class CompatibilityError(Exception):
    """Lançada quando a verificação de compatibilidade falha em modo fail-closed."""


@dataclass(frozen=True)
class PinnedChecker:
    """Metadados da ferramenta de verificação fixada."""

    name: str
    version: str
    executable_path: str


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


def verify_commit_exists(commit_sha: str) -> None:
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
    )
    if proc.returncode != 0:
        raise CompatibilityError(
            f"Commit baseline '{commit_sha}' não existe no repositório git local: {proc.stderr.strip()}."
        )


def is_contract_absent_in_commit(commit_sha: str, contract_path: str) -> bool:
    """Comprova se o arquivo de contrato está estritamente ausente no commit informado."""
    proc = subprocess.run(
        ["git", "cat-file", "-e", f"{commit_sha}:{contract_path}"],
        capture_output=True,
        text=True,
        check=False,
    )
    if proc.returncode == 0:
        return False

    stderr = proc.stderr.lower()
    is_path_absence = (
        "not a valid object name" in stderr
        or "does not exist in" in stderr
        or "fatal: path" in stderr
    )
    if is_path_absence:
        return True

    raise CompatibilityError(
        f"Erro inesperado do Git ao inspecionar caminho '{contract_path}' no commit '{commit_sha}': {proc.stderr.strip()}."
    )


def extract_base_contract(commit_sha: str, contract_path: str) -> bytes:
    """Extrai os bytes do contrato no commit baseline."""
    proc = subprocess.run(
        ["git", "show", f"{commit_sha}:{contract_path}"],
        capture_output=True,
        check=False,
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
    ]
    proc = subprocess.run(cmd, capture_output=True, text=True, check=False)
    output = (proc.stdout + "\n" + proc.stderr).strip()
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
) -> str:
    """Executa a verificação completa de compatibilidade e proveniência."""
    if not candidate_path.is_file():
        raise CompatibilityError(
            f"Arquivo de contrato candidato '{candidate_path}' não encontrado."
        )

    verify_commit_exists(base_commit)
    verify_operational_fixtures(checker)

    absent = is_contract_absent_in_commit(base_commit, contract_path)
    if absent:
        return (
            f"BOOTSTRAP VÁLIDO: Commit baseline '{base_commit}' existe e caminho '{contract_path}' "
            f"está comprovadamente ausente. Checker fixado '{checker.name} {checker.version}' operacional "
            f"com 5 fixtures negativas rejeitadas e 1 positiva aceita."
        )

    base_bytes = extract_base_contract(base_commit, contract_path)
    with tempfile.TemporaryDirectory() as temp_dir:
        base_file = Path(temp_dir) / "base_extracted.json"
        base_file.write_bytes(base_bytes)
        code, output = run_checker_breaking(
            base_file, candidate_path, checker
        )
        if code != 0:
            raise CompatibilityError(
                f"Quebra de compatibilidade detectada contra baseline {base_commit}:\n{output}"
            )

    return (
        f"COMPATIBILIDADE APROVADA: Comparação real contra {base_commit}:{contract_path} "
        f"sem breaking changes via '{checker.name} {checker.version}'."
    )


def parse_args(args: list[str] | None = None) -> argparse.Namespace:
    """Analisa argumentos de linha de comando."""
    parser = argparse.ArgumentParser(
        description="Verificador de compatibilidade OpenAPI contra baseline imutável."
    )
    parser.add_argument(
        "--base-commit",
        default=IMMUTABLE_BASE_MAIN,
        help="Commit SHA baseline imutável da main.",
    )
    parser.add_argument(
        "--contract-path",
        default=DEFAULT_CONTRACT_PATH,
        help="Caminho relativo do contrato OpenAPI no commit baseline.",
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
        checker = resolve_pinned_checker(
            parsed.tool_name, parsed.expected_version
        )
        message = check_compatibility(
            parsed.base_commit,
            parsed.contract_path,
            Path(parsed.candidate),
            checker,
        )
        print(message)
        return 0
    except CompatibilityError as exc:
        print(f"FALHA FECHADA: {exc}")
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
