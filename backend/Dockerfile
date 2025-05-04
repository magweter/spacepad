FROM composer:2.6.6 AS php_builder

WORKDIR /app

COPY composer.json composer.lock ./

COPY ./ /app

RUN composer install --no-cache --no-dev --no-scripts --no-autoloader --ansi --no-interaction --ignore-platform-reqs \
    && composer dump-autoload -o

# Set our base image
FROM serversideup/php:8.2-unit as base

FROM base as production

COPY --chown=www-data:www-data . /var/www/html
COPY --chown=www-data:www-data --from=php_builder /app/vendor /var/www/html/vendor

USER www-data

RUN touch database/database.sqlite
RUN chown www-data:www-data database/database.sqlite
RUN chmod 664 database/database.sqlite