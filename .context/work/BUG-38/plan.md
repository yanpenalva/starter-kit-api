# Plan — BUG-38 Corrigir race condition no teste de retry/backoff do queue worker

## Approved approach

Adicionar o modificador `--once` nos argumentos adicionais (`extra`) da primeira chamada de `$this->runBoundedWorker(tries: 2, extra: ['--backoff=1', '--once']);` em `tests/Feature/InfraQueueFailureTest.php`. Isso instrui o artisan `queue:work` a invocar `runNextJob` em vez do loop contínuo de daemon, garantindo que o worker processe unicamente o primeiro job e encerre sem oportunidade de race condition com a expiração do atraso de 1 segundo.

## Confirmed touchpoints

- Files/components/services: `tests/Feature/InfraQueueFailureTest.php`, `.context/project/conventions.md`, `.context/work/BUG-38/*`.
- Interfaces and data: Nenhuma alteração de schema ou contrato de banco/fila.
- Dependencies: Laravel Framework queue worker options.

## Execution order

1. Atualizar `.context/project/conventions.md` com a nova convenção permanente de idioma/anotações.
2. Aplicar `--once` no primeiro worker em `tests/Feature/InfraQueueFailureTest.php`.
3. Executar rodadas repetidas de testes dos componentes de infraestrutura de fila em container isolado.
4. Executar toda a suíte de qualidade (Composer, Pint, Larastan, Pest, CSD).
5. Documentar evidências e revisão.

## Subtasks and waves

| Subtask ID | Owner | Dependencies | Acceptance evidence | Wave | Context domains |
| --- | --- | --- | --- | --- | --- |
| S1 | support-incident-engineer | none | Inclusão de `--once` no teste e atualização de convenção no contexto | 1 | project,testing |
| S2 | quality-engineer | S1 | Suíte completa e testes repetidos de fila verdes em stack isolada | 2 | testing |

## Test plan

- Execução múltipla de `InfraQueueFailureTest` e `InfraMailDeliveryTest` com Redis real.
- Execução completa de `composer test` (Pest).
- Execução de `composer lint` (Pint) e `composer analyse` (Larastan nível 10).
- Execução dos validadores CSD (`validate_context.py`, unittests, doctor).

## Risks and rollback

- Risk: Mínimo (escopo restrito a teste).
- Mitigation: Não afeta código de produção ou fixtures compartilhadas.
- Rollback: Reverter commit na branch `docs/wave-3-auth-accepted`.

## Required approvals

- EXC-0001 (autorização condicional de roadmap/entrega).
