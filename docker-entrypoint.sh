#!/bin/sh
set -eu

case "${PORT:-8080}" in
    ''|*[!0-9]*) echo 'PORT must be numeric' >&2; exit 1 ;;
esac
if [ "${PORT:-8080}" -lt 1 ] || [ "${PORT:-8080}" -gt 65535 ]; then
    echo 'PORT must be between 1 and 65535' >&2
    exit 1
fi
sed -i "s/^Listen .*/Listen ${PORT:-8080}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT:-8080}>/" /etc/apache2/sites-available/000-default.conf
exec docker-php-entrypoint "$@"
