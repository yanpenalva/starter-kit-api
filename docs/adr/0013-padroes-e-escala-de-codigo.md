# ADR 0013 — Padrões de código e escala sem reescrita

- Estado: proposto (aguarda aprovação de yanpenalva).
- Reafirma: ADR 0008 (catálogo fechado), ADR 0011 (mutações privilegiadas com auditoria fail-closed).
- Data: 2026-10-15. Owner: yanpenalva.
- Evidência: análises de código em `app/Actions`, `app/Support`, `app/Policies` e `app/Models/User.php`; despacho pós-commit com `->afterCommit()` e regressões em `tests/Feature/AfterCommitDispatchTest.php`. Nenhum benchmark de carga foi executado: ganhos de escala abaixo são projeções com gatilhos de medição, não resultados.

## Contexto

Com o catálogo RBAC, a auditoria transacional e as Actions consolidadas, a
próxima pergunta é estrutural: que padrões adotar oficialmente, o que recusar, e
como o código escala sem reescrita. O detalhamento vive em
`docs/06-padroes.md`; as receitas de aplicação, em `docs/07-receitas-de-features.md`.

## Decisão

- Actions com `run()` são o Command adotado: o framework é o invoker, sem
  interface por Action e sem `UserService` gordo por entidade.
- Filtros de listagem são Query Objects com allowlist e guard por driver
  (`app/Support/Filters/*`); sem Repository genérico sobre o Eloquent e sem
  Specification genérica.
- Variação por driver é seleção condicional encapsulada (`EmailLookup`,
  `PrivilegeTransaction`), não hierarquia Strategy.
- Notifications com efeito externo usam `->afterCommit()` no ponto de disparo.
- Escala de código segue gatilhos medidos (`docs/06-padroes.md`, seção de
  escalabilidade): `simplePaginate`/`cursorPaginate`, índice `pg_trgm`,
  seção crítica curta no lock advisory, invalidação RBAC enxuta, store
  compartilhado para TOTP, fila/cache Redis sob pressão. Troca de trecho do
  Eloquent (`toBase()->lazyById()`, agregação no banco) só com perfil provando
  hidratação dominante — nunca a camada inteira.
- Microserviços, event sourcing, Command Bus e Outbox permanecem recusados até
  evidência explícita em contrário.

## Consequências e verificação

- PRs que introduzam Repository genérico, Singleton manual, AbstractFactory
  especulativa ou DTO sem reuso HTTP+CLI+Job devem ser recusados com referência a
  este ADR.
- Cada gatilho de escala vira PR próprio com medição antes/depois
  (`EXPLAIN (ANALYZE, BUFFERS)`, p95, throughput); sem medição, não entra.
- Verificação atual: `AfterCommitDispatchTest` (rollback 0 / commit N com
  `after_commit=false`), `FiltersTest` nos dois drivers, gates `composer lint`,
  `composer analyse` e cobertura 100% no merge pgsql+sqlite.
