# Preflight — FEAT-21

**Revisão independente Oracle (ses_f00b14f02ffecUjnB5Iz5v7Epb): PASS** (2026-10-03). Dono humano yanpenalva; Git automático sob [EXC-0001](../../exceptions/EXC-0001.json) (`feat/wave-2-identity-audit` → `main` @origin).

Rulings aplicados como mínimo de corretude:

- Cache: `forgetCachedPermissions()` explícito só após commit; relações de User recarregadas (sem relações stale).
- Seeders: firstOrCreate/upsert idempotentes (2 execuções estáveis); SEM atribuição de role a usuários; SEM contas admin seedadas.
- Auditoria: síncrona na MESMA transação PG, buffer OFF; `attribute_changes` vazio; propriedades mínimas (correlation/ator CLI ou userId/subjectId/tempo/evento permitido apenas); nunca CPF/email/password/token/payload; auditoria não pode ser silenciosamente pulada — insert real verificado ou rollback; falha de audit reverte a mutation (teste obrigatório em todas as ações privilegiadas).
- Bootstrap: ID explícito, verificado, não-desativado, ativo; rejeita inexistente/não-verificada/desativada; idempotente; produção default-deny sem `--confirm-production` (env simulado em teste apenas; nenhuma conta real); lock `FOR UPDATE` no alvo + lock global (advisory xact) para serializar mudanças de admin entre usuários (last-admin concurrency-safe); prova com segunda conexão PDO e `lock_timeout` curto.
- Negação de self-elevate/self-deactivation e role admin imutável em mutations protegidas (fundação de domínio/policies; sem endpoints públicos); auto-assign do role `user` só na wave 4.
- Correlation: `X-Correlation-Id` validado como UUID (sem trust widening, sem log-forgery); sem IP cru/PII; redação positiva/negativa testada com dados sintéticos.
- Retenção 90 dias configurada agora; cleanup/feed HTTP só wave 6.
- Lane async (outro worktree) é dona de Compose/Dockerfile/queue/cache/mail/config-database Redis/`*_000004_failed_jobs`/FEAT20 — intocados aqui; testes com `-p starter-wave2-core`.

Fase avança para execute.
