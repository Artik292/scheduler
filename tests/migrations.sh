#!/bin/bash
# Run only against a disposable local Compose project.
set -euo pipefail
migration_test_db="scheduler_migration_test_${RANDOM}"
root_sql() {
    docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root -e "$1"' sh "$1"
}
cleanup() {
    root_sql "DROP DATABASE IF EXISTS ${migration_test_db}; REVOKE ALL PRIVILEGES ON ${migration_test_db}.* FROM 'scheduler'@'%';" >/dev/null
}
trap cleanup EXIT
root_sql "CREATE DATABASE ${migration_test_db}; GRANT ALL PRIVILEGES ON ${migration_test_db}.* TO 'scheduler'@'%';"
docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root "$1"' sh "$migration_test_db" < tests/legacy.sql
docker compose exec -T -e DB_NAME="$migration_test_db" app php bin/migrate.php
docker compose exec -T -e DB_NAME="$migration_test_db" app php bin/migrate.php
docker compose exec -T -e DB_NAME="$migration_test_db" app php < tests/verify_migration.php
