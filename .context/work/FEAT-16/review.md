# Review — FEAT-16

## Veredito final de publicação

Oracle independente, sessão `ses_f00b14f02ffecUjnB5Iz5v7Epb`: **PASS** após verificar os deltas B1/B2/R1 em código. Pendências abaixo são snapshots superseded. Regressões explícitas de correlação em produção/local foram posteriormente adicionadas em CorrelationRedactionTest (linhas 58 e 76), corrigindo a alegação anterior ausente, e suite passou 38 testes/199 assertions. Checks do PR e integração são gates separados; agente não concede aprovação formal GitHub.

Autor: implementação fiel ao preflight/rulings (envelope mínimo, fail-closed, locks, redação); detalhes em verification.md.

## Revisão independente

**Pendente** — aguarda o re-check do oracle/pai antes da integração. Nenhum confirmatory PASS é afirmado aqui.

## Re-review Oracle — B1/B2/R1 (2026-10-03): FAIL anterior → fixes aplicadas

- **B1**: gate de elegibilidade (verificado E não-desativado) como primeiro guard em TODOS os métodos de UserPolicy e RolePolicy (helper privado `canOperate`), independente de permissão/role. Negativas novas para view/update/readCpf/setStatus/assignRoles + roles.view/update-permissions com atores unverified e deactivated (matriz completa).
- **B2**: `assignRoles` nega QUALQUER auto-atribuição (`$actor->is($target)`) mesmo sem role admin, mantendo last-admin/estado; regressão com ator verificado não-admin + permissão direta mirando a si = false.
- Fábricas positivas usam estado `verified()` explícito; testes negativos usam unverified/deactivated reais. Nenhum Gate::before global; endpoints públicos futuros não afetados.
- R1 (correlação): ver [FEAT-21 review](../FEAT-21/review.md).
- Nenhum confirmatory PASS afirmado; aguarda re-check do revisor.
