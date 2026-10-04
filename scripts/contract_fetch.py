"""Busca confiável de artefatos do GitHub Actions para o consumidor do contrato.

Orquestra o transporte local via `gh` com validações fail-closed antes de confiar
em qualquer byte de especificação.
"""

from __future__ import annotations

from dataclasses import dataclass
import hashlib
import json
from pathlib import Path
import subprocess


@dataclass(frozen=True)
class FetchPolicy:
    """Entradas confiáveis e imutáveis para a busca do contrato."""

    owner: str
    repo: str
    expected_sha: str
    expected_workflow_path: str
    expected_workflow_name: str
    expected_workflow_id: int
    expected_artifact_name: str
    run_id: int
    artifact_id: int
    mode: str = "download-fresh"


class FetchError(Exception):
    """Lançada quando a busca confiável falha em modo fail-closed."""


def run_gh_json(args: list[str]) -> dict:
    """Executa comando `gh api` e retorna o JSON interpretado."""
    completed = subprocess.run(["gh"] + args, capture_output=True, text=True, check=False)
    if completed.returncode != 0:
        raise FetchError(f"Falha na chamada gh: {' '.join(args)[:200]}")
    try:
        parsed = json.loads(completed.stdout)
    except ValueError as exc:
        raise FetchError(f"JSON retornado pelo gh é inválido: {exc}") from exc
    if not isinstance(parsed, dict):
        raise FetchError("Formato inesperado do JSON retornado pelo gh")
    return parsed


def validate_run_metadata(run: dict, policy: FetchPolicy) -> None:
    """Valida metadados da execução contra a política confiável."""
    if run.get("status") != "completed":
        raise FetchError("Execução do workflow não concluída")
    if run.get("conclusion") != "success":
        raise FetchError("Conclusão da execução não foi de sucesso")
    if run.get("head_sha") != policy.expected_sha:
        raise FetchError("Divergência de SHA no commit da execução")
    if run.get("head_branch") != "main":
        raise FetchError("Branch da execução diferente de main")
    if run.get("event") not in ("workflow_dispatch", "release", "workflow_run"):
        raise FetchError("Evento da execução fora da allowlist permitida")
    if run.get("path") != policy.expected_workflow_path:
        raise FetchError("Caminho do workflow divergente do esperado")
    if run.get("name") != policy.expected_workflow_name:
        raise FetchError("Nome do workflow divergente do esperado")
    if int(run.get("workflow_id", -1)) != policy.expected_workflow_id:
        raise FetchError("ID do workflow divergente do esperado")
    if int(run.get("id", -1)) != policy.run_id:
        raise FetchError("ID da execução divergente do esperado")


def validate_artifact_metadata(artifact: dict, policy: FetchPolicy) -> None:
    """Valida metadados do artefato contra a política confiável."""
    if artifact.get("name") != policy.expected_artifact_name:
        raise FetchError("Nome do artefato divergente do esperado")
    if int(artifact.get("id", -1)) != policy.artifact_id:
        raise FetchError("ID do artefato divergente do esperado")
    if artifact.get("expired", False) is True:
        raise FetchError("Artefato expirado")


def download_artifact_zip(policy: FetchPolicy, dest: Path) -> bytes:
    """Baixa o ZIP bruto do artefato via gh; retorna os bytes."""
    endpoint = f"repos/{policy.owner}/{policy.repo}/actions/artifacts/{policy.artifact_id}/zip"
    completed = subprocess.run(
        ["gh", "api", endpoint, "--output", dest.as_posix()],
        capture_output=True,
        text=True,
        check=False,
    )
    if completed.returncode != 0:
        raise FetchError("Falha no download do artefato")
    data = dest.read_bytes()
    if len(data) == 0:
        raise FetchError("Download de artefato vazio rejeitado")
    return data


def compute_sha256(payload: bytes) -> str:
    """Calcula SHA256 hexadecimal dos bytes informados."""
    return hashlib.sha256(payload).hexdigest()


def fetch_and_verify(policy: FetchPolicy, workdir: str) -> dict[str, str]:
    """Busca artefato, valida metadados, extrai com segurança e retorna digests."""
    base = Path(workdir)
    base.mkdir(parents=True, exist_ok=True)
    run = run_gh_json(["api", f"repos/{policy.owner}/{policy.repo}/actions/runs/{policy.run_id}"])
    validate_run_metadata(run, policy)
    artifact = run_gh_json(["api", f"repos/{policy.owner}/{policy.repo}/actions/artifacts/{policy.artifact_id}"])
    validate_artifact_metadata(artifact, policy)
    zip_path = base / "artifact.zip"
    archive_bytes = download_artifact_zip(policy, zip_path)
    archive_sha = compute_sha256(archive_bytes)
    import importlib.util as import_util
    import sys as sys_mod

    spec_path = Path(__file__).resolve().parent / "contract_extract.py"
    loader_spec = import_util.spec_from_file_location("contract_extract_local", spec_path)
    if loader_spec is None or loader_spec.loader is None:
        raise FetchError("Falha ao carregar o extrator de contrato")
    module = import_util.module_from_spec(loader_spec)
    sys_mod.modules["contract_extract_local"] = module
    loader_spec.loader.exec_module(module)
    result = module.safe_extract_archive(archive_bytes)
    return {"archive_sha256": archive_sha, "artifact_file_sha256": result.file_sha256, "files": ",".join(result.files)}
