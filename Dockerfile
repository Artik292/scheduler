FROM php:8.2-apache

ENV LANG=ru_RU.UTF-8 \
    LANGUAGE=ru_RU:ru \
    LC_ALL=ru_RU.UTF-8

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        locales \
        git \
        unzip \
        libzip-dev \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        mariadb-client \
    && locale-gen ru_RU.UTF-8 \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        intl \
        opcache \
        pdo_mysql \
        zip \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-dev --optimize-autoloader \
    && rm -rf /root/.composer

COPY . .

RUN chown -R www-data:www-data /var/www/html \
    && echo 'variables_order = "EGPCS"' > /usr/local/etc/php/conf.d/variables-order.ini

EXPOSE 80

CMD ["apache2-foreground"]
