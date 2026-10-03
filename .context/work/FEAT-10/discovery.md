# Discovery — FEAT-10

[API #10](https://github.com/yanpenalva/starter-kit-api/issues/10) é progressiva. Wave 1, primeira fatia: fronteira HTTP segura sem autenticação. Verdade observada: `laravel/framework ^13.17` em composer.json (Sanctum AINDA não instalado — instalação via composer só dentro do container PHP 8.5, após preflight); rotas reais: rota de disponibilidade existente + `/up` (preservadas verbatim); Laravel 13 substitui o alias `VerifyCsrfToken` por `PreventRequestForgery`. Fatos do librarian sobre bypass do PreventRequestForgery: leitura da requisição, console+testing, `$except`, Origin válido e token match. Board: API #10 In progress (movido pelo pai).

Produção (URLs, proxies reais, cookies seguros de produção) continua `NOT FOUND` e fora desta fatia.
