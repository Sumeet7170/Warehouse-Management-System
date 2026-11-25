#!/bin/sh
set -e

PORT="${PORT:-10000}"

sed -ri "s/^Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

DB_HOST="${WMS_DB_HOST:-127.0.0.1}"

if [ "$DB_HOST" = "127.0.0.1" ] || [ "$DB_HOST" = "localhost" ]; then
    mkdir -p /var/run/mysqld
    chown -R mysql:mysql /var/run/mysqld /var/lib/mysql

    if [ ! -d "/var/lib/mysql/mysql" ]; then
        mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/dev/null
    fi

    mariadbd --user=mysql --datadir=/var/lib/mysql &

    i=0
    until mariadb-admin ping --silent >/dev/null 2>&1; do
        i=$((i + 1))
        if [ "$i" -ge 30 ]; then
            echo "MariaDB failed to start within 30s" >&2
            exit 1
        fi
        sleep 1
    done

    mariadb <<'SQL'
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL

    DB_NAME="${WMS_DB_NAME:-wms_db}"
    if ! mariadb -e "USE \`${DB_NAME}\`; SHOW TABLES LIKE 'users';" 2>/dev/null | grep -q users; then
        echo "Seeding WMS showcase database (${DB_NAME})..."
        mariadb < /var/www/html/backend/database/showcase_seed.sql
    fi
fi

mkdir -p /var/www/html/backend/logs
chown -R www-data:www-data /var/www/html/backend/logs

exec apache2-foreground
