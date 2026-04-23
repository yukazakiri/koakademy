ARG PHP_VERSION=8.5
ARG FRANKENPHP_VERSION=1.9
ARG COMPOSER_VERSION=2.8
ARG APP_NAME=KoAkademy
ARG APP_ENV=production
ARG APP_DEBUG=false
ARG APP_URL=https://koakademy.yukazaki.com

# -----------------------------------------------------------------------------
# Stage 1: Install PHP Dependencies
# -----------------------------------------------------------------------------
FROM composer:${COMPOSER_VERSION} AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Needed for cweagans/composer-patches during install
COPY patches ./patches
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-autoloader \
    --no-ansi \
    --no-scripts \
    --no-progress \
    --ignore-platform-reqs

COPY . .
RUN mkdir -p \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    storage/framework/testing \
    storage/logs \
    bootstrap/cache
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-scripts

# -----------------------------------------------------------------------------
# Stage 2: Build Frontend Assets
# -----------------------------------------------------------------------------
FROM oven/bun:1 AS build-assets
WORKDIR /app
COPY package.json bun.lock ./
RUN bun install --no-save
COPY . .
# Copy vendor directory for Filament assets
COPY --from=vendor /app/vendor ./vendor
RUN bun run build

# -----------------------------------------------------------------------------
# Stage 3: Final Runtime Image
# -----------------------------------------------------------------------------
FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}

LABEL maintainer="Yuka <128908897+yukazakiri@users.noreply.github.com>"
LABEL org.opencontainers.image.title="KoAkademy"
LABEL org.opencontainers.image.description="Production-ready Docker image for KoAkademy"
LABEL org.opencontainers.image.source=https://github.com/exaco/laravel-docktane
LABEL org.opencontainers.image.licenses=MIT

ARG USER_ID=1000
ARG GROUP_ID=1000
ARG ROOT=/app
ARG TZ=UTC

ENV TERM=xterm-color
ENV OCTANE_SERVER=frankenphp
ENV TZ=${TZ}
ENV USER=laravel
ENV COMPOSER_FUND=0
ENV COMPOSER_MAX_PARALLEL_HTTP=48
ENV XDG_CONFIG_HOME=${ROOT}/.config
ENV XDG_DATA_HOME=${ROOT}/.data

WORKDIR ${ROOT}

# Install system dependencies and PostgreSQL clients
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    ca-certificates \
    zip \
    unzip \
    supervisor \
    libcap2-bin \
    libpng-dev \
    libzip-dev \
    libicu-dev \
    libpq-dev \
    postgresql-client \
    postgresql-17 && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN install-php-extensions \
    apcu \
    bz2 \
    pcntl \
    mbstring \
    bcmath \
    sockets \
    pdo_pgsql \
    pgsql \
    opcache \
    exif \
    zip \
    intl \
    gd \
    redis \
    soap

# Install Supercronic
RUN curl -fsSLO "https://github.com/aptible/supercronic/releases/download/v0.2.33/supercronic-linux-amd64" \
    && chmod +x supercronic-linux-amd64 \
    && mv supercronic-linux-amd64 /usr/local/bin/supercronic \
    && mkdir -p /etc/supercronic \
    && echo "*/1 * * * * php ${ROOT}/artisan schedule:run --no-interaction" > /etc/supercronic/laravel

# PHP Config
RUN cp ${PHP_INI_DIR}/php.ini-production ${PHP_INI_DIR}/php.ini
COPY docker/php.ini ${PHP_INI_DIR}/conf.d/99-custom.ini

# Copy Supervisor configs
COPY --link docker/supervisord.conf /etc/supervisord.conf
COPY --link docker/supervisord.frankenphp.conf /etc/supervisor/conf.d/
COPY --link docker/supervisord.horizon.conf /etc/supervisor/conf.d/
COPY --link docker/supervisord.scheduler.conf /etc/supervisor/conf.d/
COPY --link docker/supervisord.pulse.conf /etc/supervisor/conf.d/
COPY --link docker/supervisord.worker.conf /etc/supervisor/conf.d/
COPY --link docker/supervisord.nightwatch.conf /etc/supervisor/conf.d/

# Copy scripts
COPY --link docker/start-container /usr/local/bin/start-container
COPY --link docker/healthcheck /usr/local/bin/healthcheck
COPY --link docker/run-scripts.sh /usr/local/bin/run-scripts.sh
COPY --link docker/docker-scripts /var/www/html/docker-scripts
RUN chmod +x /usr/local/bin/start-container /usr/local/bin/healthcheck /usr/local/bin/run-scripts.sh && \
    chmod +x /var/www/html/docker-scripts/*.sh 2>/dev/null || true

# Create user and directories
RUN groupadd --force -g ${GROUP_ID} ${USER} \
    && useradd -ms /bin/bash --no-user-group -g ${GROUP_ID} -u ${USER_ID} ${USER} \
    && mkdir -p \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    storage/framework/testing \
    storage/logs \
    bootstrap/cache \
    .config \
    .data \
    /tmp/opcache-file-cache

# Copy application code from build stages
COPY --chown=${USER}:${USER} --from=vendor /app/vendor ./vendor
COPY --chown=${USER}:${USER} --from=build-assets /app/public/build ./public/build
COPY --chown=${USER}:${USER} . .

# Set ownership
RUN chown -R ${USER}:${USER} ${ROOT}/storage ${ROOT}/bootstrap/cache ${ROOT}/.config ${ROOT}/.data \
    && chmod -R 777 /tmp/opcache-file-cache

# Allow FrankenPHP to bind to port 80 and 443 (if needed) and run as non-root
RUN setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp

USER root

EXPOSE 8000
EXPOSE 2019

ENTRYPOINT ["start-container"]

HEALTHCHECK --start-period=5s --interval=1s --timeout=3s --retries=10 CMD healthcheck || exit 1
