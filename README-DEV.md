# Guia de desenvolvimento

Notas para quem for mexer no código do Lida Fácil.

## Pré-requisitos

- Docker: o ambiente de referência é o container `php:8.4`;
- Composer: opcional na máquina; dá para rodar tudo via container.

## Ambiente

O container `php:8.4-apache` é a base do ambiente. Localmente, use o mesmo PHP 8.4 via Docker para evitar diferença de versão.

Instalar dependências:

```bash
docker run --rm -u "$(id -u):$(id -g)" \
  -e COMPOSER_HOME=/tmp/composer \
  -v "$PWD":/app -w /app composer:2 install
```

Subir a aplicação com o banco MySQL:

```bash
cp .env.example .env   # defina DB_PASSWORD, DB_ROOT_PASSWORD, JWT_SECRET e as senhas de seed
docker compose up --build
```

## Arquitetura

A aplicação é API-first e stateless: o backend expõe apenas a API REST em `/api/v1`, protegida por JWT, e o front-end é uma SPA em HTML e JavaScript puro que consome essa API. Não há sessão de servidor.

O código segue as camadas Domain, Application e Infrastructure (PSR-4 `App\` para `src/`):

- Domain: entidades e regras puras (User, Role, Animal, Vaccination e seus validadores).
- Application: serviços de caso de uso (UserService, AnimalService, AuthService), segurança (JwtService, RefreshTokenService, AccessPolicy, PasswordHasher) e exceções de fluxo (ValidationException, ConflictException).
- Infrastructure: acesso a dados (PDO e repositórios) e a camada HTTP (controllers da API, middlewares de JWT, CORS e cabeçalhos de segurança, e o handler de erros em JSON).

A autenticação usa JWT HS256 assinado com o segredo do ambiente; a autorização (RBAC) fica centralizada na `AccessPolicy`, aplicada em cada endpoint com checagem a nível de objeto.

## Banco de dados

A aplicação usa MySQL 8 em produção e desenvolvimento; os testes usam SQLite em memória, o que mantém a suíte rápida e sem depender de um serviço externo. O SQL dos repositórios é portável entre os dois.

- `config/settings.php` escolhe o schema conforme `DB_DRIVER`: `database/schema.mysql.sql` para mysql, `database/schema.sql` (sqlite) caso contrário.
- No compose, o serviço `lidafacil-db` publica o MySQL na porta `33193` do host; dentro da rede do compose a aplicação fala com `lidafacil-db:3306`.
- O `entrypoint.sh` espera o banco aceitar conexão antes de migrar e semear.
- O seed cria um usuário por perfil (admin, operator, client), de forma idempotente por e-mail, apenas para os que tiverem senha definida no ambiente.

## Checks de qualidade

Os gates rodam dentro do container 8.4:

```bash
docker run --rm -u "$(id -u):$(id -g)" -e PHP_CS_FIXER_IGNORE_ENV=1 -v "$PWD":/app -w /app php:8.4-cli php vendor/bin/php-cs-fixer fix --dry-run --diff
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app php:8.4-cli php vendor/bin/phpstan analyse
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app php:8.4-cli php vendor/bin/phpunit
docker run --rm -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer -v "$PWD":/app -w /app composer:2 audit
```

Atalhos via composer (dentro do container): `composer cs`, `composer stan`, `composer test`, `composer check`. Para aplicar o estilo automaticamente: `composer cs:fix`.

## Git hooks

Os hooks ficam versionados em `.githooks/`. Ative-os uma vez no clone:

```bash
git config core.hooksPath .githooks
```

O `pre-commit` roda estilo, análise estática e testes antes de cada commit, usando o container 8.4 quando o Docker está disponível.

## Testes E2E

A validação de ponta a ponta usa o Playwright, cobrindo o login e o controle de acesso por perfil (RBAC), nos tamanhos de celular e desktop. Os specs ficam em `tests/e2e/` e rodam contra a aplicação no ar:

```bash
docker compose up --build -d          # sobe a aplicação em http://localhost:8793
cd tests/e2e
npm install
npx playwright install --with-deps chromium
npx playwright test
```

Defina no ambiente as senhas de seed usadas pelos testes (as mesmas do `.env`) ou exporte `E2E_BASE_URL` para apontar para outra URL.

## Estrutura

```
src/
  Domain/         entidades e regras (usuário, perfil, animal, vacinação)
  Application/    serviços, segurança (jwt, rbac, hash) e exceções de fluxo
  Infrastructure/ banco (PDO), repositórios e camada HTTP (controllers da api, middlewares, erros)
  Kernel.php      montagem do container e da aplicação Slim
  routes.php      definição das rotas da api
public/           front controller, a SPA (index.html) e assets (css, js, fontes, imagens)
database/         schema sqlite (testes) e schema mysql (aplicação)
bin/              scripts de migração, seed e espera do banco
config/           configuração por ambiente
tests/            unit, integration e e2e
docker/           vhost do apache e entrypoint
docs/             documentação da api
```

## CI/CD

- `ci.yml`: estilo, PHPStan e PHPUnit (unidade e integração em etapas separadas) e `composer audit` em PHP 8.4.
- `docker.yml`: build da imagem e smoke test do container (sobe e confere o `/health`), a cada push e pull request na `main`. Não publica em nenhum registro.

## Convenções

- Código em inglês, comentários em pt-BR.
- Commits seguindo Conventional Commits, mensagem em pt-BR e minúscula.
- Nada de segredos no repositório; `.env` fica fora do versionamento.
