# ADR 0001 — API e SPA independentes

- Estado: aceito; decisão arquitetural aprovada, ainda não implementada.
- Data: 2026-10-01. Owner e aprovador: yanpenalva. Evidência: revisão nesta conversa e solicitação explícita de implementar o plano “Consolidar as decisões dos ADRs”; registro em [FEAT-1](../../.context/work/FEAT-1/approval.md).

## Contexto e evidência

A [issue #1](https://github.com/yanpenalva/starter-kit-api/issues/1) e o [contexto arquitetural](../../.context/project/architecture.md) definem API REST Laravel e frontend Vue/Quasar independentes. Na aprovação inicial só existia governança; o scaffold API-only agora é implementado em FEAT-7, com ambiente em FEAT-8 e CI em FEAT-24.

## Decisão

Manter dois repositórios, releases e deploys independentes. A API expõe JSON sob `/api/v1`; a SPA pertence ao frontend. Controllers coordenam HTTP; Form Requests validam entradas; Policies autorizam operações; Resources controlam a resposta. Criar Actions quando um caso de uso tiver lógica de aplicação relevante. Eloquent permanece disponível diretamente; Services, Repositories e DDD não são camadas obrigatórias.

## Alternativas

Monólito acoplaria as entregas; camadas genéricas antecipadas acrescentariam manutenção sem domínio conhecido. Ambas divergem da direção registrada na issue.

## Impactos e verificação futura

Contratos compartilhados exigem mudanças compatíveis e coordenação entre releases. A primeira feature deve testar validação, autorização e formato JSON. Não definir módulos de negócio antes de conhecer seus casos de uso. Hosting, pipelines e estratégia de evolução além de `/api/v1`: NOT FOUND. Reversão desta entrega documental consiste em reverter a alteração documental, preservando a evidência de aprovação; não há migração ou alteração de runtime.

## Incorporação do ADR 0006

Por decisão humana nesta conversa, textos e regras de domínio derivam dos requisitos das novas features, sem reaproveitamento automático do legado. Não copiar seeds, credenciais ou roles. ADR 0006 incorporado em 0001, 0002 e 0004; registro histórico preservado em FEAT-1.
