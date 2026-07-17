FROM php:8.3-fpm-bookworm

# Prebuilt binaries for common extensions (much faster than pecl compile)
COPY --from=mlocati/php-extension-installer:2.2.5 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql mysqli redis bcmath opcache zip pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
