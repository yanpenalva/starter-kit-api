# Plan — FEAT-20 (API #20)

Registro de entrega: branch `feat/wave-2-async-recovery` (recuperação; branch original `feat/wave-2-async` perdeu o worktree sem commit de fonte) → alvo `main`, remote `origin`, EXC-0001.

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| FEAT-20-PLAN | fixer (frente async) | none | discovery/spec/plan/preflight FEAT-20; gate do orquestrador; reserva migration 000004 comunicada ao integrador | 1 | core,project,architecture,testing |
| FEAT-20-IMPL | fixer (frente async) | FEAT-20-PLAN | build phpredis 6.3.0; Pest 30/30; smoke worker/scheduler; guardas F1–F4 | 2 | core,project,architecture,testing |
| FEAT-20-RECOVERY | fixer (frente async) | FEAT-20-IMPL | reconstrução no root persistente + gates frescos (build/pest/static/CSD) após perda do worktree temporário | 3 | core,project,architecture,testing |
| FEAT-20-VERIFY | orquestrador + revisor independente | FEAT-20-RECOVERY | Larastan 10/Pint/CSD strict + revisão independente no SHA final | 4 | core,project,testing |
| FEAT-20-INTEGRATE | integrador (dono de composer/migrations numeradas) | FEAT-20-VERIFY | merge em main com checks no SHA final; PR criado pelo parent | 5 | core,project |

## Passos (executados; F1–F4 aplicados)

1. Dockerfile: `pecl install redis-6.3.0` + enable.
2. compose.yaml: `API_DEV_IMAGE` parametrizável; worker/scheduler async limitados com healthcheck real; redis-test/mailpit-test internos; anchor env seguro (log/sync, sem hosts async).
3. config/queue.php: after_commit env-gated default FALSE; config/mail.php: infra_smoke (.test + mailpit_api_url); .env.example: 3 chaves aprovadas com defaults seguros.
4. Migration 000004 failed_jobs (schema padrão).
5. Comandos dev (probe com guarda, smoke-mail, fail-job) + mailable/job sintéticos.
6. routes/console.php: UMA linha guardada local/testing (consentida pelo parent).
7. Testes: Concern + 5 classes Feature (13 testes async, incl. 3 guardas negativas de prod simulado); worker efêmero por teste.
8. scripts/async_dev.sh (up/smoke/cleanup via worker).
9. Docs FEAT-20 (.context/work/FEAT-20/*) com recovery record.

## Limites de propriedade

- **Nosso**: Dockerfile, compose.yaml, config/queue|mail (chaves FEAT-20), migration 000004, app/Console/Commands + app/Mail + app/Jobs sintéticos, scripts, testes async, .context/work/FEAT-20, .env.example (3 chaves), linha guardada em routes/console.php (consentida).
- **Núcleo (intocável)**: composer.json/lock, User, bootstrap, providers, demais rotas, logging/PII, migrations 000002/000003.

## Validação

`composer validate --strict`, `check-platform-reqs`, `audit`, `lint`, `analyse`; `--profile test` Pest no projeto isolado; smoke async; `validate_context.py --strict`(+`--examples`); unittest; doctor; PHP 8.5; sem `else`; types imutáveis; CSD obrigatório.

## Riscos e rollback

- Risco: colisão Docker entre lanes — mitigado por `-p starter-wave2-async` + imagem própria; rollback: `down -v` do projeto isolado (primário intacto).
- Risco: perda de worktree temporário — mitigado por local commit checkpoint + push da branch de recuperação (EXC-0001); PR fica para o parent.

## Aprovações necessárias

Pacote onda 2 (aprovado); EXC-0001 (approved); consentimento parent para linha única em routes/console.php (registrado); revisão independente pré-integração (pendente).
