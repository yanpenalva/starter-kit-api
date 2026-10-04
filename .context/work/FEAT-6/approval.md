# Autorização humana — entrega autônoma do Starter Kit

## Decisão posterior — onda 2 e limpeza remota (2026-10-03)

O usuário respondeu: "autorizo o pacote mas antes, limpe as branchs nao utilizadas no github deixando apenas a main e as atuais, atualize o quadro do projeto movendo o que precisa mover para done e siga".

Pacote autorizado: roles `admin`/`user`; permissões `users.view`, `users.update`, `users.set-status`, `users.assign-roles`, `users.read-cpf`, `roles.view`, `roles.update-permissions`, `logs.view`; admin protegido, último administrador preservado, sem autoelevação/autodesativação administrativa; user sem permissões administrativas e atribuição básica após verificação somente na onda 4. Auditoria inicial: `cli.bootstrap`/`privileged.change`, envelope mínimo de IDs/ator/correlação/data, sem CPF/email/credenciais/payload, diffs vazios. Bootstrap seleciona conta verificada/ativa, idempotente e transacional, com rollback por falha de auditoria; produção default-deny e nenhuma promoção real nesta onda. Exclusão de usuários e permissões adicionais de CRUD ficam para definição na onda 5.

Limpeza específica autorizada e executada: dez branches remotas comprovadamente ancestrais de main (cinco em cada repo), nenhuma PR aberta, main/branches atuais preservadas. Nenhuma branch local, tag, commit ou volume foi removido. Essa decisão não concede autorização genérica para apagar branches futuras com trabalho não integrado. Quadro completo reconciliado: dez dos 47 itens Done; issues progressivas continuam abertas. Detalhes em cleanup-branches.md.

Owner e aprovador: yanpenalva. Data: 2026-10-02. Origem: instrução explícita nesta conversa para o orquestrador mover o quadro, atualizar cada entrega, realizar commits com ou sem PR e aprovar/integrar, conduzindo o fluxo ponta a ponta até o projeto pronto.

## Escopo aprovado

- Execução do roadmap dos repositórios yanpenalva/starter-kit-api e yanpenalva/starter-kit-frontend, coordenada por funções de subagentes e uma wave ativa por vez.
- Commit e push automáticos após validação, ligados ao work item, branch e remote corretos; não solicitar nova confirmação por entrega dentro desse escopo.
- Atualização do projeto yanpenalva/1, evidências de progresso e fechamento de issues somente quando o aceite completo tiver sido atendido e a entrega estiver integrada.
- Abertura de PR quando o risco ou regras reais do repositório exigirem; integração sem PR somente para documentação/tooling de baixo impacto e quando as regras permitirem, após revisão e gates aplicáveis.
- Revisão por agente independente e merge automático condicionado ao resultado aprovado, checks do último SHA e requisitos reais do GitHub. Esta é a decisão humana prévia de autorizar esses merges; não é autoaprovação formal de uma PR pelo autor.

## Limites da autorização

Requisitos de aprovação de outra identidade no GitHub continuam vigentes: o autor não pode aprovar a própria PR. Se uma aprovação formal for obrigatória, usar apenas identidade revisora distinta já autorizada e disponível; caso indisponível, registrar o bloqueio real. Não criar contas/credenciais, forjar aprovação, contornar rulesets, usar privilégios administrativos de bypass, desabilitar gates ou forçar push.

Decisões novas de produto/arquitetura, aceitação de findings ou riscos não resolvidos, comandos destrutivos, operações irreversíveis e implantação em ambiente de produção ainda não definido não foram aprovados por esta autorização de entrega Git. Preparar proposta concreta e tratar a decisão no momento necessário. Marco B continua parte da missão, mas seus pré-requisitos operacionais precisam existir.

## Aplicação no CSD

A instrução explícita do usuário prevalece sobre os defaults anteriores de confirmação por commit/push e merge manual. A aprovação humana continua registrada: flags human_approval_required_before_push e merge_requires_human_approval permanecem true, satisfeitas por esta autorização prévia para entregas dentro do escopo e dos critérios acima. Mudança material de escopo ou risco requer nova decisão; não estender esta autorização a outros projetos.

Atualizar configurações canônicas e prompt é a entrega atual de FEAT-6. Os agentes Oh My OpenCode, as features do roadmap, permissões remotas e pipeline de integração ainda não foram executados/verificados por esta revisão documental.

## Adendo — aprovações explícitas registradas (2026-10-03 UTC; 2026-10-02 local America/Sao_Paulo)

Nesta conversa, yanpenalva respondeu duas escolhas explícitas pela ferramenta de perguntas. Registro factual das respostas, sem nova confirmação adicional:

1. **"Aprovar exceção"**: formaliza [EXC-0001](../../exceptions/EXC-0001.json) com status `approved`. Escopo exclusivo dos repositórios do roadmap Starter Kit (API aqui; autorização espelhada no frontend): reutilização da autorização validada de commit/push para **branches de entrega registradas com alvo `main`**, entre work items do roadmap, e execução condicional de merges previamente autorizados por humano. Cada work item registra branch de entrega, branch alvo e remote antes da finalização Git; mudança **fora desse plano de entrega registrado e aprovado** — ou de scope/remote/risco sem aprovação — invalida a autorização automática até reaprovação humana. A criação de novo work item do roadmap com branch registrada e alvo `main` permanece coberta pela aprovação de roadmap existente; a exceção não concede autoridade de escopo além dela. Válida até 2026-12-31, com encerramento antecipado por conclusão do roadmap ou revogação, registrado na própria exceção. Controles compensatórios: revisão independente, checks no último SHA exato e regras do repo, rastreabilidade issue → work item → commit/PR, gates/checkpoint por wave, exclusões de produção/destrutivo/tags/novo escopo/mudança de risco; sem force, bypass administrativo, tags ou aprovação fake de review no GitHub. Todo work item afetado DEVE referenciar EXC-0001 em `policy_exceptions` (referência é vínculo de registro; não é aprovação retroativa).
2. **"Autorizar conteúdo interno"**: autoriza classificação de dados `internal` (`ai.data_classification = "internal"` em `.context/config.json`): compartilhamento minimizado de código/documentação interna dos dois repositórios com os modelos/subagentes do harness existente; exclui `.env`, segredos, credenciais, PII e dumps; sem novo modelo, provedor ou conta e sem alegação nova de retenção.

Estas duas respostas são confirmações humanas reais da conversa; nenhuma outra aprovação foi inferida ou simulada. Os arquivos core MUST e as flags de aprovação humana permanecem intocados; a exceção vincula entrega/encerramento e não amplia produção indefinida.

## Adendo 2 — aprovações de continuidade da wave 0 (2026-10-03)

Na mesma conversa, yanpenalva aprovou por perguntas explícitas:

1. **"Aprovar pacote SPA"**: pacote de arquitetura SPA de [FEAT-1](../../work/FEAT-1/approval.md) — ADR proposto e matriz de compatibilidade. A materialização do ADR aceito está em curso pela função fixer no frontend e AINDA NÃO foi integrada; nenhuma implementação é alegada. A decisão humana de arquitetura está resolvida e não é reaberta; versões efetivas seguem resolvidas por lockfile no scaffold (SPA #5).
2. **"Aprovar transporte"**: [política de transporte privado do OpenAPI](../../project/api.md) — geração somente de código real implementado (#11, wave 3), run confiável de `main` no SHA exato revisado/integrado, verificação fail-closed (workflow/evento allowlist, artefato não expirado, archive digest + re-hash independente), consumo via OpenAPI commitado + tipos gerados + recibo de provenance no CI do frontend sem credencial de API, sincronização separada confiável, credencial condicional FG-PAT Actions:read apenas no repo da API (NOT FOUND; provisão humana adiada à wave 3), sem tags/identidades novas/Contents:read, sem `latest`, archive digest como integridade de transferência e não atestado.

Pins de SHA/workflow de rotina não exigem nova escolha humana; provisionamento de credencial permanece humano quando necessário. Nenhuma dessas aprovações inicia a wave 1 nem fecha issues progressivas.
