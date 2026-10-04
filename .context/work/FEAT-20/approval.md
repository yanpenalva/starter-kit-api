# FEAT-20 — Decisão de aprovação do work item e autorização excepcional de merge

Data: 2026-10-04 (UTC) · Autoridade: yanpenalva (owner humano) · Escopo: entrega FEAT-20 / API #20 · Branch de entrega: `feat/wave-2-async-recovery` → `main` (remote `origin`), sob [EXC-0001](../../exceptions/EXC-0001.json).

## 1. Decisão registrada

O owner autorizou, de forma **específica e excepcional**, a execução do merge da PR [#34](https://github.com/yanpenalva/starter-kit-api/pull/34) **mesmo com o check externo `GitGuardian Security Checks` no estado `Triggered`/failing** (incidente **37826025**), mediante investigação independente do explorer. Este registro é a documentação formal dessa decisão no work item, conforme o fluxo review-release. **Não constitui extensão permanente da [EXC-0001](../../exceptions/EXC-0001.json) e não altera políticas** para dispensar checks em entregas futuras — cada ocorrência análoga exigirá decisão humana própria.

## 2. Verdade factual sobre os checks (nenhum check "verde" forjado)

No head `08009f7717c8e9e4a77782649c7680c39e15dd6e`:

- `context` — **success**: https://github.com/yanpenalva/starter-kit-api/actions/runs/37210792638/job/111461389511
- `application` — **success** (53 testes / 248 assertions, sem exclusões): https://github.com/yanpenalva/starter-kit-api/actions/runs/37210792638/job/111461389647
- `GitGuardian Security Checks` — **failing** (app externo, dashboard-only): https://dashboard.gitguardian.com

O merge **não** foi executado sob alegação de checks todos verdes; a autorização excepcional do item 1 é o fundamento registrado.

## 3. Bases da decisão

1. **Procedência de `9c5f102` resolvida** (ver [execution.md](execution.md), decisão parent 2026-10-04): explorer comprovou via lifecycle OpenCode (log + DB) autoria pela sessão fixer antiga retomada `ses_efbddf033ffevWJI0m8ZqB6Z2U` (2026-10-04T14:16:28Z), todo o conteúdo em escopo FEAT-20, sem escritores ativos.
2. **Re-review oracle** (independente, não-GitHub): **PASS em `08009f7`** sobre todos os commits/conteúdo — inclui a revisão do workflow CI (serviços Redis/Mailpit efêmeros, pins compose, sem skip/exclusão de testes). Correções F2–F6 de rounds anteriores verificadas como aplicadas.
3. **Evidência técnica auditável**: Pest **53 passed / 0 failed / 248 assertions** (com Redis/Mailpit reais, incl. afterCommit rollback/commit, retry/backoff→failed_jobs, 5 guardas negativas de prod simulado); Pint 58 files; Larastan 10 sem erros; CSD strict/--examples/unittest/doctor.
4. **Scan local independente**: gitleaks (imagem oficial) sobre o range `4f7c74d..4888e89` = **0 leaks**; conteúdo sintético (`.test`, UUIDs, sem credenciais). O finding GitGuardian segue em investigação explorer independente; resultado será anexado quando disponível.
5. **Zero-diff core**: arquivos core-owned sem divergência vs `origin/main` na base revisada; única sobreposição intencional = bloco consentido de 11 linhas em `routes/console.php`.

## 4. Resultado e pós-merge

- Merge executado pelo parent (squash/merge normal, sem `--admin`/`--force`), resultando em `main` @ **`44aa89a29dbda6a16164014c914cc3b411324186`**.
- CI de `main` no SHA do merge — **context: success** (https://github.com/yanpenalva/starter-kit-api/actions/runs/37211538628/job/111463556099) e **application: success** (https://github.com/yanpenalva/starter-kit-api/actions/runs/37211538628/job/111463556181), run `37211538628`.
- Branch `feat/wave-2-async-recovery` preservada (não apagada).
- Issue [#20](https://github.com/yanpenalva/starter-kit-api/issues/20) permanece **aberta/In progress**: consumidores #14/#15 são fatias futuras (ondas 4); a entrega FEAT-20 encerra a fatia de infraestrutura, não a issue inteira.

## 5. Limitações e pendências que esta decisão NÃO resolve

- O aceite do **gate da wave 2** permanece **pendente** do critério "seed idempotente no banco de teste" para o caminho de seed **default** (`migrate:fresh --seed` executado duas vezes, exigência da issue #9): `main` @ `44aa89a` não contém `database/seeders/DatabaseSeeder.php` (verificado nesta worktree). Correção em lane separada (`DatabaseSeeder.php` + teste exclusivo de idempotência). Onda 3 **não** declarada liberada.
- Nenhuma revisão aprovadora formal do GitHub é alegada (revisões por agentes não equivalem a approving review).
- Sem produção, sem segredos, sem force push/reset em todo o ciclo.
