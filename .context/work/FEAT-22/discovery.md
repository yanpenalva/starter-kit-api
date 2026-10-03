# Discovery — FEAT-22

[API #22](https://github.com/yanpenalva/starter-kit-api/issues/22) é progressiva: a preparação inicial identifica riscos e cenários agora; a conclusão depende dos fluxos reais das waves seguintes, e o fechamento só ocorre na wave 7 com testes de abuso reais. O board já está em In progress, movido pelo pai antes desta fatia.

Base usada: ADRs aceitos [0002](../../../docs/adr/0002-autenticacao-spa.md) (Sanctum/sessão SPA), [0004](../../../docs/adr/0004-identidade-rbac.md) (cadastro, verificação, ativação, CPF, RBAC), [0005](../../../docs/adr/0005-auditoria.md) (Activitylog v5, retenção 90 dias); [contrato da API](../../project/api.md); e o runtime real do scaffold — exatamente dois endpoints GET (`/api/v1`, `/up`) com testes atuais, Compose isolado de teste. Nenhum auth/Sanctum/RBAC/auditoria/OpenAPI em runtime; nenhum worker/fila real (Redis/Mailpit são serviços de profile assíncrono sem integração).

A matriz abaixo é o estado inicial documental derivado da revisão Oracle advisory; cada linha planejada mapeia à issue/wave dona do controle e do teste negativo correspondente.
