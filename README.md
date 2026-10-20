# 🚀 Starter Kit API

API REST em Laravel 13 sobre PHP 8.5. Autenticação por sessão via Sanctum com CSRF,
gestão de usuários e papéis com RBAC, trilha de auditoria e contrato OpenAPI
versionado, tudo sob o prefixo `/api/v1`.

---

## 📑 Índice

1. [Visão geral](#️-visão-geral)
2. [Stack](#-stack)
3. [Guias](#-guias)
4. [Começo rápido](#️-começo-rápido)
5. [Convenções](#-convenções)

---

## 👁️ Visão geral

A `starter-kit-api` é o backend consumido pelo SPA próprio. Não distribui tokens por
cabeçalho: toda autenticação de primeira parte acontece por sessão em cookie, com
proteção CSRF. A raiz do versionamento responde o estado da API:

```bash
curl --fail http://localhost:8080/api/v1
```

A prontidão operacional é pública em `GET /api/v1/health`: HTTP 200 com o estado de
cada dependência quando tudo está saudável, HTTP 503 com o detalhe por verificação
quando algo falha. O endpoint `/up` do framework indica apenas que a aplicação
inicializou, sem verificar dependências.

---

## 📦 Stack

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
| Cobertura | pcov | Gate de 80% no CI sobre relatórios PostgreSQL e SQLite mesclados |
| Hash de senhas | argon2id | Parâmetros ajustáveis por ambiente |

---

## 📚 Guias

Guias novos, em ordem de leitura:

| # | Guia | Conteúdo |
| --- | --- | --- |
| 01 | [`Instalação`](docs/01-instalacao.md) | Do checkout limpo à API respondendo. Cada comando do setup explicado linha a linha, mais banco de desenvolvimento e saúde. |
| 02 | [`Comandos`](docs/02-comandos.md) | O que cada script Composer, cada Artisan relevante e cada perfil do Compose faz, e quando usar cada um. |
| 03 | [`Arquitetura`](docs/03-arquitetura.md) | Ciclo de vida da requisição, camadas do código, RBAC e auditoria, com desenhos Mermaid por fluxo. |
| 04 | [`Decisões`](docs/04-decisoes.md) | Cada decisão técnica com motivo e alternativa rejeitada: sessão vs tokens, argon2id, throttle, CPF, fila, logs, TLS, imagem e contrato. |
| 05 | [`Integração fullstack`](docs/05-integracao-fullstack.md) | Sequência SPA → CSRF → login → perfil → logout, CORS stateful, `VITE_API_URL` e contrato gerando tipos. |
| 06 | [`Padrões`](docs/06-padroes.md) | Catálogo de design patterns: onde cada um vive no código, ganho, limite e o que evitamos — mais gatilhos de escala/performance. |
| 07 | [`Receitas de features`](docs/07-receitas-de-features.md) | Quero fazer X, por onde parto: CRUD novo, campo, filtro, job, integração externa e permissão, arquivo a arquivo. |
| 08 | [`Arquitetura e escala`](docs/08-arquitetura-e-escala.md) | Desenhos Mermaid: módulos e núcleo, leitura vs escrita, e estágios de escala a nível de código. |

Referências operacionais e arquiteturais existentes:

| Guia | Conteúdo |
| --- | --- |
| [`Onboarding`](docs/onboarding.md) | Guia de desenvolvimento local até a suíte verde. Pré-requisitos, checkout, perfis de teste e assíncrono, gates e OpenAPI. |
| [`Release`](docs/release.md) | Release, deploy e rollback com imagens imutáveis. Configuração do ambiente, pilha de release efêmera, TLS no edge e logs JSON. |
| [`Release do contrato`](docs/contract-release.md) | Fluxo oficial do snapshot OpenAPI: candidato no PR, artifact publicado em `main` e verificação de proveniência. |
| [`Segurança em produção`](docs/security-configuration.md) | Variáveis obrigatórias de produção antes de publicar: origens CORS, domínio de sessão e hosts stateful. |
| [`Receita do contrato`](docs/openapi-contract-recipe.md) | Proveniência do artifact OpenAPI consumido fora deste repositório: download, receipt e verificação. |
| [`Receita de fatia vertical`](docs/vertical-slice-recipe.md) | Entregar um comportamento completo do HTTP à persistência, com TDD, gates e contrato no mesmo slice. |
| [`Exemplo CSD → strict-code`](docs/csd-strict-code-example.md) | Handoff ilustrativo do filtro de usuários: dono por etapa, ponteiros de spec externa, checagens automatizadas vs. manuais e limites explícitos. |
| [`ADRs`](docs/adr/README.md) | Decisões arquiteturais incrementais. Índice dos ADRs aceitos, do scaffold ao catálogo fechado de permissões. |
| [`Insomnia`](docs/insomnia/README.md) | Collection Insomnia v5: 37 requests em 5 pastas, com environment, CSRF e validação live. |

---

## ⚡ Começo rápido

### 1️⃣ Subir a API

```bash
python3 scripts/setup_local_env.py
docker compose build api
docker compose run --rm --no-deps api composer install --no-interaction --prefer-dist
docker compose run --rm --no-deps api php artisan key:generate --no-interaction
docker compose up -d --wait
docker compose exec api php artisan migrate --seed --no-interaction
curl --fail http://localhost:8080/api/v1/health
```

### 2️⃣ Confirmar a saúde

```bash
curl --fail http://localhost:8080/api/v1
```

O passo a passo comentado está em [`docs/01-instalacao.md`](docs/01-instalacao.md).

> Com mais de um checkout na máquina, use `docker compose -p <nome-unico>` em todos os
> comandos, porque o nome de projeto padrão é compartilhado.

---

## 📏 Convenções

O histórico segue commits convencionais em minúsculas (`feat`, `fix`, `docs`, `chore`,
`ci`), com escopo entre parênteses quando a mudança pertence a uma área. Cada entrega
agrupa comportamento, testes, snapshot OpenAPI quando o contrato muda e documentação
afetada. Tags `v*` disparam o workflow de release, mas o artefato publicado sempre
recebe a tag imutável gerada `api-<data>-<sha>`.

Todo arquivo PHP abre com `declare(strict_types=1)`. Form requests validam a entrada
na borda, policies decidem o acesso por objeto e mensagens de erro reutilizáveis vivem
em catálogo central para manter o vocabulário consistente.
