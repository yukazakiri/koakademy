ARG APP_NAME=KoAkademy
ARG APP_ENV=production
ARG APP_DEBUG=false
ARG APP_URL=https://koakademy.yukazaki.com

# -----------------------------------------------------------------------------
# Stage 1: Install PHP Dependencies
# -----------------------------------------------------------------------------
FROM composer:2.10@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-autoloader \
    --no-ansi \
    --no-scripts \
    --no-progress \
    --ignore-platform-reqs

# All PHP dependencies, including koakademy/forms, are governed by
# composer.lock so image builds stay reproducible. The lock file is bumped
# via a regular PR whenever a new Forms release should be deployed; running
# `composer update` at build time re-resolves metadata against github.com
# without authentication and breaks the build once the anonymous API quota
# is exhausted ("Could not authenticate against github.com").
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
FROM php:8.5.12-fpm@sha256:f665ad19c093eee54db3a03c89102d6828b1bbf4f327689e5947a8686c6585a1 AS wayfinder
# The application boots during wayfinder:generate and config/backup.php
# instantiates ZipArchive, so the extension must be present in this stage.
RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= PORTAL_HOST= ADMIN_HOST= php -d memory_limit=512M artisan wayfinder:generate --no-interaction

# -----------------------------------------------------------------------------
# Stage 3: Build Frontend Assets
# -----------------------------------------------------------------------------
FROM node:26.12.1-bookworm-slim@sha256:28147155d47dc31abe0a46e0d12b59b2fb3d0a2dcc5dc65fce76a074180b16fe AS build-assets
WORKDIR /app
COPY package.json package-lock.json ./
# puppeteer.config.cjs disables the Chromium download that would otherwise run
# on every npm install. It has to be in place before the install step.
COPY puppeteer.config.cjs ./
RUN npm ci
COPY . .
# Copy PHP dependencies and generated Wayfinder files.
COPY --from=vendor /app/vendor ./vendor
COPY --from=wayfinder /app/resources/js/actions ./resources/js/actions
COPY --from=wayfinder /app/resources/js/routes ./resources/js/routes
COPY --from=wayfinder /app/resources/js/wayfinder ./resources/js/wayfinder
RUN WAYFINDER_GENERATE=false npm run build
# SSR runs in the production container, so retain only its runtime dependency
# tree after the asset build has completed.
RUN npm prune --omit=dev

# -----------------------------------------------------------------------------
# Stage 4: Final Runtime Image
# -----------------------------------------------------------------------------
FROM php:8.5.12-fpm@sha256:f665ad19c093eee54db3a03c89102d6828b1bbf4f327689e5947a8686c6585a1

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
ENV TZ=${TZ}
ENV ROOT=${ROOT}
ENV USER=laravel
ENV INERTIA_SSR_ENABLED=true
ENV INERTIA_SSR_RUNTIME=/usr/local/bin/node
ENV COMPOSER_FUND=0
ENV COMPOSER_MAX_PARALLEL_HTTP=48
ENV XDG_CONFIG_HOME=${ROOT}/.config
ENV XDG_DATA_HOME=${ROOT}/.data

WORKDIR ${ROOT}

# Install system dependencies: web server, process supervision, and PostgreSQL client tools.
# Only build dependencies for the extensions compiled below are installed.
# The base image already ships ctype, curl, dom, fileinfo, iconv, json, mbstring,
# openssl, opcache, pdo_sqlite, posix, session, simplexml, sqlite3, tokenizer,
# xml, xmlreader, xmlwriter, and zlib.
RUN apt-get update && apt-get install -y --no-install-recommends \
    $PHPIZE_DEPS \
    curl \
    ca-certificates \
    zip \
    unzip \
    supervisor \
    nginx \
    libatomic1 \
    libstdc++6 \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libpq-dev \
    librsvg2-bin \
    mdbtools \
    postgresql-client && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Install only the PHP extensions the application actually requires
RUN docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install \
        pcntl \
        sockets \
        pdo_pgsql \
        exif \
        zip \
        intl \
        gd \
    && pecl install redis \
    && docker-php-ext-enable redis

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

# Web server configuration
COPY --link docker/nginx.conf /etc/nginx/nginx.conf
COPY --link docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Copy Supervisor configs
COPY --link docker/supervisord.conf /etc/supervisord.conf
COPY --link docker/supervisord.web.conf /etc/supervisor/conf.d/
COPY --link docker/supervisord.web.no-horizon.conf /etc/supervisor/conf.d/
COPY --link docker/supervisord.inertia-ssr.conf /etc/supervisor/conf.d/
COPY --link docker/supervisord.horizon.conf /etc/supervisor/conf.d/
COPY --link docker/supervisord.scheduler.conf /etc/supervisor/conf.d/
COPY --link docker/supervisord.pulse.conf /etc/supervisor/conf.d/
COPY --link docker/supervisord.worker.conf /etc/supervisor/conf.d/
COPY --link docker/supervisord.nightwatch.conf /etc/supervisor/conf.d/

# Copy scripts
COPY --link docker/start-container /usr/local/bin/start-container
COPY --link docker/healthcheck /usr/local/bin/healthcheck
COPY --link docker/pulse-process /usr/local/bin/pulse-process
COPY --link docker/nightwatch-process /usr/local/bin/nightwatch-process
COPY --link docker/run-scripts.sh /usr/local/bin/run-scripts.sh
COPY --link docker/docker-scripts /var/www/html/docker-scripts
RUN chmod +x /usr/local/bin/start-container /usr/local/bin/healthcheck /usr/local/bin/pulse-process /usr/local/bin/nightwatch-process /usr/local/bin/run-scripts.sh && \
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
    /tmp/opcache-file-cache \
    /tmp/nginx/client_body \
    /tmp/nginx/proxy \
    /tmp/nginx/fastcgi \
    /tmp/nginx/uwsgi \
    /tmp/nginx/scgi

# Copy application code from build stages
COPY --chown=${USER}:${USER} --from=vendor /app/vendor ./vendor
COPY --chown=${USER}:${USER} --from=build-assets /app/node_modules ./node_modules
COPY --chown=${USER}:${USER} --from=build-assets /app/public/build ./public/build
COPY --chown=${USER}:${USER} . .
COPY --chown=${USER}:${USER} --from=build-assets /app/bootstrap/ssr ./bootstrap/ssr

# The Inertia SSR runtime must not fall back to the distribution's Node package:
# Debian ships an end-of-life Node release that receives no security fixes.
# Reuse the patched Node runtime from the asset build stage instead.
COPY --from=build-assets /usr/local/bin/node /usr/local/bin/node

# Validate the generated SSR bundle against the pruned production tree: resolve
# every bare package import Node must load when the SSR server boots, so a
# dev-only runtime import fails the build instead of the healthcheck.
RUN test -f bootstrap/ssr/ssr.js \
    && node --input-type=module -e "import('node:fs').then(async ({ readFileSync }) => { const src = readFileSync('bootstrap/ssr/ssr.js', 'utf8'); const specs = [...new Set([...src.matchAll(/from\s+['\"]([^'\"./][^'\"]*)['\"]/g)].map((m) => m[1]))]; if (!specs.length) throw new Error('no SSR externals found in bootstrap/ssr/ssr.js'); for (const spec of specs) await import(spec); console.log('SSR externals OK: ' + specs.join(', ')); })"

# R2 uses Laravel's S3 filesystem driver. Keep its adapter in the production
# image so storage-backed branding and uploads cannot turn page rendering into
# a runtime 500.
RUN php -r 'require "vendor/autoload.php"; class_exists(\League\Flysystem\AwsS3V3\PortableVisibilityConverter::class) || exit(1);'

# Set ownership
RUN chown -R ${USER}:${USER} ${ROOT}/storage ${ROOT}/bootstrap/cache ${ROOT}/.config ${ROOT}/.data /tmp/nginx \
    && chmod -R 777 /tmp/opcache-file-cache

USER root

EXPOSE 8000

ENTRYPOINT ["start-container"]

HEALTHCHECK --start-period=60s --interval=30s --timeout=10s --retries=5 CMD healthcheck || exit 1
