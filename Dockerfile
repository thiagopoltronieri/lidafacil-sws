# instala as dependências de produção com o composer
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --no-scripts \
    --optimize-autoloader

# imagem de runtime com apache + php 8.4
FROM php:8.4-apache AS runtime

# pdo_mysql para o banco em produção e sqlite/pdo_sqlite já vem embutido, usado nos testes; opcache melhora o desempenho
RUN docker-php-ext-install pdo_mysql \
    && docker-php-ext-enable opcache \
    && a2enmod rewrite headers \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# apache escuta na 8080 para conseguir rodar sem privilégios de root
RUN sed -ri 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
# copia só o necessário para runtime
COPY --chown=www-data:www-data composer.json composer.lock ./
COPY --chown=www-data:www-data src ./src
COPY --chown=www-data:www-data public ./public
COPY --chown=www-data:www-data config ./config
COPY --chown=www-data:www-data database ./database
COPY --chown=www-data:www-data bin ./bin
COPY --from=vendor --chown=www-data:www-data /app/vendor /var/www/html/vendor

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# diretórios de runtime graváveis pelo usuário www-data, nao-root
RUN mkdir -p /var/www/html/var/data \
    && chown -R www-data:www-data /var/www/html/var /var/run/apache2 /var/log/apache2 \
    && chmod -R 775 /var/www/html/var

USER www-data

EXPOSE 8080

# healthcheck sem depender de curl: usa o próprio php
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD ["php", "-r", "$b=@file_get_contents('http://127.0.0.1:8080/health'); exit($b && str_contains($b,'\"status\":\"ok\"') ? 0 : 1);"]

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
