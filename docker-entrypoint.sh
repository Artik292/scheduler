#!/bin/bash

# Стартуем MariaDB
service mysql start

# Настраиваем root пользователя и создаем базу данных
mysqladmin -u root password "$MYSQL_ROOT_PASSWORD"
mysql -u root -p"$MYSQL_ROOT_PASSWORD" mysql -e "UPDATE user SET plugin='mysql_native_password' WHERE User='root';"
mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "FLUSH PRIVILEGES;"
mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS $MYSQL_DATABASE;"

# Выполняем SQL скрипт, если он существует
if [ -f /var/www/html/docs/scheduler.sql ]; then
    cat /var/www/html/docs/scheduler.sql | mysql -u root -p"$MYSQL_ROOT_PASSWORD" $MYSQL_DATABASE
fi
sed -i -e 's/variables_order = "GPCS"/variables_order = "EGPCS"/g' /etc/php/5.6/apache2/php.ini
# Запускаем Apache в foreground
apache2ctl -D FOREGROUND
