# Release do contrato OpenAPI

Este guia descreve o fluxo oficial da API. A configuração executável é
.github/workflows/openapi.yml e o gate de contrato está em
.github/workflows/quality.yml.

## Produzir o artifact

1. Conclua e integre em main as mudanças de rotas ou respostas. A publicação não
   executa em branches de feature.
2. Em GitHub Actions, execute OpenAPI Contract em main e informe o motivo exigido.
   O workflow aceita somente workflow_dispatch e falha fechado fora de main.
3. O job instala as dependências fixadas, exige dedoc/scramble 0.13.47, migra o
   PostgreSQL de teste e exporta com LC_ALL=C. Duas exportações precisam ter bytes
   idênticos.
4. O workflow publica contracts/openapi.json como artifact
   openapi-<commit-sha-completo>, com retenção de 90 dias. A execução não cria
   commit no repositório.
5. Baixe e revise o artifact da execução correspondente ao SHA exato de main. Para
   consumo entre repositórios, verifique run, workflow, branch, SHA, artifact id,
   digest do ZIP e SHA-256 recalculado do arquivo seguindo a
   [receita de proveniência](openapi-contract-recipe.md).
   Não aceite artifacts por nome latest, não use metadados não verificados e não
   confunda o digest do arquivo ZIP com o hash do OpenAPI.

## Atualizar o snapshot versionado

Depois de revisar o artifact, atualize contracts/openapi.json em uma mudança
revisável própria. O produtor não grava esse commit automaticamente. Exportação
local com scramble:export serve para inspeção e não substitui a execução oficial.

O gate contract do workflow Quality reexporta duas vezes, exige igualdade com o
snapshot commitado, valida estrutura, sessão-cookie sem esquema bearer e compara
compatibilidade com a baseline imutável usando oasdiff fixado. Mudança incompatível
precisa seguir a regra de versão da API e não pode ser escondida por atualizar só o
snapshot.

Não altere o snapshot em um branch de feature nem copie uma exportação local para
simular a publicação. Se o artifact expirou, publique novamente a partir da main
atual e revise o novo SHA; a exportação não reproduz arbitrariamente um SHA
histórico. A retenção do artifact é 90 dias. O snapshot já revisado e commitado não
expira com o artifact.

## Proveniência e segurança

Para o procedimento detalhado de extração, receipt e verificação, siga a
[receita de proveniência OpenAPI](openapi-contract-recipe.md).

- Capture a execução concluída e confiável, incluindo repositório, branch, SHA
  completo, evento, caminho/nome/ID do workflow, run ID e metadados do artifact.
- Confira o artifact ID e nome contra essa execução e valide separadamente o digest
  do ZIP bruto e o SHA-256 do OpenAPI extraído. Extraia somente openapi.json usando
  o caminho protegido em scripts/contract_extract.py; construa o receipt a partir
  dos metadados verificados fora do ZIP.
- Valide arquivo e receipt com scripts/contract_cli.py, fixando o SHA exato de main,
  o ID do workflow e o evento permitido. O modo download-fresh aplica a expiração
  de 90 dias; nunca use latest ou aceite um receipt criado dentro do ZIP.
- O workflow tem permissão contents: read, não recebe segredo para publicar e não
  escreve em outro repositório.
- A verificação de artifacts privados falha fechado se workflow, evento, branch,
  SHA, id/nome/digest do artifact ou hash do arquivo não corresponderem à política.
- A verificação de proveniência autentica metadados e integridade de transferência;
  não é uma assinatura criptográfica nem uma atestação do produtor.
- Não use PAT ou credencial de aplicação para contornar a verificação. O contrato
  de API permanece Sanctum por sessão/cookie e CSRF, sem bearer tokens.
