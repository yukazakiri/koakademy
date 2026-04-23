# Deployment Guide

This guide covers a production deployment shape for KoAkademy using a container image, Redis, and an external database. Replace image names, secrets, and hostnames with values for your environment.

## Deployment Checklist

- Build and publish an application image for this repository.
- Provision PostgreSQL or MySQL.
- Provision Redis.
- Set `APP_URL`, `ADMIN_HOST`, and `PORTAL_HOST` to the real production domains.
- Run database migrations during deploy.
- Keep `storage/` persistent across releases.

## Required Environment

```env
APP_NAME="KoAkademy"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://koakademy.edu
ADMIN_HOST=admin.koakademy.edu
PORTAL_HOST=portal.koakademy.edu

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=koakademy
DB_USERNAME=koakademy
DB_PASSWORD=change-me

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=database
REDIS_HOST=redis
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_FROM_ADDRESS=noreply@koakademy.edu
MAIL_FROM_NAME="${APP_NAME}"

LARAVEL_PDF_DRIVER=cloudflare
LARAVEL_PDF_PRODUCTION_DRIVER=cloudflare
LARAVEL_PDF_PRODUCTION_FALLBACK=dompdf
LARAVEL_PDF_ROLLBACK_DRIVER=dompdf
CLOUDFLARE_API_TOKEN=
CLOUDFLARE_ACCOUNT_ID=

FILESYSTEM_DISK=public
OCTANE_SERVER=frankenphp
OCTANE_HOST=0.0.0.0
OCTANE_PORT=8000
```

## Example Docker Compose

```yaml
services:
  app:
    image: ghcr.io/your-org/koakademy:latest
    container_name: koakademy-app
    restart: unless-stopped
    env_file:
      - .env.production
    ports:
      - "8000:8000"
    depends_on:
      - redis
    volumes:
      - koakademy-storage:/var/www/html/storage

  redis:
    image: redis:7-alpine
    container_name: koakademy-redis
    restart: unless-stopped
    volumes:
      - koakademy-redis:/data

volumes:
  koakademy-storage:
  koakademy-redis:
```

## Deploy Steps

```bash
docker compose pull
docker compose up -d
docker exec koakademy-app php artisan migrate --force
docker exec koakademy-app php artisan optimize
```

If you need an initial administrator:

```bash
docker exec -it koakademy-app php artisan make:filament-user
```

## Branding and Domains

- Runtime branding should come from configuration and persisted settings, not from compiled text in the image.
- Set canonical production hosts with `APP_URL`, `ADMIN_HOST`, and `PORTAL_HOST`.
- If you are migrating an older deployment, back up the settings table before applying any branding backfill migration.

## Health Checks

Useful commands after deployment:

```bash
docker ps
docker logs koakademy-app --tail=200
docker exec koakademy-app php artisan octane:status
docker exec koakademy-app php artisan about
```

## Backups

Back up at least:

- the application database
- the persistent `storage/` volume
- production environment files and secret values from your secret manager

## Rollback

Keep the previous container image tag available. To roll back:

1. Point the compose file to the previous image tag.
2. Redeploy the containers.
3. Restore the database only if the failed release included destructive schema changes.

PDF driver rollback path:

1. Set `LARAVEL_PDF_DRIVER=${LARAVEL_PDF_ROLLBACK_DRIVER}`.
2. Redeploy and clear config cache (`php artisan config:clear`).

## PDF Driver ADR (2026-04-23)

Decision record for PDF rendering in production:

- Primary production driver: `cloudflare` (Cloudflare Browser Rendering).
- Production fallback: `dompdf` (pure PHP, no external binaries).
- Staging fallback: `dompdf`.
- Local development default: `dompdf`.
- Rollback knob: `LARAVEL_PDF_ROLLBACK_DRIVER`.

Rationale:

- Eliminates local Chromium/Node runtime from the container image.
- Uses Cloudflare's managed headless browser for complex layouts.
- Keeps DOMPDF as a lightweight, zero-dependency fallback.

## Post-Deploy Verification

Check these URLs and flows:

- `https://koakademy.edu`
- `https://portal.koakademy.edu`
- `https://admin.koakademy.edu`
- authentication
- dashboard rendering
- enrollment and payment views
- queue-backed notifications or jobs
