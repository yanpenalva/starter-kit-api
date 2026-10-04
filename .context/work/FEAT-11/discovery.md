# Discovery — FEAT-11 (API #11, onda 3)

Data: 2026-10-04 · Base: `5d8c198296949b7f3c66b2bd56f086cb8f9ceafe` (PR #36 merge, origin/main) · Branch: `feat/wave-3-openapi-prep` → alvo `main` (origin), EXC-0001.
Worktree persistente exclusivo: `/home/hiyan/Development/github/personal/starter-kit-api-wave3-contract-worktree`.

## Roadmap e objetivos (fonte: `.context/project/roadmap.md`)

- **Onda 3, item 2**: "OpenAPI gerado e publicado | API #11; evolução de #24 | L | Preparação após #3/#7/#10; publicar contrato de auth a partir de endpoints reais de #13. Artefato versionado acessível à SPA e comparação de compatibilidade em CI."
- **Decomposição em dois estágios**:
  - **Stage A (este escopo — CONTRACT prep)**: Infraestrutura de proveniência de artefato fail-closed (`scripts/verify_contract_artifact.py`), workflow draft guardado (`.github/workflows/openapi.yml`), alinhamento de contexto (`api.md`, `stack.md`) e work item CSD.
  - **Stage B (escopo futuro — publicação real)**: Desbloqueado somente após o congelamento da autenticação L1 (FEAT-13). Instalação do Scramble (`dedoc/scramble 0.13.47`), configuração de securityScheme tipo cookie (`starter-kit-api-session` + CSRF), transformer de rotas vendor-excluded (`/sanctum/csrf-cookie`), export determinístico (`LC_ALL=C`, 2x idempotente) e publicação de artefato via Actions.

## Lane Boundary estrito

- A lane auth paralela é a dona exclusiva de `routes/`, `app/Models/User*`, `database/migrations/`, `app/Providers/AppServiceProvider.php`, `bootstrap/` e `FEAT-13`.
- No Stage A é estritamente proibido editar ou criar arquivos nesses diretórios, bem como alterar `composer.json`, `composer.lock`, `app/`, `config/` ou instalar pacotes.
- Não gerar spec fingindo rotas vivas nem publicar `/up` como contrato de negócio (`/up` é healthcheck operacional).

## Fatos técnicos de transporte e autenticação

1. **Transporte privado aprovado**: O transporte via orquestrador local com `gh` autenticado é o caminho ativo para repositórios privados, dispensando a criação de um PAT para o consumidor frontend.
2. **Consumo sem credenciais**: O frontend consome o documento OpenAPI, tipos TypeScript e receipt duravelmente commitados em seu repositório; os builds de CI (incluindo forks e Dependabot) rodam livres de tokens.
3. **Autenticação vs Attestation**: O receipt valida os metadados do GitHub Actions run e do artefato; não há assinatura criptográfica (notary/cosign) nesta fase.
4. **Scramble 0.13.47**: Suporta PHP 8.5 / Laravel 13; mapeamento Sanctum padrão (Bearer) é inadequado para sessão SPA e exigirá customização no Stage B; `/sanctum/csrf-cookie` é rota vendor-excluded por default e demandará transformer.

