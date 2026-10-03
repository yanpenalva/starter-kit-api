# Versioned Handoff — FEAT-6

## State

- Current phase/status: verify; wave 0 iniciada e **incompleta**. Nenhum commit/push/PR remoto executado nesta fatia de correção (proibido ao fixer neste passe).
- Approved scope: roadmap Starter Kit (API + frontend) sob [EXC-0001](../../exceptions/EXC-0001.json) (até 2026-12-31 ou encerramento/revogação anterior) e classificação `internal` aprovada ("Autorizar conteúdo interno"). Produção, destrutivo, tags, novo escopo e mudança de risco permanecem exclusões com decisão humana.
- Decisions: API #2/#3 Done — PR #30 merged 2026-10-02T23:34:49Z (head 99321a9, merge 1d45f75), fechamento confirmado pelo humano, sem exigir novo aceite de contrato; EXC-0001 aprovada ("Aprovar exceção"); revisão independente inicial do Oracle = PASS condicionada a F1/EXC-0001 (cumprida); "reviewer NOT FOUND" do preflight original superseded; harness observado é OpenCode (especialistas explorer/librarian/oracle/fixer/designer; modelo `openai/gpt-6.1-sol` observado apenas no pai, sem IDs de modelo inventados para especialistas e sem instalação de Oh My OpenCode).
- Files and contracts: `.context/project/roadmap.md` (estado corrigido, 47 links preservados), `.context/exceptions/EXC-0001.json`, `.context/config.json` (`ai.data_classification="internal"`), `.context/project/ai-governance.md` e `delivery.md` (registros append), FEAT-2/FEAT-3 reconciliados (close/completed), FEAT-6 approval/preflight/review/spec/verification/progress atualizados.
- Commands and evidence: ver "Correção pós-merge e wave 0" em [verification.md](verification.md) — evidência remota atribuída ao pai + gates desta fatia.
- Failures and risks: aplicação não reexecutada (Docker/Compose acessíveis, sem rerun); `main` sem proteção (rulesets [], 404 em branch protection) — gates dependem da disciplina CSD; approving review formal por outra identidade indisponível — nunca forjar.
- Next step: concluir pendências da wave 0 (SPA #1, API #4/#6, fatia #24, prep #22), aplicar gate e liberar wave 1. API #6 permanece aberta; quadro já reflete In progress somente de #6 e SPA #1.
