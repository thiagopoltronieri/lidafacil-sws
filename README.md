# Lida Fácil

API REST segura para gestão de usuários e do rebanho, com autenticação por JSON Web Token (JWT) e controle de acesso por perfil (RBAC), acompanhada de uma interface web (SPA) que consome e demonstra a API. O tema é o manejo pecuário, bovinos, suínos e ovinos, mas o foco do projeto é a segurança da API: autenticação, autorização e boas práticas de proteção.

## Objetivo

Disponibilizar uma API REST que permita cadastrar, consultar, atualizar e excluir usuários, com login que gera um token JWT e três perfis de acesso (Administrador, Operador e Cliente), evidenciando na prática os conceitos de autenticação, autorização e segurança de aplicações web. O rebanho entra como um segundo recurso protegido, para demonstrar o RBAC atuando sobre mais de um domínio.

## Funcionalidades

- Login por e-mail e senha com emissão de JWT e refresh token.
- CRUD de usuários (nome, e-mail, senha e perfil de acesso).
- CRUD do rebanho e histórico de vacinação por animal.
- Controle de acesso por perfil (RBAC), com endpoints protegidos por autorização.
- Interface web (SPA) com login, listagem, cadastro, edição, exclusão e um painel que mostra as respostas cruas da API.
- Interface responsiva (celular, tablet e desktop).

## Tecnologias utilizadas

- PHP 8.4 com o microframework Slim 4.
- Autenticação JWT com a biblioteca firebase/php-jwt (HS256).
- Persistência em MySQL 8 via PDO com consultas parametrizadas; os testes rodam em SQLite em memória.
- Front-end próprio: SPA em HTML e JavaScript puro (sem framework), CSS com glassmorphism e fonte Nunito, tudo servido localmente.
- Docker e Docker Compose para conteinerização.
- Qualidade: PHPUnit, PHPStan (nível 8) e PHP-CS-Fixer.

A documentação completa dos endpoints, do JWT, do RBAC, do OAuth 2.0 e a análise de segurança está em [docs/API.md](docs/API.md).

## Como instalar e executar

Pré-requisito: Docker e Docker Compose. Não é preciso ter PHP nem Composer na máquina.

1. Copie o modelo de ambiente e defina os segredos:

```bash
cp .env.example .env
```

No `.env`, ajuste `DB_PASSWORD`, `DB_ROOT_PASSWORD`, o `JWT_SECRET` (gere um valor forte, por exemplo com `openssl rand -hex 32`) e as senhas dos usuários semeados (`SEED_ADMIN_PASSWORD`, `SEED_OPERATOR_PASSWORD`, `SEED_CLIENT_PASSWORD`).

2. Suba a aplicação e o banco:

```bash
docker compose up --build -d
```

A aplicação sobe em `http://localhost:8793` (porta configurável por `APP_PORT`) e o MySQL fica exposto só no loopback do host na porta `33193`. No primeiro start o container espera o banco, aplica o schema e semeia um usuário por perfil.

3. Confira que está no ar:

```bash
docker compose ps
curl http://localhost:8793/health
```

> Nota sobre o `.env`: por segurança ele não é versionado, não está no Git nem no GitHub. No pacote de entrega (`.zip`) enviado ao professor vai um `.env` já pronto, só para facilitar a avaliação local. Ao clonar do GitHub, copie o `.env.example` para `.env` e defina os valores.

## Acesso de demonstração

No primeiro start são criados três usuários, um por perfil, com os e-mails e senhas definidos no `.env`:

- Administrador: `admin@lidafacil.local`
- Operador: `operator@lidafacil.local`
- Cliente: `client@lidafacil.local`

Abra `http://localhost:8793/` no navegador, faça login e explore. O administrador gere todos os usuários e o rebanho; o operador gere o rebanho e consulta ou atualiza usuários; o cliente vê apenas o próprio cadastro.

## Como testar

A suíte automatizada roda dentro do container PHP 8.4, sem depender de nada instalado na máquina:

```bash
# testes (unidade e integração), análise estática e estilo
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app php:8.4-cli php vendor/bin/phpunit
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app php:8.4-cli php vendor/bin/phpstan analyse
docker run --rm -u "$(id -u):$(id -g)" -e PHP_CS_FIXER_IGNORE_ENV=1 -v "$PWD":/app -w /app php:8.4-cli php vendor/bin/php-cs-fixer fix --dry-run --diff
```

Também dá para testar a API direto por linha de comando com `curl` ou por ferramentas como Postman ou Insomnia. Exemplos em [docs/API.md](docs/API.md). Detalhes de desenvolvimento e testes de ponta a ponta estão em [README-DEV.md](README-DEV.md).

## Segurança

Senhas com hash bcrypt (nunca retornadas pela API), JWT assinado com algoritmo fixo (HS256, sem confiar no cabeçalho `alg` do token), refresh token com rotação e revogação guardado apenas como hash, controle de acesso deny-by-default com checagem a nível de objeto (anti-IDOR), consultas sempre parametrizadas, CORS com lista de permissão, cabeçalhos de segurança (CSP restritiva, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy) e erros genéricos que não vazam detalhe interno. O container roda como usuário sem privilégios de root. A análise de riscos completa está em [docs/API.md](docs/API.md).

## Contexto

Projeto de estudo sobre desenvolvimento de sistemas web seguros, com foco em autenticação, autorização e boas práticas de segurança aplicadas a uma aplicação real.

## Licença

Distribuído sob a licença MIT. Veja o arquivo [LICENSE](LICENSE).
