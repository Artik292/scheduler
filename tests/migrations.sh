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
docker compose exec -T -e DB_NAME="$migration_test_db" app php bin/migrate.php --through=006_teacher_multiple_subjects
docker compose exec -T -e DB_NAME="$migration_test_db" app php bin/migrate.php --through=006_teacher_multiple_subjects
docker compose exec -T -e DB_NAME="$migration_test_db" app php < tests/verify_migration.php
root_sql "DELETE FROM ${migration_test_db}.schema_migrations WHERE version = '004_teacher_reception_hours'; ALTER TABLE ${migration_test_db}.teacher DROP COLUMN end_time; UPDATE ${migration_test_db}.teacher SET start_time = '16:30';"
docker compose exec -T -e DB_NAME="$migration_test_db" app php bin/migrate.php --through=006_teacher_multiple_subjects
docker compose exec -T -e DB_NAME="$migration_test_db" app php bin/migrate.php --through=006_teacher_multiple_subjects
docker compose exec -T -e DB_NAME="$migration_test_db" -e EXPECTED_TEACHER_START=16:30 app php < tests/verify_migration.php
root_sql "UPDATE ${migration_test_db}.text SET text = 'Custom absence message' WHERE code = 'teacher_unavailable_message'; DELETE FROM ${migration_test_db}.schema_migrations WHERE version = '005_teacher_unavailable_message';"
docker compose exec -T -e DB_NAME="$migration_test_db" app php bin/migrate.php --through=006_teacher_multiple_subjects
docker compose exec -T -e DB_NAME="$migration_test_db" app php bin/migrate.php --through=006_teacher_multiple_subjects
docker compose exec -T -e DB_NAME="$migration_test_db" -e EXPECTED_TEACHER_START=16:30 -e 'EXPECTED_UNAVAILABLE_MESSAGE=Custom absence message' app php < tests/verify_migration.php
docker compose exec -T -e DB_NAME="$migration_test_db" app php < tests/roster_import.php
