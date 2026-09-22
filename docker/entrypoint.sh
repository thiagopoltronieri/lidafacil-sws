#!/bin/sh
# ponto de entrada do container: prepara o banco e sobe o apache
set -e

cd /var/www/html

# com mysql o banco é um serviço a parte; espera ele aceitar conexão antes de migrar
if [ "${DB_DRIVER:-sqlite}" = "mysql" ]; then
    echo "aguardando o banco mysql aceitar conexão..."
    tries=0
    until php bin/wait-for-db.php; do
        tries=$((tries + 1))
        if [ "$tries" -ge 30 ]; then
            echo "banco mysql não respondeu a tempo" >&2
            exit 1
        fi
        sleep 2
    done
    echo "banco mysql pronto"
fi

# aplica o schema de forma idempotente e semeia o gestor inicial se ainda não existir
php bin/migrate.php
# o seed exige SEED_ADMIN_PASSWORD; sem ela a app sobe mesmo assim, mas não mascaramos falha do banco
if [ -z "${SEED_ADMIN_PASSWORD:-}" ]; then
    echo "seed do gestor ignorado: defina SEED_ADMIN_PASSWORD para criar o acesso inicial"
else
    php bin/seed.php || echo "seed do gestor falhou; verifique o banco e as credenciais" >&2
fi

# entrega o processo para o apache em foreground
exec apache2-foreground
