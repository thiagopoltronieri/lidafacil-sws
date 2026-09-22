# Documentação da API - Lida Fácil

API REST segura para gestão de usuários e do rebanho, com autenticação por JSON Web Token (JWT) e controle de acesso por perfil (RBAC). Todas as respostas são em JSON e a base dos recursos é `/api/v1`. Os nomes de endpoints seguem o inglês técnico, padrão de mercado para APIs.

Sumário:

1. Modelagem da API
2. Segurança com JWT
3. Controle de acesso (RBAC)
4. OAuth 2.0
5. Análise de segurança

## Parte 1 - Modelagem da API

Convenções REST adotadas: substantivos no plural para coleções, verbos HTTP para a operação, códigos de status coerentes com o resultado e corpo em JSON. Um recurso criado volta com 201; uma exclusão bem-sucedida volta com 204 sem corpo.

Autenticação - rotas públicas:

| Método | Endpoint             | Finalidade                                                 | Resposta                                    |
| ------- | -------------------- | ---------------------------------------------------------- | ------------------------------------------- |
| POST    | /api/v1/auth/login   | Autentica por e-mail e senha e emite os tokens             | 200 OK, 401 Unauthorized, 422 Unprocessable |
| POST    | /api/v1/auth/refresh | Renova o access token a partir de um refresh token válido | 200 OK, 401 Unauthorized                    |
| POST    | /api/v1/auth/logout  | Revoga o refresh token apresentado                         | 204 No Content                              |

Usuários -  rotas protegidas por JWT:

| Método | Endpoint           | Finalidade                             | Perfis                             | Resposta                            |
| ------- | ------------------ | -------------------------------------- | ---------------------------------- | ----------------------------------- |
| GET     | /api/v1/users      | Listar usuários                       | admin, operator                    | 200 OK, 403 Forbidden               |
| POST    | /api/v1/users      | Criar usuário                         | admin                              | 201 Created, 403, 409 Conflict, 422 |
| GET     | /api/v1/users/me   | Consultar o próprio cadastro do token | admin, operator, client            | 200 OK                              |
| GET     | /api/v1/users/{id} | Consultar um usuário                  | admin, operator, o próprio client | 200 OK, 403, 404 Not Found          |
| PUT     | /api/v1/users/{id} | Atualizar um usuário                  | admin, operator, o próprio client | 200 OK, 403, 404, 409, 422          |
| DELETE  | /api/v1/users/{id} | Excluir um usuário                    | admin                              | 204 No Content, 403, 404, 409       |

Rebanho e vacinação -rotas protegidas por JWT:

| Método | Endpoint                                          | Finalidade                         | Perfis          | Resposta                   |
| ------- | ------------------------------------------------- | ---------------------------------- | --------------- | -------------------------- |
| GET     | /api/v1/animals                                   | Listar animais                     | admin, operator | 200 OK, 403                |
| POST    | /api/v1/animals                                   | Cadastrar animal                   | admin, operator | 201 Created, 403, 409, 422 |
| GET     | /api/v1/animals/{id}                              | Consultar animal                   | admin, operator | 200 OK, 403, 404           |
| PUT     | /api/v1/animals/{id}                              | Atualizar animal                   | admin, operator | 200 OK, 403, 404, 409, 422 |
| DELETE  | /api/v1/animals/{id}                              | Excluir animal                     | admin, operator | 204 No Content, 403, 404   |
| GET     | /api/v1/animals/{id}/vaccinations                 | Listar o histórico de vacinação | admin, operator | 200 OK, 403, 404           |
| POST    | /api/v1/animals/{id}/vaccinations                 | Registrar uma vacina               | admin, operator | 201 Created, 403, 404, 422 |
| DELETE  | /api/v1/animals/{id}/vaccinations/{vaccinationId} | Remover um registro de vacinação | admin, operator | 204 No Content, 403, 404   |

Verificação de saúde em rota pública: `GET /health` responde 200 com `{"status":"ok"}`, usado pelo healthcheck do container.

Formato de erro: toda falha volta num envelope único, sem vazar detalhe interno.

```json
{ "error": { "message": "Dados inválidos.", "status": 422, "fields": { "email": "Informe um e-mail válido." } } }
```

## Parte 2 - Segurança com JWT

Processo de login: o cliente envia e-mail e senha em JSON para `POST /api/v1/auth/login`. O servidor busca o usuário pelo e-mail e confere a senha contra o hash bcrypt guardado. A comparação é feita em tempo constante e, quando o e-mail não existe, ainda assim gasta-se o tempo de um hash falso, para não vazar por timing quais e-mails estão cadastrados. Credencial errada e e-mail inexistente devolvem a mesma resposta 401 com a mensagem genérica "Credenciais inválidas".

Geração do token: com as credenciais válidas, a API gera um JWT assinado em HS256 com um segredo que vive apenas no ambiente (variável `JWT_SECRET`, nunca no código nem no repositório). O token é devolvido junto de um refresh token e dos dados públicos do usuário:

```json
{
  "access_token": "<jwt>",
  "token_type": "Bearer",
  "expires_in": 900,
  "refresh_token": "<opaco>",
  "user": { "id": 1, "name": "...", "email": "...", "role": "admin", "created_at": "..." }
}
```

Informações armazenadas no token (claims do payload):

- `iss`: emissor do token (identifica a aplicação que assinou).
- `sub`: identificador do usuário (id).
- `role`: perfil de acesso (admin, operator ou client), base do RBAC.
- `typ`: tipo do token (access), para separar de outros usos.
- `iat` e `exp`: instante de emissão e de expiração.

Por minimização de dados, o token não carrega nome nem e-mail. O payload de um JWT é apenas codificado em base64, não é cifrado, e pode ser lido por qualquer um que tenha o token, então PII fica de fora. O front obtém o nome pelo objeto `user` devolvido no login ou por `/users/me`. O JWT também nunca guarda a senha. O que garante a confiança é a assinatura. As respostas que devolvem tokens saem com o cabeçalho `Cache-Control: no-store`, para não ficarem em cache de navegador ou proxy.

Uso do token: a cada requisição protegida o cliente envia o cabeçalho `Authorization: Bearer <access_token>`. O servidor valida a assinatura fixando o algoritmo HS256 na verificação, ou seja, nunca confia no campo `alg` que vem no cabeçalho do próprio token. Isso evita os ataques clássicos de confusão de algoritmo e de `alg=none`. Também são conferidos o emissor, o tipo e a expiração.

Política de expiração e justificativa: o access token dura 15 minutos e o refresh token dura 7 dias, ambos configurávesi por ambiente. A escolha equilibra segurança e experiência de uso. Um access token curto reduz bastante a janela de dano caso o token seja interceptado, já que ele expira rápido. Para o usuário não precisar refazer login o tempo todo, o refresh token permite obter um novo access token silenciosamente. O refresh usa rotação: ao ser usado, ele é revogado e um novo é emitido, e uma tentativa de reusar o refresh antigo falha, o que ajuda a detectar roubo de token. No banco guarda-se apenas o hash SHA-256 do refresh, nunca o valor cru, então um vazamento do banco não entrega tokens utilizáveis. O logout revoga o refresh corrente.

## Parte 3 - Controle de acesso (RBAC)

A aplicação tem três perfis de acesso, checados a partir da claim `role` do token. A autorização é deny-by-default: sem token válido nenhuma rota protegida responde, e cada endpoint verifica explicitamente se o perfil pode executar a ação.

| Recurso / Ação                                             | admin | operator | client |
| ------------------------------------------------------------ | ----- | -------- | ------ |
| Usuários: listar                                            | sim   | sim      | não   |
| Usuários: criar                                             | sim   | não     | não   |
| Usuários: excluir                                           | sim   | não     | não   |
| Usuários: consultar qualquer                                | sim   | sim      | não   |
| Usuários: atualizar clientes                                | sim   | sim      | não   |
| Usuários: atualizar admin ou operador                       | sim   | não     | não   |
| Usuários: consultar ou atualizar o próprio (`/users/me`) | sim   | sim      | sim    |
| Usuários: definir ou alterar o perfil                       | sim   | não     | não   |
| Usuários: redefinir a senha de terceiros                    | sim   | Não     | não   |
| Rebanho e vacinação: CRUD                                  | sim   | sim      | não   |

Além do papel, há checagem a nível de objeto: o perfil client só enxerga e edita o próprio cadastro, nunca o de outro usuário, mesmo informando um id alheio na URL. Isso fecha a brecha de acesso direto a objeto por referência (IDOR).

Para evitar escalonamento vertical de privilégio, a decisão de atualização leva em conta o perfil do alvo: o operator só atualiza clientes e o próprio cadastro, nunca um admin ou outro operator. A atribuição de perfil é exclusiva do admin, então operator e client não conseguem se promover: se enviarem o campo `role`, ele é ignorado e o perfil atual é mantido. A redefinição de senha de terceiros também é privilégio do admin; qualquer usuário pode trocar apenas a própria senha. Como salvaguarda operacional, o admin não pode excluir a própria conta, para não se trancar para fora do sistema.

## Parte 4 - OAuth 2.0

A observação da atividade pede a explicação do OAuth 2.0, sem implementação. A seguir, como uma aplicação parceira poderia acessar esta API por OAuth 2.0.

Concessão de acesso: no fluxo de código de autorização (authorization code), o usuário dono dos dados é redirecionado a um servidor de autorização confiável (o provedor de identidade) e lá se autentica e autoriza, de forma explícita, quais permissões a aplicação parceira terá. O parceiro nunca vê a senha do usuário. Após o consentimento, o servidor de autorização devolve ao parceiro um código de autorização temporário, que o parceiro troca, no seu backend, por um access token, e opcionalmente, um refresh token.

Utilização de tokens: de posse do access token, a aplicação parceira o envia no cabeçalho `Authorization: Bearer <token>` a cada chamada aos recursos protegidos. A API, atuando como resource server, valida o token (assinatura, emissor, expiração e escopos) antes de liberar o recurso. Os escopos limitam o que o token pode fazer, de modo que o parceiro recebe só o acesso mínimo necessário.

Benefícios: o usuário não compartilha a prpópria senha com o parceiro, o que reduz a superfície de exposição de credenciais; as permissões são delegadas, específicas por escopo e temporárias, podendo ser revogadas a qualquer momento sem trocar a senha; e há rastreabilidade, pois cada token é associado a uma aplicação e a um consentimento. No contexto desta solução, a API poderia deixar de emitir o próprio JWT e passar a confiar num provedor de identidade externo (por exemplo, via OpenID Connect sobre OAuth 2.0), validando os tokens emitidos por ele. A arquitetura atual, com validação de JWT por assinatura e claims, já está próxima desse modelo de resource server.

## Parte 5 - Análise de segurança

Riscos identificados e as respectivas mitigações, com a referência ao OWASP.

| Risco                                                                           | Mitigação                                                                                                                                                                                                             | OWASP    |
| ------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- |
| Roubo ou interceptação do token JWT                                           | HTTPS com HSTS em produção, expiração curta do access token, refresh com rotação e revogação, e Content-Security-Policy estrita no front para reduzir XSS                                                       | A02, A07 |
| Ataque de força bruta ou enumeração de usuários no login                    | Comparação de senha em tempo constante, mensagem de erro genérica que não revela se o e-mail existe, e recomendação de rate limiting por IP na borda                                                              | A07      |
| Quebra de controle de acesso e acesso indevido a dados de outro usuário (IDOR) | RBAC deny-by-default, checagem a nível de objeto (client só o próprio registro) e atribuição de perfil restrita ao admin                                                                                           | A01      |
| Confusão de algoritmo ou aceitação de`alg=none` no JWT                     | Algoritmo HS256 fixado na verificação; o servidor nunca confia no cabeçalho`alg` do token recebido                                                                                                                 | A02      |
| Senha armazenada de forma insegura                                              | Hash com bcrypt via`password_hash`, com salt automático; a senha e o hash nunca são retornados pela API                                                                                                             | A02      |
| Injeção de SQL                                                                | Todas as consultas usam PDO com prepared statements e parâmetros vinculados;`EMULATE_PREPARES` desligado                                                                                                             | A03      |
| Configuração incorreta de segurança                                          | Cabeçalhos de segurança em toda resposta (CSP, X-Frame-Options DENY, X-Content-Type-Options nosniff, Referrer-Policy, Permissions-Policy), CORS com lista de permissão explícita e erros genéricos sem stack trace | A05      |
| Componentes vulneráveis ou desatualizados                                      | `composer audit` na esteira de CI, dependências mínimas e versão da biblioteca de JWT travada em linha segura (>= 7.0, sem a CVE-2025-45769)                                                                       | A06, A11 |

### Controles adicionais implementados

- Prevenção de escalonamento vertical: o operator não edita admin nem outro operator, e a redefinição de senha de terceiros é exclusiva do admin (ver Parte 3).
- Refresh token com rotação atômica: a revogação condicional decide o vencedor em caso de corrida, evitando que um mesmo refresh gere duas sessões válidas.
- Violação de unicidade (e-mail ou brinco) em corrida é traduzida para 409, em vez de estourar 500.
- Respostas com token saem com `Cache-Control: no-store`; o cabeçalho HSTS é emitido para produção com TLS.
- Os cabeçalhos de segurança também são aplicados na camada do Apache, cobrindo o HTML da SPA e os assets estáticos, não só as respostas da API.
- O log de erro de servidor registra apenas a classe da exceção, o método e o caminho, sem a mensagem, para não vazar PII (por exemplo, um e-mail em erro de integridade).
- A exclusão de um usuário revoga os refresh tokens dele, cortando a renovação de acesso.

### Limitações conhecidas e trade-offs

- Rate limiting no login: não é aplicado na camada da aplicação; a recomendação é impor throttling por IP e por conta na borda (proxy reverso ou API gateway). Já há mitigação de enumeração (mensagem genérica e comparação em tempo constante).
- Armazenamento de token no front: o access e o refresh token ficam em `localStorage`, padrão comum em SPA, com o risco de XSS mitigado pela CSP estrita e pela expiração curta do access token. A alternativa mais robusta é guardar o refresh em cookie `HttpOnly`, `Secure` e `SameSite=Strict`, mantendo só o access token curto em memória.
- Janela do access token: por ser stateless, um usuário rebaixado ou excluído mantém o access token válido até expirar (no máximo 15 minutos); a exclusão já revoga os refresh tokens, cortando a renovação.
- Imagens base do Docker são referenciadas por tag; para reprodutibilidade total, poderiam ser fixadas por digest.

## Como testar a API

Com a aplicação no ar, veja o README, um fluxo rápido com `curl`:

```bash
# login e captura do access token
curl -s -X POST http://localhost:8793/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@lidafacil.local","password":"SUA_SENHA_DO_ENV"}'

# usando o token retornado
TOKEN="<access_token>"
curl -s http://localhost:8793/api/v1/users -H "Authorization: Bearer ${TOKEN}"
```

A interface web (SPA) na raiz `http://localhost:8793/` permite fazer login, gerir usuários e o rebanho e visualizar as respostas cruas da API em um painel, o que facilita a demonstração das funcionalidades.
