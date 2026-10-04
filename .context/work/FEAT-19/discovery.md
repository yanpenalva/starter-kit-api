# Discovery — FEAT-19

API #19 progressiva; primeira fatia = somente escrita de auditoria fail-closed (Activitylog 5.1.1, mesma transação PG, envelope mínimo aprovado, sem auto-log de User). Feed/leitura wave 6; retenção 90 dias configurada, cleanup wave 6. Base: ADR 0005 e decisão da wave 2.

Público: yanpenalva e executores/revisores. Falha = confundir catálogo aprovado com runtime entregue; o que está fora da fatia permanece na issue progressiva.
