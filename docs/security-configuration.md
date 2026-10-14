# 🔒 Configuração de segurança em produção

Defina estes valores no ambiente de execução da API antes de publicar:

| Variável | Valor a configurar |
| --- | --- |
| `CORS_ALLOWED_ORIGINS` | Lista separada por vírgulas das origens HTTPS exatas autorizadas a acessar a API, incluindo esquema e porta quando aplicável. Não use curingas. |
| `SESSION_DOMAIN` | Domínio que deve receber o cookie de sessão. Use o domínio compartilhado necessário entre API e SPA; deixe sem valor para cookie restrito ao host da API. |
| `SANCTUM_STATEFUL_DOMAINS` | Lista separada por vírgulas dos hosts e portas da SPA e da API que devem iniciar autenticação stateful. Não inclua esquema. |
| `SESSION_SECURE_COOKIE` | Obrigatório como `true` em produção. Em HTTP local use `false` ou deixe ausente: o atributo `Secure` jamais deve ser enviado em HTTP, pois o navegador descarta o cookie. |
| `MAIL_MAILER` | Use um transporte de entrega em produção; `log` e `null` são recusados no boot para evitar gravar mensagens e tokens de redefinição em logs ou descartá-los silenciosamente. O default `log` continua disponível em desenvolvimento local. |
| `SESSION_HTTP_ONLY` | `true` sempre. Mantém o cookie de sessão ilegível para JavaScript, mitigando roubo via XSS. |
| `SESSION_SAME_SITE` | `lax` é o padrão e a escolha correta quando API e SPA estão sob o mesmo domínio registrável (Same-Site, ex.: `api.empresa.com` + `app.empresa.com` com `SESSION_DOMAIN=.empresa.com`). `none` só seria exigido entre domínios registráveis distintos (Cross-Site) — cenário desaconselhado para sessão stateful, pois navegadores modernos bloqueiam cookies de terceiros. Nunca adote `none` como correção de 419. |

O cookie de sessão da aplicação é `HttpOnly` e, portanto, ilegível para JavaScript: o frontend nunca o manipula diretamente, ele apenas acompanha as requisições. Já o cookie `XSRF-TOKEN` é intencionalmente legível ao JavaScript, pois o frontend lê seu valor e o espelha no cabeçalho `X-XSRF-TOKEN` de cada requisição stateful, permitindo a verificação CSRF no backend.

Os domínios reais dependem da implantação e devem ser fornecidos pelo responsável pelo ambiente. Não reutilize os defaults locais `localhost` em produção. Mantenha `supports_credentials` habilitado somente para as origens exatas configuradas.

O Nginx de produção aplica HSTS (`max-age=31536000`). A configuração local não envia esse cabeçalho.
