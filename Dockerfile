FROM composer:2.10.3 AS dependencies
WORKDIR /app
COPY composer.json composer.lock ./
COPY src/ ./src/
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-plugins --classmap-authoritative --ignore-platform-req=ext-pdo_mysql

FROM php:8.3.36-apache-bookworm AS runtime
RUN docker-php-ext-install -j"$(nproc)" pdo_mysql \
    && a2enmod headers \
    && printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/security.conf
WORKDIR /app
COPY --from=dependencies /app/vendor/ ./vendor/
COPY composer.json composer.lock ./
COPY src/ ./src/
COPY public/ ./public/
COPY *.php ./
COPY logo.png logo.ico ./public/
RUN cp public/logo.ico public/favicon.ico
COPY database/ ./database/
COPY bin/ ./bin/
RUN cp -a vendor/atk4/ui/public public/atk \
    && chown -R root:www-data /app \
    && find /app -type d -exec chmod 755 {} + \
    && find /app -type f -exec chmod 644 {} +
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/application.ini
COPY docker-entrypoint.sh /usr/local/bin/scheduler-entrypoint
RUN chmod 755 /usr/local/bin/scheduler-entrypoint
ENV PORT=8080 APP_ENV=production
EXPOSE 8080
ENTRYPOINT ["scheduler-entrypoint"]
CMD ["apache2-foreground"]
