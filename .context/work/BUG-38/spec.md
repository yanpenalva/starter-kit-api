# Specification — BUG-38 Corrigir race condition no teste de retry/backoff do queue worker

## Request

- Source: GitHub Actions CI run 37232150173 on PR #38
- Requester: CI runner / user prompt AUTH-RETRY
- Date: 2026-10-04

## Objective

Garantir determinismo absoluto no teste `test_failing_job_is_retried_with_backoff_then_stored_in_failed_jobs` de `tests/Feature/InfraQueueFailureTest.php`, assegurando que a primeira execução do worker execute exatamente uma tentativa, retendo o job na estrutura delayed-zset do Redis e mantendo `failed_jobs` com zero registros antes do intervalo de backoff.

## Scope

- Alteração pontual no teste `tests/Feature/InfraQueueFailureTest.php` adicionando `--once` na invocação do primeiro worker.
- Documentação da convenção de projeto atualizada em `.context/project/conventions.md`.
- Registro do artefato de suporte CSD em `.context/work/BUG-38/`.

## Out of scope

- Alteração da implementação de filas da aplicação (`App\Jobs\InfraFailingJob`, `config/queue.php`, etc.).
- Remoção do atraso ou redução da espera real de backoff.
- Utilização de mocks, fakes ou drivers em memória no teste de infraestrutura assíncrona.
- Migração global de convenção de código fora do escopo estrito do bug.
- Modificação de outros worktrees ou serviços em execução.

## Evidence and context

- Repository evidence: `tests/Feature/InfraQueueFailureTest.php`, `tests/Concern/RunsAsyncInfraStack.php`.
- Product or incident evidence: GitHub Actions run 37232150173, job 111523972664.
- Relevant project context: `.context/project/quality.md`, `.context/project/conventions.md`, `.context/exceptions/EXC-0001.json`.
- Unknowns: `NOT FOUND`.

## Rules and contracts

- Preservar Redis real, delayed-zset e persistência em `failed_jobs`.
- Primeira execução: exatamente UMA tentativa (`--once`).
- Segunda execução: após expiração da janela de 1s de backoff, drenar o retry e comprovar falha final gravada em `failed_jobs`.

## Tests and acceptance criteria

- `tests/Feature/InfraQueueFailureTest.php` e `tests/Feature/InfraMailDeliveryTest.php` executados repetidas vezes com backend real e resultado PASS.
- Composer validate/audit/check-platform-reqs/lint/analyse/test PASS.
- CSD validator e unittests PASS.

## Open decisions

- Nenhuma. A solução com `--once` é a abordagem canônica suportada pelo Laravel (`artisan queue:work --once`).
