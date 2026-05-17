FROM composer:lts AS php_builder

WORKDIR /app

COPY composer.json composer.lock ./

COPY ./ /app

RUN composer install --no-cache --no-dev --no-scripts --no-autoloader --ansi --no-interaction --ignore-platform-reqs \
    && composer dump-autoload -o --ignore-platform-reqs --no-scripts

# Node.js builder stage
FROM node:22-alpine AS node_builder

WORKDIR /app

# Install pnpm
RUN corepack enable && corepack prepare pnpm@10.11.0 --activate

COPY package.json pnpm-lock.yaml ./
COPY vite.config.js ./
COPY resources ./resources

RUN pnpm install --config.only-built-dependencies-none=true && pnpm run build

# Set our base image
FROM serversideup/php:8.4-fpm-nginx AS base

# Switch to root before installing our PHP extensions
USER root
RUN install-php-extensions opentelemetry protobuf

FROM base AS production

# Add build arguments for git information
ARG GIT_TAG
ARG GIT_COMMIT

# Set environment variable with tag or commit hash
ENV SPACEPAD_VERSION=${GIT_TAG:-${GIT_COMMIT}}

COPY --chown=www-data:www-data . /var/www/html
COPY --chown=www-data:www-data --from=php_builder /app/vendor /var/www/html/vendor
COPY --chown=www-data:www-data --from=node_builder /app/public/build /var/www/html/public/build

USER www-data

RUN mkdir -p storage && touch storage/database.sqlite
