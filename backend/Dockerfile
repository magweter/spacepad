FROM composer:lts AS php_builder

WORKDIR /app

# Copy dependency manifests first — composer install is cached until lock file changes
COPY composer.json composer.lock ./
RUN composer install --no-cache --no-dev --no-scripts --no-autoloader --ansi --no-interaction --ignore-platform-reqs

# Only the PSR-4 autoload paths are needed to generate the optimised classmap
# (composer.json defines: App\ => app/, Database\Factories\ => database/factories/, Database\Seeders\ => database/seeders/)
COPY app ./app
COPY database ./database

RUN composer dump-autoload -o --ignore-platform-reqs --no-scripts

# Node.js builder stage
FROM node:22-alpine AS node_builder

WORKDIR /app

# Install pnpm
RUN corepack enable && corepack prepare pnpm@10.11.0 --activate

COPY package.json pnpm-lock.yaml ./
COPY vite.config.js ./
COPY resources ./resources

RUN pnpm install --frozen-lockfile && pnpm run build

# Base image with PHP extensions
FROM serversideup/php:8.4-fpm-nginx AS base

USER root
RUN install-php-extensions opentelemetry protobuf

FROM base AS production

ARG GIT_TAG
ARG GIT_COMMIT

ENV SPACEPAD_VERSION=${GIT_TAG:-${GIT_COMMIT}}

# Copy only the files needed to run the application in production
# (excludes tests/, requests/, docs/, phpunit.xml, vite/tailwind configs, package.json, etc.)
COPY --chown=www-data:www-data artisan composer.json composer.lock /var/www/html/
COPY --chown=www-data:www-data app /var/www/html/app
COPY --chown=www-data:www-data bootstrap /var/www/html/bootstrap
COPY --chown=www-data:www-data config /var/www/html/config
COPY --chown=www-data:www-data database /var/www/html/database
COPY --chown=www-data:www-data lang /var/www/html/lang
COPY --chown=www-data:www-data public /var/www/html/public
COPY --chown=www-data:www-data resources /var/www/html/resources
COPY --chown=www-data:www-data routes /var/www/html/routes
COPY --chown=www-data:www-data storage /var/www/html/storage

# Bring in built artefacts from builder stages
COPY --chown=www-data:www-data --from=php_builder /app/vendor /var/www/html/vendor
COPY --chown=www-data:www-data --from=node_builder /app/public/build /var/www/html/public/build

USER www-data

RUN mkdir -p storage && touch storage/database.sqlite
