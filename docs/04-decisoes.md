# 04 — Decisões técnicas: o motivo e a alternativa rejeitada

Este guia explica cada decisão relevante da API no formato motivo → escolha →
alternativa rejeitada. É a leitura para quem precisa entender **por que** as coisas
são como são antes de propor mudanças. Cada item aponta onde a decisão vive no código
ou na configuração.

## 1. Sessão em cookie via Sanctum em vez de tokens

**Onde vive:** `config/sanctum.php` (modo stateful), guard `web` com driver `session`
em `config/auth.php`, middleware `statefulApi` no bootstrap, mais
`DisallowHeaderAuthentication` e `EnsureStatefulSession` próprios.

**Motivo.** A API serve um SPA próprio, de primeira parte. Sessão em cookie com CSRF
mantém a credencial fora do JavaScript (cookie `HttpOnly`), delega expiração e
invalidação ao mecanismo de sessão do framework e dispensa o frontend de armazenar,
renovar e proteger tokens. O login regenera a sessão e o logout a invalida com
renovação do CSRF.

**Alternativa rejeitada.** Tokens portados pelo SPA, que exigiriam armazenamento no
navegador, lógica de refresh e ampliam a superfície de exposição. Tokens ficam
restritos a integrações específicas aprovadas, e qualquer `Authorization` enviado às
rotas desta API é rejeitado com 401 — os tokens pessoais do Sanctum não funcionam
aqui por desenho, não por acidente.

## 2. Hash argon2id como padrão

**Onde vive:** `config/hashing.php` com driver `argon2id`, memória, threads e tempo
ajustáveis por `ARGON_MEMORY`, `ARGON_THREADS` e `ARGON_TIME`.

**Motivo.** Argon2id combina resistência a ataques de GPU/ASIC com uso calibrável de
memória, e é o padrão recomendado para senhas interativas. Os parâmetros por ambiente
permitem endurecer a produção sem penalizar o desenvolvimento. O login ainda verifica
`Hash::needsRehash` e atualiza o hash armazenado quando os parâmetros mudam.

**Alternativa rejeitada.** Bcrypt com rodadas fixas, que continua suportado como
alternativa de configuração, mas oferece menos margem de ajuste memória-tempo para o
mesmo custo de CPU.

## 3. Throttle em duas camadas no login: 5/min por e-mail+IP e 20/min por IP

**Onde vive:** limitadores `login`, `register`, `verification`, `forgot`, `reset` e
`api` registrados no `AppServiceProvider`.

**Motivo.** O limite de 5 por minuto por combinação de e-mail e IP freia força bruta
contra uma conta específica sem punir usuários legítimos atrás do mesmo IP. O teto de
20 por minuto por IP freia ataques distribuídos contra muitas contas a partir de uma
origem. A tabela completa:

| Limitador | Limite | Chave |
| --- | --- | --- |
| `api` | 60/min | usuário autenticado ou IP |
| `login` | 5/min + 20/min | e-mail+IP (hash SHA-256) e IP |
| `register` | 5/min | IP |
| `verification` | 3/min | usuário ou IP |
| `forgot` | 3/min | IP |
| `reset` | 5/min | e-mail+IP (hash SHA-256) |

**Alternativa rejeitada.** Limite único global por IP, que ou seria frouxo contra
ataques mirados ou agressivo contra redes compartilhadas, e chaves de throttle com o
e-mail em claro, que vazariam identidade no backend de cache — por isso a chave passa
por hash SHA-256.

## 4. Dummy hash no login para não revelar existência de conta

**Onde vive:** `LoginController`, constante de hash falso argon2id.

**Motivo.** Sem a medida, um e-mail inexistente responderia mais rápido que um
existente com senha errada, permitindo enumerar cadastros por temporização. Executando
`Hash::check` contra um hash falso quando o usuário não existe, os dois caminhos
custam parecido e devolvem o mesmo 401 genérico.

**Alternativa rejeitada.** Mensagens distintas ("e-mail não encontrado" vs "senha
incorreta"), que seriam mais amigáveis, mas entregariam o cadastro a quem varre
e-mails — uma troca de usabilidade por enumeração que a API não aceita.

## 5. Busca de e-mail case-insensitive com normalização

**Onde vive:** normalização para minúsculas sem espaços nos requests e no controller,
comparação com `lower(btrim(email, ...))` no PostgreSQL, apoio central em
`EmailLookup`.

**Motivo.** Usuários digitam `Nome@Exemplo.com` e `nome@exemplo.com` esperando a mesma
conta. Normalizar na borda e comparar em minúsculas no banco elimina duplicatas
acidentais e logins que falham por caixa alta, sem depender da collation da coluna.

**Alternativa rejeitada.** Comparação exata, que fragmentaria identidades por
capitalização e criaria duplicatas silenciosas difíceis de fundir depois.

## 6. CPF opcional em texto com índice único parcial

**Onde vive:** coluna `users.cpf` (texto, 11 posições, anulável) com índice único
parcial `where cpf is not null`, validação de formato e dígitos verificadores,
unicidade cobrada também sob concorrência.

**Motivo.** O CPF é identificação cadastral opcional: nunca funciona como login nem
concede privilégios. Texto de 11 dígitos normalizados é pesquisável, indexável e
validável; o índice parcial garante unicidade só entre quem informou, permitindo
muitos nulos sem gambiarras com valores sentinela.

**Alternativa rejeitada.** CPF obrigatório (rejeitado em favor do preenchimento
opcional), criptografia da coluna em repouso (rejeitada porque o controle é por
acesso — permissão `users.read-cpf` — e redação em auditoria e logs, não por cifra) e
índice único total (que impediria múltiplos nulos em alguns bancos).

## 7. Exclusão definitiva vs desativação por estado

**Onde vive:** `DELETE /users/{user}` (remoção) e `POST /users/{user}/status`
(transição ativo/desativado) em `routes/api.php`, protegidos por `UserPolicy`.

**Motivo.** São operações diferentes com propósitos diferentes. Desativar suspende o
acesso preservando histórico, auditoria e relacionamentos — é reversível e é o caminho
para suspensões temporárias. Remover apaga a conta quando a retenção não é mais
necessária. Manter os dois verbos separados evita que uma suspensão temporária vire
perda de dados e que uma remoção necessária vire conta fantasma desativada para sempre.

**Alternativa rejeitada.** Exclusão lógica única para tudo, que acumularia contas
mortas no banco e confundiria "suspenso" com "removido" nos relatórios e na trilha.

## 8. Fila `database` com despacho após commit

**Onde vive:** `QUEUE_CONNECTION=database` com `QUEUE_AFTER_COMMIT=true` nos
ambientes local, teste e release.

**Motivo.** Com `after_commit`, um job só é enfileirado depois que a transação que o
originou é confirmada. Sem isso, um job poderia executar enxergando dados que o
rollback apagou em seguida — e-mails de boas-vindas para cadastros que não existem
são o exemplo clássico. O driver `database` dispensa operar um broker dedicado para o
volume atual; Redis entra onde faz falta (cache, sessão, sincronização), não como
pré-requisito.

**Alternativa rejeitada.** Despacho imediato dentro da transação (risco de job órfão
após rollback) e broker obrigatório desde o scaffold (complexidade operacional sem
demanda demonstrada).

## 9. Logs em JSON no release, legíveis no desenvolvimento

**Onde vive:** `LOG_CHANNEL=stderr` com `LOG_STDERR_FORMATTER` apontando para o
`JsonFormatter` do Monolog no perfil release; log legível em arquivo único no
desenvolvimento.

**Motivo.** Em produção, logs vão para coletores centralizados, que consultam melhor
JSON estruturado com correlação do que texto livre — cada linha carrega o contexto da
requisição. No desenvolvimento, texto legível acelera a leitura humana. O formato
acompanha o ambiente em vez de forçar um só para os dois.

**Alternativa rejeitada.** Formato único para todos os ambientes: texto em produção
quebra parsing e correlação no coletor; JSON no desenvolvimento atrasa a leitura
durante o debug.

## 10. TLS terminado no edge, nunca no container

**Onde vive:** perfil release servindo HTTP puro na porta 8080 atrás do proxy, HSTS
aplicado pelos cabeçalhos de segurança da API.

**Motivo.** Terminar TLS no balanceador, CDN ou ingress centraliza certificados,
renovação e configuração criptográfica num só lugar, e mantém a imagem da API livre
de segredos de certificado. O HSTS garante que navegadores elevem para HTTPS mesmo com
o tráfego interno em HTTP.

**Alternativa rejeitada.** TLS dentro do container, que espalharia certificados por
cada réplica, complicaria rotação e misturaria segredos de transporte com a imagem da
aplicação. A porta do release nunca é exposta à internet sem proxy com TLS à frente.

## 11. Imagem multi-stage com `php.ini` por ambiente e opcache travado

**Onde vive:** `Dockerfile` em estágios `composer`, `vendor` e `runtime`, mais
`docker/local/php.ini` e `docker/production/php.ini`.

**Motivo.** Cada estágio entrega só o necessário: o estágio `composer` fornece o
binário, o `vendor` instala dependências de produção com autoloader otimizado, e o
`runtime` soma Nginx, Supervisor, opcache e Redis sob usuário sem privilégios
(`app`, UID/GID 10001). O `php.ini` de desenvolvimento revalida timestamps a cada
requisição para recarregar código editado; o de produção desliga a validação, reserva
256 MB de opcache e amplia o cache de caminhos — porque código de release só muda com
novo deploy. Ambos mantêm `expose_php` desligado e JIT desligado.

**Alternativa rejeitada.** Imagem de estágio único com ferramentas de build incluídas
(superfície maior, segredos de build na imagem final) e `php.ini` único (ou
desenvolvimento sem recarregamento, ou produção revalidando arquivos a cada request).

## 12. Cobertura mínima de 85% com pcov no CI

**Onde vive:** job de cobertura do workflow de qualidade (`vendor/bin/pest
--coverage --min=85` com driver pcov).

**Motivo.** O gate impede erosão silenciosa: cada pull request precisa manter pelo
menos 85% das linhas cobertas, então funcionalidade nova chega com testes ou o CI
barra. Pcov foi escolhido por ser rápido e sem dependências nativas pesadas, rodando
contra PostgreSQL, Redis e Mailpit reais no job.

**Alternativa rejeitada.** Sem gate numérico (cobertura cai aos poucos até os testes
virarem enfeite) e medição com drivers mais lentos que alongariam cada execução sem
mudar a decisão.

## 13. Smoke de login → perfil → logout, com limpeza de throttle entre casos

**Onde vive:** workflow reutilizável de pós-deploy e teste de fronteira HTTP.

**Motivo.** O smoke exercita exatamente o contrato que o SPA usa: obter CSRF, logar,
ler o perfil e sair, sobre HTTP real com cookies. Entre cenários de throttle, a suíte
limpa apenas os dados de cache do framework no diretório isolado, de modo que um 429
esperado num caso não contamine o seguinte — determinismo sem afrouxar os limites.

**Alternativa rejeitada.** Smoke só com `/health` (prova que o processo subiu, não que
alguém consegue autenticar) e limpeza global de cache (rápida, mas esconderia
vazamento de estado entre testes).

## 14. Versionamento em `/api/v1` com `/api/v2` reservado para quebras

**Onde vive:** prefixo `api/v1` no bootstrap, rotas em `routes/api.php`, geração
OpenAPI restrita a `api/v1/*`, versão do documento vinda de `API_VERSION`.

**Motivo.** Mudanças aditivas (campos e endpoints opcionais) convivem sob `/api/v1`
sem quebrar clientes. Mudanças incompatíveis exigem o prefixo `/api/v2` com período
de convivência — nunca quebra silenciosa do v1. O contrato versionado e o snapshot
pinado dão ao frontend um alvo estável para gerar tipos.

**Alternativa rejeitada.** API sem versão (qualquer mudança arrisca todos os clientes)
e versionamento por cabeçalho ou parâmetro (invisível em logs, difícil de rotear no
edge e fácil de esquecer no cliente).

## 15. Onboarding de usuário criado pelo administrador

**Onde vive:** `app/Actions/Users/StoreUser.php`, `app/Notifications/VerifyEmail.php`,
comando `auth:prune-expired-tokens` em `routes/console.php`.

**Motivo.** O convite é o mesmo e-mail de verificação do cadastro público: link assinado
com validade de 24 horas, reenvio em `POST /api/v1/email/verification-notification`
(limitado a 3 por minuto por usuário; a 4ª tentativa devolve 429).

**Falha de envio.** `StoreUser` grava o usuário e a auditoria dentro da transação e só
depois dispara a notificação, fora dela. A notificação é enfileirada (fila `database`,
3 tentativas com espera de 5, 15 e 60 segundos). Assim, uma falha de envio de e-mail
não desfaz a criação da conta nem a auditoria: o usuário existe e o convite pode ser
reenviado. Se o próprio enfileiramento falhar, a resposta é de erro, mas o usuário já
foi persistido; repetir a criação devolverá e-mail duplicado e o caminho de recuperação
é o reenvio do link.

**Prune de 24 horas e contas administrativas.** `auth:prune-expired-tokens` remove,
diariamente, toda conta com `email_verified_at` nulo e `created_at` anterior a 24 horas.
Isso inclui contas criadas pelo administrador (ativas ou desativadas) cujo convidado não
verificou o e-mail dentro do prazo: a conta é excluída definitivamente, sem aviso, e o
administrador precisa criá-la de novo. O prune não distingue contas por origem.

**Login antes da verificação.** O convidado entra com a senha definida pelo administrador
antes de verificar o e-mail; a sessão é limitada (perfil mínimo e fluxo de verificação,
conforme ADR 0004). A verificação não é pré-requisito para autenticar, e sim para os
recursos de negócio.

**Alternativa rejeitada.** Enviar o e-mail dentro da transação: acoplaria a criação da
conta à disponibilidade da fila e do provedor de e-mail.
