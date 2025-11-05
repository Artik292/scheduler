#!/bin/bash
set -e

# Allow overriding the PHP variables order without rebuilding the image
if [[ -n "${PHP_VARIABLES_ORDER:-}" ]]; then
  echo "variables_order = \"${PHP_VARIABLES_ORDER}\"" > /usr/local/etc/php/conf.d/variables-order.ini
fi

exec apache2-foreground
