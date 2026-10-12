# ADR 0012 — Contrato OpenAPI e release verificável

- Estado: aceito.
- Substitui parcialmente: ADR 0003 (status de geração/publicação); mantém sua decisão PostgreSQL e de versões fixadas.
- Data: 2026-10-12. Owner: yanpenalva.
- Evidência: workflow já integrado em main e Quality; refinamento de envelopes na branch feat/slice-9-openapi-envelopes (2bed058). A branch de envelope ainda não publicou um snapshot atualizado em main.

## Contexto

O ADR 0003 registrava Scramble e comparação de contrato como planejados. O produtor
e o gate agora existem; uma branch de feature não pode publicar como se fosse o
contrato integrado. A forma dos envelopes e dos valores nullable também precisa
ser visível no OpenAPI 3.1.

## Decisão

- Scramble 0.13.47 gera o OpenAPI 3.1 a partir das rotas e metadados implementados.
  Collections usam o envelope data/links/meta e declaram metadados de paginação e
  nullables conforme a resposta real.
- OpenAPI Contract é um workflow_dispatch restrito a main. Ele valida a versão
  fixada, usa PostgreSQL de teste, exporta duas vezes com LC_ALL=C, exige bytes
  idênticos e publica openapi-<SHA completo> por 90 dias.
- O produtor não cria commit. Depois de mudanças integradas, o operador revisa o
  artifact da execução e atualiza contracts/openapi.json em uma mudança revisável.
  A exportação local serve apenas para inspeção.
- Quality verifica a igualdade do snapshot com uma nova exportação, valida estrutura
  e compara compatibilidade com o baseline imutável usando oasdiff fixado.
- Proveniência separa o digest do ZIP do SHA-256 do OpenAPI extraído. O receipt
  comprova metadados confiáveis e integridade da transferência; não é assinatura
  criptográfica.

## Consequências e verificação

Uma atualização de rota não é publicável como snapshot antes de chegar a main. Um artifact expirado exige uma nova publicação a partir da main atual; não se atribui um artifact novo a um SHA histórico. O [guia de release](../contract-release.md) cobre o produtor e snapshot; a [receita de proveniência](../openapi-contract-recipe.md) cobre extração e verificação.

O gate contract do workflow Quality é a evidência automatizada de frescor e
compatibilidade. Este ADR não altera contracts/openapi.json nem a configuração dos
workflows.
