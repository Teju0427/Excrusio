# Excursio production image — works on Render (free web service) and Azure App Service (custom container).
# Stage 1: install PHP dependencies
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# Stage 2: runtime
FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev unzip ca-certificates \
    && docker-php-ext-install pdo_mysql opcache bcmath zip \
    && a2enmod rewrite headers expires \
    && printf 'ServerTokens Prod\nServerSignature Off\nTraceEnable Off\n' > /etc/apache2/conf-available/zz-excursio.conf \
    && a2enconf zz-excursio \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/excursio.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/start.sh /usr/local/bin/start.sh

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
RUN chmod +x /usr/local/bin/start.sh \
    && chown -R www-data:www-data storage bootstrap/cache \
    && rm -f .env

ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr PORT=10000
EXPOSE 10000
CMD ["/usr/local/bin/start.sh"]
