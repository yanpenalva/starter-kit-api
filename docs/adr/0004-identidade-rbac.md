# ADR 0004 — Identidade, ativação e menor privilégio

- Estado: aceito; decisão arquitetural aprovada, ainda não implementada.
- Data: 2026-10-01. Owner e aprovador: yanpenalva. Evidência: revisão nesta conversa e solicitação explícita de implementar o plano “Consolidar as decisões dos ADRs”; registro em [FEAT-1](../../.context/work/FEAT-1/approval.md).

## Contexto e evidência

A [issue #1](https://github.com/yanpenalva/starter-kit-api/issues/1) pede definição de cadastro, verificação, ativação e bootstrap sem elevação indevida. O usuário decidiu pelo cadastro público e ativação automática após verificar email, substituindo a proposta inicial de ativação administrativa. Laravel oferece [verificação de email](https://laravel.com/framework/docs/verification) e [Policies](https://laravel.com/framework/docs/authorization); a decisão de produto está registrada na aprovação acima.

## Decisão

Permitir cadastro público com conta inicialmente pendente de verificação. A verificação do email ativa automaticamente o acesso aos recursos permitidos ao perfil básico; não concede privilégios administrativos. Até verificar, permitir apenas sessão, logout, identificação mínima e fluxo de verificação; negar recursos de negócio. Bloquear usuário desativado em toda requisição protegida, mesmo com sessão existente; a verificação não pode reativar uma conta desativada explicitamente.

Cadastro aceita somente atributos explicitamente permitidos; cliente não pode enviar roles, permissions, estado de ativação ou verificação. Usar Spatie Permission e Policies por recurso, negando acesso sem permissão explícita e verificando também acesso ao objeto. A UI não é controle de autorização. Nomes de roles e matriz de permissões serão definidos na feature correspondente.

CPF opcional no perfil para identificação cadastral, sem funcionar como login ou conceder privilégios. Quando informado, normalizar para 11 dígitos, validar formato e dígitos verificadores e exigir unicidade entre perfis; ausência é permitida. O titular pode consultar e editar o próprio CPF; consulta por gestão exige permissão específica, sem acesso automático por título de role. CPF não integra respostas públicas ou a trilha de auditoria. A implementação deverá garantir unicidade também sob concorrência e não expor o CPF de outro perfil em erros.

Bootstrap administrativo por comando operacional restrito, fora de HTTP público, executado com autorização humana: selecionar conta explicitamente escolhida e verificada, promover uma única vez de forma transacional e idempotente e registrar o evento. Não usar credenciais fixas, promoção do primeiro cadastro ou seed administrativo em produção. Se a gravação da auditoria falhar, reverter a promoção e demais alterações de privilégios conforme ADR 0005.

## Alternativas

Cadastro por convite ou criação pelo administrador restringiriam o acesso inicial. Ativação administrativa acrescentaria aprovação manual; foi rejeitada nesta revisão em favor da ativação automática após verificação. Bootstrap automático pelo primeiro usuário pode elevar uma identidade não autorizada. CPF obrigatório foi rejeitado em favor de preenchimento opcional.

## Impactos, pendências e verificação

A ativação automática reduz trabalho administrativo; os recursos liberados continuam sujeitos ao perfil e às Policies. CPF cria um identificador cadastral que exige restrição de acesso. Primeira feature, nomes de roles/permissões, recuperação administrativa e detalhes operacionais de tratamento do CPF serão definidos antes da implementação correspondente. Não bloqueiam o scaffold sem endpoints de identidade.

Testar cadastro sem CPF, normalização, CPF inválido, duplicação inclusive concorrente, acesso do titular e da gestão autorizada, negação a terceiros e exclusão de CPF de respostas públicas/logs. Testar tentativa de atribuir role/estado pelo cadastro, usuário não verificado, ativação após verificação, ativo sem permissão, acesso ao objeto de outro usuário, desativação com sessão aberta e bootstrap idempotente/concorrente com rollback por falha de auditoria. Mudanças de privilégios/ativação exigem trilha conforme ADR 0005, sem dados pessoais. Reversão de privilégios em produção demanda operação explícita e auditada, não rollback cego de migrations.

## Incorporação do ADR 0006 e precedência

CPF opcional constitui decisão própria da nova API, conforme contrato acima, não requisito herdado do legado. ADR 0006 incorporado em 0001, 0002 e 0004 por aprovação nesta conversa. A ativação automática após email verificado prevalece sobre a descrição desatualizada da issue #14 que ainda menciona aprovação administrativa; a issue não foi editada.
