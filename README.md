# starter-kit-api

API REST em Laravel 13 sobre PHP 8.5. Autenticação por sessão via Sanctum com CSRF,
gestão de usuários e papéis com RBAC, trilha de auditoria e contrato OpenAPI
versionado, tudo sob o prefixo `/api/v1`.

## Visão geral

A `starter-kit-api` é o backend consumido pelo SPA próprio. Não distribui tokens por
cabeçalho: toda autenticação de primeira parte acontece por sessão em cookie, com
proteção CSRF. A raiz do versionamento responde o estado da API:

```sh
curl --fail http://localhost:8080/api/v1
```

A prontidão operacional é pública em `GET /api/v1/health`: HTTP 200 com o estado de
cada dependência quando tudo está saudável, HTTP 503 com o detalhe por verificação
quando algo falha. O endpoint `/up` do framework indica apenas que a aplicação
inicializou, sem verificar dependências.

## Stack

| Camada | Tecnologia | Versão / detalhe |
| --- | --- | --- |
| Linguagem | PHP | 8.5 (`php:8.5-fpm-bookworm` no Docker) |
| Framework | Laravel | 13.17 |
| Autenticação | Sanctum stateful | Sessão em cookie mais CSRF |
| Autorização | Spatie Permission | Catálogo fechado de 10 permissões |
| Auditoria | Spatie Activitylog | Trilha fail-closed, retenção de 90 dias |
| Documentação da API | Scramble | OpenAPI 3.1.0, snapshot em `contracts/openapi.json` |
| Banco de dados | PostgreSQL | 18 (`postgres:18-alpine`) |
| Fila | Banco de dados | Driver `database` com `after_commit` |
| Cache e sessões em release | Redis | Extensão `redis-6.3.0` |
| E-mail local | Mailpit | SMTP em `mailpit:1025`, interface em `127.0.0.1:8025` |
| Servidor HTTP local | Nginx | `nginx:1.29-alpine` como proxy reverso para o PHP-FPM |
| Testes | PHPUnit | Contra PostgreSQL isolado |
| Formatação | Pint | Preset `laravel` |
| Análise estática | PHPStan via Larastan | Nível 10, sem linha de base |
| Cobertura | pcov | Gate de 85% no CI |
| Hash de senhas | argon2id | Parâmetros ajustáveis por ambiente |

## Índice da documentação

Guias novos, em ordem de leitura:

- [`docs/01-instalacao.md`](docs/01-instalacao.md) — Do checkout limpo à API respondendo.
  Cada comando do setup explicado linha a linha, mais banco de desenvolvimento e saúde.
- [`docs/02-comandos.md`](docs/02-comandos.md) — O que cada script Composer, cada Artisan
  relevante e cada perfil do Compose faz, e quando usar cada um.
- [`docs/03-arquitetura.md`](docs/03-arquitetura.md) — Ciclo de vida da requisição, camadas
  do código, RBAC e auditoria, com desenhos Mermaid por fluxo.
- [`docs/04-decisoes.md`](docs/04-decisoes.md) — Cada decisão técnica com motivo e alternativa
  rejeitada: sessão vs tokens, argon2id, throttle, CPF, fila, logs, TLS, imagem e contrato.
- [`docs/05-integracao-fullstack.md`](docs/05-integracao-fullstack.md) — Sequência SPA →
  CSRF → login → perfil → logout, CORS stateful, `VITE_API_URL` e contrato gerando tipos.

Referências operacionais e arquiteturais existentes:

- [`docs/onboarding.md`](docs/onboarding.md) — Guia de desenvolvimento local até a suíte verde.
  Pré-requisitos, checkout, perfis de teste e assíncrono, gates e OpenAPI.
- [`docs/release.md`](docs/release.md) — Release, deploy e rollback com imagens imutáveis.
  Configuração do ambiente, pilha de release efêmera, TLS no edge e logs JSON.
- [`docs/security-configuration.md`](docs/security-configuration.md) — Variáveis obrigatórias
  de produção antes de publicar: origens CORS, domínio de sessão e hosts stateful.
- [`docs/adr/README.md`](docs/adr/README.md) — Decisões arquiteturais incrementais.
  Índice dos ADRs aceitos, do scaffold ao catálogo fechado de permissões.

## Começo rápido

```sh
python3 scripts/setup_local_env.py
docker compose build api
docker compose run --rm --no-deps api composer install --no-interaction --prefer-dist
docker compose run --rm --no-deps api php artisan key:generate --no-interaction
docker compose up -d --wait
docker compose exec api php artisan migrate --seed --no-interaction
curl --fail http://localhost:8080/api/v1/health
```

O passo a passo comentado está em [`docs/01-instalacao.md`](docs/01-instalacao.md).
Com mais de um checkout na máquina, use `docker compose -p <nome-unico>` em todos os
comandos, porque o nome de projeto padrão é compartilhado.

## Convenções

O histórico segue commits convencionais em minúsculas (`feat`, `fix`, `docs`, `chore`,
`ci`), com escopo entre parênteses quando a mudança pertence a uma área. Cada entrega
agrupa comportamento, testes, snapshot OpenAPI quando o contrato muda e documentação
afetada. Tags `v*` disparam o workflow de release, mas o artefato publicado sempre
recebe a tag imutável gerada `api-<data>-<sha>`.

Todo arquivo PHP abre com `declare(strict_types=1)`. Form requests validam a entrada
na borda, policies decidem o acesso por objeto e mensagens de erro reutilizáveis vivem
em catálogo central para manter o vocabulário consistente.
