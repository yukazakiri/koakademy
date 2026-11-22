# Self-contained local development container for KoAkademy.
# Builds cleanly on a fresh clone (before vendor/ or node_modules/ exist)
# and pins the same patched PHP 8.5.12 and Node.js 26.13.0 runtimes used in
# production Docker builds.

FROM node:26.13.0-bookworm-slim@sha256:bc0a2afd3b74ce12222fc8bab99271e66257f021078f5f4a5d687a77d280c1d9 AS node-runtime

FROM composer:2.10@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS composer-bin

FROM php:8.5.12-cli-bookworm

ARG WWWUSER=1000
ARG WWWGROUP=1000

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_FUND=0
ENV SUPERVISOR_PHP_COMMAND="/usr/local/bin/php -d variables_order=EGPCS /var/www/html/artisan serve --host=0.0.0.0 --port=80"
ENV SUPERVISOR_PHP_USER="sail"

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    git \
    gosu \
    libcap2-bin \
    libfreetype6-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libpq-dev \
    librsvg2-bin \
    libsqlite3-dev \
    libzip-dev \
    libffi-dev \
    mdbtools \
    postgresql-client \
    sqlite3 \
    supervisor \
    unzip \
    zip \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        ffi \
        gd \
        intl \
        pcntl \
        pdo_pgsql \
        pdo_sqlite \
        sockets \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
COPY --from=node-runtime /usr/local/bin/node /usr/local/bin/node
COPY --from=node-runtime /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

RUN setcap "cap_net_bind_service=+ep" /usr/local/bin/php \
    && groupadd --force -g "${WWWGROUP}" sail \
    && useradd -ms /bin/bash --no-user-group -g "${WWWGROUP}" -u "${WWWUSER}" sail \
    && git config --system --add safe.directory /var/www/html

COPY php.ini /usr/local/etc/php/conf.d/99-koakademy-dev.ini
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY start-container /usr/local/bin/start-container
RUN chmod +x /usr/local/bin/start-container

EXPOSE 80/tcp 5173/tcp

ENTRYPOINT ["start-container"]
