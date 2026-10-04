# Preflight — FEAT-20

Data: 2026-10-03 · Revisor: fixer (frente async) · Fase original: pré-implementação (worktree temporário original)

## Checks

- [x] Request and objective are clear — roadmap onda 2, API #20; pacote da onda 2 aprovado pelo humano (`.context/work/FEAT-6/approval.md`, leitura read-only).
- [x] Scope and out of scope agree — `spec.md` (sem Horizon/Telescope/Pulse, sem #14/#15, sem produção).
- [x] Evidence supports proposed behavior — `discovery.md` (fatos verificados na base `065ff90`).
- [x] Plan touchpoints exist or are explicitly approved as new — arquivos-alvo existem; migration 000004 reservada e comunicada ao integrador.
- [x] Security and privacy impact is addressed — sem segredos, sem PII (destinatários sintéticos `*.test`), sem porta pública nova, logging/PII intocáveis.
- [x] Tests and acceptance criteria are executable — `spec.md` aceite + `plan.md` passos.
- [x] Rollback and operational ownership are defined — `down -v` restrito ao projeto `starter-wave2-async`; owner humano yanpenalva.
- [x] Required human approvals are recorded — pacote da onda 2 aprovado; EXC-0001 cobre branch registrada → `main`.
- [x] Applicable core policies were checked — code-quality, testing, delivery.
- [x] Quality commands identified — `plan.md` seção Validação.
- [x] AI/data/tool permissions addressed — dados `internal` minimizados; `.env`/dumps/segredos excluídos.
- [x] Subtasks, owners, dependencies and waves explicit — `plan.md` (Subtasks and waves).

## Findings (originais)

- Colisão real de projeto Compose confirmada → isolamento `-p starter-wave2-async` + tag de imagem própria.
- phpredis PECL estável 6.3.0 (consulta ao vivo); composer sem conflito.
- Limitações: nenhum serviço instalado/executado na fase de planejamento.

## Adendo — provenância da autorização de execução (registro honesto, 2026-10-03)

O veredito `READY` acima era autodeclarado pelo implementador (fixer) e NÃO constituía registro independente. O gate real de pré-código foi o **Oracle PASS independente** registrado NA CONVERSA (sessão `ses_f00b14f02ffecUjnB5Iz5v7Epb`), proferido ANTES do dispatch EXECUTE do orquestrador e antes de qualquer código/instalação. Regulações exatas vigentes naquele veredito:

- Projeto Compose isolado `-p starter-wave2-async` em TODA operação Docker/teste; imagem própria via `API_DEV_IMAGE=starter-wave2-async-api:local` (default do core inalterado);
- `.env` primário como `--env-file` apenas em runtime de containers — nunca lido, copiado ou impresso no contexto; nenhum segredo em saídas;
- phpredis pin `6.3.0` (PECL estável) com prova de build; sem troca de cliente em caso de falha de compilação;
- Perfil de teste isolado (redis-test/mailpit-test em rede interna, sem portas públicas); tmpfs do PG de teste preservado; 17 testes base devem permanecer verdes.

Este adendo NÃO fabrica aprovação retroativa nem afirma que o Oracle "aconteceu agora": documenta a existência e o conteúdo do veredito real preexistente, corrigindo a lacuna de independência. Nenhuma decisão humana nova foi necessária (defaults de engenharia dentro do escopo já aprovado).

## Verdict

`READY` — para **iniciar implementação após gate do orquestrador**, sob as condições registradas. Nenhuma evidência de execução/runtime foi alegada na fase de planejamento.

Reviewer: `fixer (frente async FEAT-20)` · Date: `2026-10-03`
