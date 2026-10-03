# Specification — FEAT-4

## Scope

Primeira fatia documental (wave 0): definir os quality gates progressivos e smoke tests da API, com rótulo explícito de estado por gate. Nenhum workflow, comando novo ou runtime é criado nesta fatia; o CI existente é preservado verbatim.

## Gates implementados (observados; não recriar)

- Workflow `quality.yml`: jobs de PR e `main` com PHP 8.5, PostgreSQL 18 isolado, Composer install pelo lock, validate/audit, Pint, Larastan nível 10 sem baseline, Pest; contexto CI com validação estrita, testes Python de regressão e doctor CSD fixado. Permissões de repositório somente leitura para os jobs context+app.
- Locais equivalentes documentados em [quality.md](../../project/quality.md): Composer validate/check-platform-reqs/audit, lint (Pint), analyse (Larastan 10), Pest contra PostgreSQL isolado, validação CSD (strict `--examples`, unittest, doctor).
- Testes atuais do scaffold cobrem boot, JSON de `/api/v1`, `/up` e fronteiras de rota: exatamente dois endpoints GET expostos; nenhum runtime de auth/Sanctum/RBAC/auditoria/OpenAPI existe.
- Dependências: `composer audit` sem advisories observado nos runs de CI (FEAT-24).

## Gates planejados (proposta Oracle, advisory — NÃO implementados)

Adições registradas como planejadas, cada uma mapeada à wave/issue dona; nenhuma é alegada como passando hoje:

| Gate planejado | Conteúdo | Dona (issue/wave) |
| --- | --- | --- |
| Erros JSON forçados, sem trace/depuração | Respostas de exceção em JSON sem stack trace; `APP_DEBUG=false`; `trustHosts=false` | #10, wave 1 |
| Compose de teste isolado | PG 18 em tmpfs, sem sobreposição com dev, credenciais efêmeras, portas localhost, imagens fixadas por digest | #8/#9, waves base/1 |
| Serviços assíncronos isolados por profile | Redis/Mailpit somente como serviços de profile assíncrono; sem worker/scheduler/integração real; queue driver `sync`, session/cache `file` (dev) e `array` (teste) | #20, wave 2 |
| Autorização e conta (negativos) | 403 negócio para não verificado/perfis vazios, sessão desativada, CSRF 419, fixação de sessão, não enumeração em login/reset | #13/#14/#15, waves 3/4 |
| CPF e RBAC (negativos) | Null/normalização/validação/unicidade/concorrência de CPF; leitura por titular/permissão específica; sem PII em erros/logs; rollback atômico de auditoria; bootstrap estrito | #9/#12/#16/#19, waves 1/2/6 |
| OpenAPI e compatibilidade | Geração por Scramble, checagens de sintaxe/compatibilidade, publicação pela [política de transporte privado](../../project/api.md) | #11, wave 3 |
| Consolidação de segurança | Testes de abuso reais da matriz de [FEAT-22](../FEAT-22/spec.md) | #22, wave 7 |

## Out of scope

- Criar, editar ou reexecutar workflows/CI; alterar runtime, dependências, lockfiles, migrations.
- Alegar testes negativos futuros como passando; alegar prova manual de timing uniforme.
- Cobertura percentual: scaffold sem lógica de negócio; expectativa por feature permanece em revisão.

## Acceptance

- Todo comando citado existe em quality.md, QUALITY_GATES.md ou no workflow existente; nenhum check inventado é reportado como passando.
- Cada gate tem rótulo implementado (com evidência atribuída) ou planejado (com issue/wave dona).
- CI existente preservado verbatim; fatia é aditiva e documental.
- Issue #4 permanece aberta/progressiva até a wave 7; esta fatia não a fecha.
