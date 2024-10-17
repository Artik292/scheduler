# Используем официальный образ Ubuntu
FROM ubuntu:20.04

COPY . /var/www/html/

# Установим переменные окружения, чтобы избежать интерактивных запросов во время установки пакетов
ENV DEBIAN_FRONTEND=noninteractive
ENV MYSQL_ROOT_PASSWORD=rootpassword
ENV MYSQL_DATABASE=testdb

# Устанавливаем необходимые пакеты для локали
RUN apt-get update && apt-get install -y locales

# Генерируем локаль с поддержкой UTF-8
RUN locale-gen ru_RU.UTF-8

# Устанавливаем локаль по умолчанию на ru_RU.UTF-8
ENV LANG=ru_RU.UTF-8 \
    LANGUAGE=ru_RU:ru \
    LC_ALL=ru_RU.UTF-8

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
RUN apt install -y \
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

# Устанавливаем MariaDB, совместимую с PHP 5.6 (версии MariaDB 10.3)
RUN apt-get install -y mariadb-server-10.3 mariadb-client-10.3

# Настраиваем права для Apache
RUN chown -R www-data:www-data /var/www/html

# Включаем модуль Apache для переписывания URL
RUN a2enmod rewrite

# Копируем файл с настройками Apache (опционально, можно создать свой)
COPY ./000-default.conf /etc/apache2/sites-available/000-default.conf

# Открываем порты для Apache и MariaDB
EXPOSE 80 3306

# Скопируем точку входа
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Установка MariaDB и Apache при запуске
ENTRYPOINT ["docker-entrypoint.sh"]

# Определим, что процесс в контейнере будет запускаться в foreground
CMD ["apache2-foreground"]
