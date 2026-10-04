# Spec — FEAT-11 (API #11)

## Objetivo

Preparar a infraestrutura de contrato OpenAPI, validação de proveniência de artefato e workflow guardado para a onda 3 (Stage A — CONTRACT prep), estabelecendo verificação fail-closed sem violar os limites da lane de autenticação (FEAT-13) e sem simular sucesso prematuro de rotas ou export.

## Escopo (Stage A — CONTRACT prep)

1. **Script de proveniência fail-closed (`scripts/verify_contract_artifact.py`)**:
   - Valida que o artefato OpenAPI descende de `main` em SHA exato e completo de 40 caracteres hexadecimais (rejeita explicitamente `latest`, tags ou SHAs parciais).
   - Valida evento de disparo através de allowlist restrita (`workflow_dispatch`, `release`, `workflow_run`).
   - Recalcula independentemente o digest SHA256 do arquivo de artefato e compara com o valor registrado no receipt.
   - Valida receipt completo contendo: `workflow_name`, `workflow_path`, `workflow_id`, `run_id`, `run_url`, `artifact_id`, `artifact_name`, `commit_sha`, `head_branch`, `event`, `created_at`, `archive_sha256` e `artifact_file_sha256`.
   - Comportamento fail-closed: qualquer divergência, campo ausente ou valor malformado encerra a execução com status não-zero e mensagem descritiva.
   - Interface de CLI e helpers para uso pelo produtor e orquestrador.

2. **Workflow draft guardado (`.github/workflows/openapi.yml`)**:
   - Trigger restrito via `workflow_dispatch` com input obrigatório de justificativa.
   - Permissão restrita (`contents: read`), sem segredos novos.
   - Step guardado com falha explícita e informativa (`exit 1`), documentando que o Stage B aguarda o congelamento de autenticação L1 e instalação do Scramble.
   - Proibido qualquer echo de falso sucesso antes de endpoints reais existirem.

3. **Alinhamento documental de contexto**:
   - `.context/project/api.md`: registra o caminho de transporte ativo pelo orquestrador local com `gh` para repositórios privados (sem novo PAT para consumidor; builds do frontend sem credenciais de CI; `GITHUB_TOKEN contents:read` não cruza repositórios).
   - `.context/project/stack.md`: registra a decisão de transporte e o version pin exato do Scramble (`dedoc/scramble 0.13.47`).


4. **Suite de testes automatizados (`tests/tools/test_verify_contract_artifact.py`)**:
   - Unittest em Python cobrindo cenários positivos e negativos (divergência de SHA256, SHA ausente ou inválido, evento fora da allowlist, branch diferente de `main`, metadata incompleta ou malformada, artefato inexistente, CLI).
   - Discovery de testes do projeto permanece 100% verde sem afetar `test_context_validation.py`.
   - Proibição absoluta de chamadas reais de rede/API do GitHub, mutações de repositório e segredos em fixtures.

## Fora de escopo (Stage B — publicação real)

- Instalação do Scramble (`composer.json`/`composer.lock`).
- Configuração de providers, rotas, controllers, form requests ou resources.
- Alterações em `app/`, `config/`, `routes/`, `database/`.
- Assinatura criptográfica / attestation (notary/cosign).
- Execução de contêineres Docker.

## Critérios de aceite

- [x] Script `scripts/verify_contract_artifact.py` implementado com regras fail-closed e conformidade estrita de qualidade de código (zero `else`/`elif`, <= 3 retornos por função).
- [x] Workflow `.github/workflows/openapi.yml` configurado como draft guardado que falha com mensagem explicativa.
- [x] Suite de testes `tests/tools/test_verify_contract_artifact.py` com testes negativos e positivos passando integralmente.
- [x] Documentos `api.md` e `stack.md` atualizados com o registro factual do transporte aprovado.
- [x] Todos os gates CSD e unittests executados com sucesso.
