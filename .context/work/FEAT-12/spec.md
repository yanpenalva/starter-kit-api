# Specification — FEAT-12 (fatia wave 2)

Comando CLI restrito (`php artisan app:promote-admin`) que atribui o role `admin` a uma conta explicitamente escolhida, **verificada e ativa**, por ID (não email). Regras aprovadas ([FEAT-6 approval.md](../FEAT-6/approval.md)):

- Idempotente (re-execução não duplica nem falha), transacional com auditoria `cli.bootstrap`/`privileged.change` na mesma transação PG; falha de auditoria reverte tudo (rollback atômico, fail-closed).
- Restrições: conta inexistente/não-verificada/desativada → rejeitada; IDs restritos ao alvo; **produção default-deny** (flag explícita `--confirm-production` exigida, sem credenciais padrão); **nenhuma promoção real nesta onda** — só testes com factories.
- Concorrente-seguro (lock da conta alvo; dupla execução idempotente). Cache de permissões invalidado + relações fresh após commit.
- Sem segredos em logs/saída; sem PAT; sem CRUD; config de produção continua `NOT FOUND`.

## Entrega

Branch `feat/wave-2-identity-audit` → `main` @origin; [EXC-0001](../../exceptions/EXC-0001.json). Testes: feliz (escolhida+verificada+ativa), rejeições (inexistente/não-verificada/desativada), idempotência, rollback por falha de auditoria, default-deny em produção (env simulation), concorrência.
