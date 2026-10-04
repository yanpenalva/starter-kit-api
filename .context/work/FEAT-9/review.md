# Review — FEAT-9

## Veredito final

Oracle independente, sessão `ses_f00b14f02ffecUjnB5Iz5v7Epb`: **PASS** após confirmação das correções de CPF/serialização, proteção da fixture e isolamento físico em tmpfs. Estados de revisão pendente abaixo são históricos e superseded. Orquestrador repetiu suite isolada: 17 testes, 74 assertions; runtime confirmou cache/sessões/testing em tmpfs. Nenhuma aprovação formal GitHub é simulada. Checks do PR e integração permanecem gates separados; issue #9 continua progressiva.

Autor: implementação fiel à spec/preflight (estados independentes, CPF parcial-úniko aprovado, concorrência obrigatória coberta, sem escopo de wave 2+). Verificação real em verification.md.

## Revisão independente

**Pendente** — aguarda revisão do oracle/pai antes da integração. Nenhum PASS é afirmado aqui.

## Re-review Oracle (ses_f00b14f02ffecUjnB5Iz5v7Epb) — PASS condicional; bloqueador F1 no FEAT-10

- **F1 (bloqueador de merge, FEAT-10)**: fixture só por flag-file permitiria rota em produção se o arquivo vazasse. Disposição: ver [FEAT-10 review](../FEAT-10/review.md) — gate duplo (ambiente testing|local **E** flag) + regressão de servidor HTTP real em APP_ENV=production com flag presente provando 404.
- **F2 (FEAT-9)**: `withCpf()` não normalizava entrada formatada. Corrigido: normalização antes do state, zeros à esquerda preservados, semântica opcional/null mantida; regressão `withCpf('529.982.247-25')` persiste `52998224725` e valida.
- **F3 (FEAT-9)**: `cpf` adicionado a `$hidden`; teste de serialização estendido (toArray + jsonSerialize) — leitura autorizada futura fica no Resource da wave dona.
- Nenhum confirmatory PASS é afirmado; aguarda re-check do revisor.

## Re-review da fatia seed (cav, via parent) — PASS (2026-10-04)

- **Objeto**: correção de guardas em `DefaultDatabaseSeederTest` — fonte `f796ed32d5366d59c1686b69b9c5713491bd5b78` (lane seed), consolidada via cherry-pick `-x` como `5aa7380` na branch de gate `docs/wave-2-gate` (PR #35), codeeq provada.
- **Histórico**: re-review anterior da fonte `2445e44` = **FAIL** (DB_URL herdada podia sobrepor o alvo; sem guard de paralelismo) — preservado acima, superseded pela correção.
- **Veredito: PASS.** Evidência: suíte 57 passed / 293 assertions (incl. 2 adversariais: DB_URL smuggled recusado; **TEST_TOKEN não vazio, inclusive string "0", recusa runner paralelo; ausente/vazio permitido**); CI PR #35 head `aedac01` context/application/GitGuardian success (run `37214604864`, jobs `111472456056`/`111472456188`).
- **Integração**: merge normal da PR #35 pelo parent (EXC-0001; **sem waiver de GitGuardian** — check passou por si): `main` @ `5604bf5e6fbbdb2c043780c82580f1cabf26293d`; CI `main` success (run `37215264091`).
- **Artefato aceito em `main`**: seeder default exclusivo do catálogo (8 permissions / 2 roles / 0 users / 0 assignments; nenhum admin/credencial default; banco somente testing).
- **Gate da onda 2: PASS.** Onda 3 pronta para execução (não iniciada). Issue #9 permanece aberta (progressiva; fechamento = parent no aceite completo).
