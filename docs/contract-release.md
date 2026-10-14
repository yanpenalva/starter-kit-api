# 📜 Release do contrato OpenAPI

Este guia descreve o fluxo oficial da API. A configuração executável é
.github/workflows/openapi.yml e o gate de contrato está em
.github/workflows/quality.yml.

## Atualizar e validar o snapshot candidato

O job contract de `.github/workflows/quality.yml` roda em pull requests e em pushes
para main. Ele exporta o OpenAPI duas vezes, exige bytes idênticos e compara a
exportação com `contracts/openapi.json` presente no checkout daquela revisão.
Também valida a estrutura e os esquemas de sessão e compara compatibilidade com a
baseline imutável usando oasdiff fixado.

Quando uma mudança de rota ou resposta altera o contrato, atualize
`contracts/openapi.json` a partir da revisão candidata e inclua o snapshot no mesmo
pull request. Gere o candidato na revisão proposta com:

```sh
LC_ALL=C php artisan scramble:export --path=contracts/openapi.json
```

Revise o diff e deixe o gate confirmar a exportação determinística e a
compatibilidade. Em mudanças que não alteram o contrato, não é necessário editar o
snapshot. Uma exportação local gera o candidato para revisão; ela não substitui o
artifact oficial de proveniência.

## Registrar uma quebra intencional

O gate continua bloqueando findings `ERR` do oasdiff por padrão. Uma quebra de v1
aprovada pelo produto só pode passar quando estiver registrada em
`contracts/accepted-breaking-changes.json`. Cada entrada contém um `id`, o endpoint,
a mudança em linguagem de domínio, a justificativa e a referência/estado do PR.
Também registra `oasdiff_id` e `oasdiff_text`, que devem corresponder exatamente ao
finding produzido para aquela quebra.

`scripts/contract_compat.py` lê o arquivo e compara o JSON do oasdiff pela regra,
método, caminho e texto exatos. Cada entrada cobre um único finding. Campo ausente,
arquivo inválido, assinatura duplicada, erro do checker ou qualquer finding `ERR`
sem correspondência continua falhando fechado. A allowlist não altera a severidade
de outras mudanças. Use uma referência de PR real quando houver; para uma entrega
somente por branch, registre explicitamente que o PR não foi aberto e identifique a
branch. Remova a entrada depois que a quebra passar a fazer parte da baseline da
main.

Exemplo da assinatura estruturada do checker para tornar obrigatório `roles`:

```json
{
  "endpoint": "POST /api/v1/users",
  "oasdiff_id": "new-required-request-property",
  "oasdiff_text": "added the new required request property `roles`"
}
```

## Publicar o artifact oficial

Depois que a mudança de contrato for integrada em main, execute OpenAPI Contract em
GitHub Actions e informe o motivo exigido. O produtor aceita somente
`workflow_dispatch` e falha fora de main. Ele instala as dependências fixadas, exige
dedoc/scramble 0.13.47, migra o PostgreSQL de teste e exporta com `LC_ALL=C`; as duas
exportações precisam ter bytes idênticos.

O workflow publica `contracts/openapi.json` como artifact
`openapi-<commit-sha-completo>`, com retenção de 90 dias. A execução não cria commit
nem atualiza o snapshot versionado. Revise o artifact da execução correspondente ao
SHA exato de main e confirme que ele corresponde ao snapshot integrado. Para consumo
entre repositórios, verifique run, workflow, branch, SHA, artifact id, digest do ZIP
e SHA-256 recalculado do arquivo seguindo a
[receita de proveniência](openapi-contract-recipe.md). Não aceite artifacts por nome
latest, não use metadados não verificados e não confunda o digest do arquivo ZIP com
o hash do OpenAPI.

Se o artifact expirou, publique novamente a partir da main atual e revise o novo
SHA; a exportação não reproduz arbitrariamente um SHA histórico. O snapshot revisado
e commitado não expira com o artifact.

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
