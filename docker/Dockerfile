ARG APP_NAME=KoAkademy
ARG APP_ENV=production
ARG APP_DEBUG=false
ARG APP_URL=https://koakademy.yukazaki.com

# -----------------------------------------------------------------------------
# Stage 1: Install PHP Dependencies
# -----------------------------------------------------------------------------
FROM composer:2.10@sha256:4d71c3c2109c61d5415544264b59ad4087e4c5b7244481723664138fd36d5040 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
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
# Stage 2: Generate Wayfinder Files (requires the application's PHP version)
# -----------------------------------------------------------------------------
FROM dunglas/frankenphp:1.12-php8.5@sha256:da270879b95225345b2ee984f717aef5cba7336e1f206ec005074a79235af347 AS wayfinder
RUN install-php-extensions zip
WORKDIR /app
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= PORTAL_HOST= ADMIN_HOST= php artisan wayfinder:generate --no-interaction

# -----------------------------------------------------------------------------
# Stage 3: Build Frontend Assets
# -----------------------------------------------------------------------------
FROM node:26-bookworm-slim@sha256:9e6f9357d371591e32ab6f2d8a26d63bdd0d17c29eee3f4f3e7e454d9634bf73 AS build-assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
# Copy PHP dependencies and generated Wayfinder files.
COPY --from=vendor /app/vendor ./vendor
COPY --from=wayfinder /app/resources/js/actions ./resources/js/actions
COPY --from=wayfinder /app/resources/js/routes ./resources/js/routes
COPY --from=wayfinder /app/resources/js/wayfinder ./resources/js/wayfinder
RUN WAYFINDER_GENERATE=false npm run build

# -----------------------------------------------------------------------------
# Stage 4: Final Runtime Image
# -----------------------------------------------------------------------------
FROM dunglas/frankenphp:1.12-php8.5@sha256:da270879b95225345b2ee984f717aef5cba7336e1f206ec005074a79235af347

LABEL maintainer="Yuka <128908897+yukazakiri@users.noreply.github.com>"
LABEL org.opencontainers.image.title="KoAkademy"
LABEL org.opencontainers.image.description="Self-hosted school administration and learning platform"
LABEL org.opencontainers.image.source=https://github.com/yukazakiri/koakademy
LABEL org.opencontainers.image.licenses=AGPL-3.0-or-later

ARG TARGETARCH
ARG USER_ID=1000
ARG GROUP_ID=1000
ARG ROOT=/app
ARG TZ=UTC
ARG SUPERCRONIC_VERSION=0.2.33
ARG SUPERCRONIC_SHA256_AMD64=feefa310da569c81b99e1027b86b27b51e6ee9ab647747b49099645120cfc671
ARG SUPERCRONIC_SHA256_ARM64=f1f8585c66de020fef494dd636058f99949d108f569fef00016a1c8b9eb145b3

ENV TERM=xterm-color
ENV OCTANE_SERVER=frankenphp
ENV TZ=${TZ}
ENV ROOT=${ROOT}
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
    librsvg2-bin \
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
RUN case "${TARGETARCH}" in \
        amd64) supercronic_sha256="${SUPERCRONIC_SHA256_AMD64}" ;; \
        arm64) supercronic_sha256="${SUPERCRONIC_SHA256_ARM64}" ;; \
        *) printf 'Unsupported TARGETARCH: %s\n' "${TARGETARCH}" >&2; exit 1 ;; \
    esac \
    && supercronic="supercronic-linux-${TARGETARCH}" \
    && curl --fail --location --silent --show-error \
        "https://github.com/aptible/supercronic/releases/download/v${SUPERCRONIC_VERSION}/${supercronic}" \
        --output "/tmp/${supercronic}" \
    && printf '%s  %s\n' "${supercronic_sha256}" "/tmp/${supercronic}" | sha256sum --check \
    && install -m 0755 "/tmp/${supercronic}" /usr/local/bin/supercronic \
    && rm -f "/tmp/${supercronic}" \
    && mkdir -p /etc/supercronic \
    && echo "*/1 * * * * php ${ROOT}/artisan schedule:run --no-interaction" > /etc/supercronic/laravel

# PHP Config
RUN cp ${PHP_INI_DIR}/php.ini-production ${PHP_INI_DIR}/php.ini
COPY docker/php.ini ${PHP_INI_DIR}/conf.d/99-custom.ini

# Copy Supervisor configs
COPY --link docker/supervisord.conf /etc/supervisord.conf
COPY --link docker/supervisord.frankenphp.conf /etc/supervisor/conf.d/
COPY --link docker/supervisord.frankenphp.no-station.conf /etc/supervisor/conf.d/
COPY --link docker/supervisord.station.conf /etc/supervisor/conf.d/
COPY --link docker/supervisord.scheduler.conf /etc/supervisor/conf.d/
COPY --link docker/supervisord.pulse.conf /etc/supervisor/conf.d/
COPY --link docker/supervisord.worker.conf /etc/supervisor/conf.d/
COPY --link docker/supervisord.nightwatch.conf /etc/supervisor/conf.d/

# Copy scripts
COPY --link docker/start-container /usr/local/bin/start-container
COPY --link docker/healthcheck /usr/local/bin/healthcheck
COPY --link docker/pulse-process /usr/local/bin/pulse-process
COPY --link docker/run-scripts.sh /usr/local/bin/run-scripts.sh
COPY --link docker/docker-scripts /var/www/html/docker-scripts
RUN chmod +x /usr/local/bin/start-container /usr/local/bin/healthcheck /usr/local/bin/pulse-process /usr/local/bin/run-scripts.sh && \
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

HEALTHCHECK --start-period=60s --interval=30s --timeout=10s --retries=5 CMD healthcheck || exit 1
