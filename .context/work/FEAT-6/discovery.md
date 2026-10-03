# Discovery — FEAT-6

O usuário precisa distribuir o trabalho restante entre outros modelos usando CSD. Uma sequência numérica não representa as dependências de identidade, auditoria, contrato e integração entre API e SPA.

Origem: [API #6](https://github.com/yanpenalva/starter-kit-api/issues/6), primeira fatia documental de coordenação, e pedido explícito nesta conversa para criar arquivo Markdown ponta a ponta. O quadro completo foi consultado em 2026-10-02: 47 itens, 27 API e 20 SPA; paginação encerrada com hasNextPage=false. Dependências foram confrontadas com issues e ADRs locais.

Público: yanpenalva, executores de cada repo e revisores. Valor esperado: permitir entregas delimitadas e paralelas sem violar contratos ou fechar issues progressivas parcialmente. Sinal de sucesso: os 47 itens têm referência, estado observado, etapa e complexidade estimada; cada onda explicita pré-requisitos, paralelismo e evidência de conclusão.

A tarefa não autoriza execução do roadmap inteiro nesta entrega. Frontend #1 e aceites completos de #2/#3 permanecem pendentes; #6 não é concluída por este arquivo.

## Steering posterior

yanpenalva informou que as tasks API #2/#3 já foram fechadas na PR e pediu substituir o prompt por uma instrução ao orquestrador Oh My OpenCode: ele deve determinar a ordem e delegar aos agentes. A pendência acima descreve a descoberta original; o prompt de retomada considera a confirmação humana de conclusão e confere evidência atual sem reabrir entregas. Esta revisão autoriza elaborar o prompt, não executar o lote de features ou instalar o harness.

Na correção seguinte, o usuário esclareceu que a missão deve cobrir todo o projeto ponta a ponta: delegar funções aos subagentes, executar cada wave separadamente e seguir organizado até os Marcos A/B. O limite anterior de waves 0/1 foi removido do prompt; a distribuição dentro da wave continua por dependências. Autorizações de merge/produção continuam humanas e nenhuma execução das features foi iniciada por esta edição documental.

Steering mais recente: o usuário explicitamente quer quadro atualizado, commits com ou sem PR, revisão e aprovação/integração autônomas, sem acompanhar operações por task. [Autorização humana prévia](approval.md) registrada com escopo Starter Kit e critérios de gates. Isso substitui a confirmação por operação de rotina; limites técnicos do GitHub e pré-requisitos de produção continuam explícitos.
