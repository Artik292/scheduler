# Используем официальный образ Ubuntu
FROM ubuntu:20.04

COPY . /var/www/html/

# Установим переменные окружения, чтобы избежать интерактивных запросов во время установки пакетов
ENV DEBIAN_FRONTEND=noninteractive
ENV MYSQL_ROOT_PASSWORD=rootpassword
ENV MYSQL_DATABASE=testdb

# Обновляем пакеты и устанавливаем необходимые зависимости
RUN apt-get update && \
    apt-get install -y \
    software-properties-common \
    wget \
    vim \
    curl \
    zip \
    unzip \
    apt-transport-https \
    lsb-release \
    ca-certificates

# Добавляем репозиторий для PHP 5.6 от Ondrej, поддерживающий старые версии PHP
RUN add-apt-repository ppa:ondrej/php && apt-get update

# Устанавливаем Apache, PHP 5.6 и необходимые модули
RUN apt-get install -y \
    apache2 \
    php5.6 \
    libapache2-mod-php5.6 \
    php5.6-mysql \
    php5.6-cli \
    php5.6-json \
    php5.6-curl \
    php5.6-xml \
    php5.6-mbstring \
    php5.6-zip

# Установка MariaDB, совместимой с PHP 5.6 (версии MariaDB 10.3)
RUN apt-get install -y mariadb-server-10.3 mariadb-client-10.3

# Устанавливаем права на работу Apache
RUN chown -R www-data:www-data /var/www/html

# Включаем модуль Apache для переписывания URL
RUN a2enmod rewrite

# Копируем файл с настройками Apache (опционально, можно создать свой)
COPY ./000-default.conf /etc/apache2/sites-available/000-default.conf

# Открываем порты для Apache и MariaDB
EXPOSE 80 3306

# Настраиваем MariaDB для автоматической установки root пароля
RUN service mysql start



# Запускаем Apache и MariaDB при старте контейнера
CMD service apache2 start && service mysql start && \
    mysqladmin -u root password "$MYSQL_ROOT_PASSWORD" && \
    mysql -u root -p"$MYSQL_ROOT_PASSWORD" mysql -e "UPDATE user SET plugin='mysql_native_password' WHERE User='root';" && \
    mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "FLUSH PRIVILEGES;" && \
    mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS $MYSQL_DATABASE;" && \
    cat /var/www/html/docs/scheduler.sql |  mysql -u root -p"$MYSQL_ROOT_PASSWORD" $MYSQL_DATABASE && \
    tail -f /var/log/apache2/access.log
