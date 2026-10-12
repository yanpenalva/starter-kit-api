# Receita para uma fatia vertical

Uma fatia vertical entrega um comportamento observável completo, do limite HTTP à
persistência e resposta quando esses componentes forem necessários. Organize a
mudança pelo resultado do usuário, não por uma camada inteira como “todos os
models” ou “todos os endpoints”.

## 1. Defina uma fatia verificável

- Comece pelo AGENTS.md e .context/INDEX.md. Registre track, type, owner, risco,
  classificação, routing e fase no work item CSD antes da implementação.
- Escolha um fluxo pequeno com critérios de aceitação observáveis e um dono claro.
- Liste entradas, estados, autorização, respostas, efeitos de persistência e
  compatibilidade. Marque desconhecidos como NOT FOUND; não invente rotas,
  permissões ou sinais operacionais.
- Separe follow-ups que não são necessários para completar esse fluxo.

## 2. Trabalhe na branch

Atualize origin/main, crie a branch autorizada a partir dele e faça uma mudança por
fatia. Não baseie uma fatia em outra branch paralela sem uma decisão explícita:
isso mantém cada entrega revisável e mostra quais dependências ainda não estão
integradas.

Antes da implementação, escreva o teste focado, execute-o e confirme que falha pelo
comportamento ausente. Depois implemente o menor caminho completo e rode o teste de
novo.

## 3. Use as fronteiras da API existentes

- Valide entrada em Form Requests, autorize com Gates/Policies, coloque orquestração
  relevante em Actions e exponha só os campos permitidos por Resources.
- HTTP autentica por Sanctum stateful session/cookie e CSRF. Use
  ActsAsSanctumUser nos testes HTTP. Não adicione bearer, PAT, JWT ou tokens de API.
- Em filtros, aceite apenas campos e ordenações allowlisted. Não use ilike sem um
  guard explícito por driver.
- Adicione Pest para comportamento Laravel. Prefira testes SQLite rápidos quando a
  semântica do caso for compatível; PostgreSQL 18, Redis e Mailpit são exercitados
  pelo perfil de teste canônico no CI.
- Atualize documentação de domínio e contrato no mesmo slice. Mudança material de
  decisão precisa de ADR aprovado; não use ADR para afirmar que uma branch já foi
  integrada ou implantada.

## 4. Rode os gates aplicáveis

Com PHP 8.5 local, carregue o toolchain configurado:

    source /opt/data/php/env.sh

Para comportamento da aplicação:

    composer lint
    composer analyse
    composer test

Rode Pest focado durante o ciclo TDD e, no CI, a suíte do perfil Compose com
PostgreSQL 18, Redis e Mailpit. Para alteração de contexto/documentação:

    python3 scripts/validate_context.py --strict
    python3 scripts/validate_context.py --strict --examples
    python3 -m unittest discover -s tests -v

Registre comandos, escopo, resultado e limitações. Não chame um gate remoto de
passado se ele não foi executado neste commit.

## 5. Libere o contrato e entregue

Se rotas ou respostas mudarem, deixe contracts/openapi.json para o workflow
produtor executado em main. Siga o [guia de release do contrato](contract-release.md)
para revisar o artifact e atualizar o snapshot; export local é apenas inspeção.

Revise o diff inteiro, confirme que ele contém só a fatia, atualize o work item e
documente branch, arquivos e gates. Faça commit convencional e push somente para a
branch registrada. PR, merge, deploy e qualquer publicação externa seguem as
autorizações de entrega registradas para o trabalho.
