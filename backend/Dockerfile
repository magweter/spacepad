FROM composer:lts AS php_builder

WORKDIR /app

COPY composer.json composer.lock ./

COPY ./ /app

RUN composer install --no-cache --no-scripts --no-autoloader --ansi --no-interaction --ignore-platform-reqs \
    && composer dump-autoload -o

# Set our base image
FROM serversideup/php:8.4-unit as base

FROM base as production

COPY --chown=www-data:www-data . /var/www/html
COPY --chown=www-data:www-data --from=php_builder /app/vendor /var/www/html/vendor

USER www-data

RUN mkdir -p database && touch database/database.sqlite