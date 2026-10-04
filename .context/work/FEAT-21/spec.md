# Specification — FEAT-21 (primeira fatia, wave 2)

Logs estruturados e seguros em torno dos fluxos privilegiados da wave 2 (bootstrap/privilege change): `correlation_id` propagado (gerado no fluxo e incluído na auditoria + contexto de log estruturado), redação garantida — sem CPF, email, credenciais, tokens ou payload nos registros; canais padrão Laravel (stderr no container), sem ferramenta nova.

- Middleware/contexto de log: correlação por request/CLI (header `X-Correlation-Id` aceito e propagado em dev/teste; gerado quando ausente), incluído via contexto de log; sem host/proxy trust widening.
- Integração: bootstrap CLI e mutations de privilégio logam com correlation_id e sem valores sensíveis (testes afirmam ausência de CPF/email/segredos nas saídas).
- Ferramentas extras: nenhuma (só quando necessidade real justificar, waves futuras). Retenção/rotação de produção: `NOT FOUND` (Marco B).

## Entrega

Branch `feat/wave-2-identity-audit` → `main` @origin; [EXC-0001](../../exceptions/EXC-0001.json). Testes: correlation_id consistente entre log e auditoria; redação em mensagens/erros; sem PII em stderr capturado.
