# Deployment

This is the canonical production and upgrade runbook for KoAkademy.

## Supported architecture

The default installer deploys KoAkademy, PostgreSQL, Redis, and Gotenberg as manager-pinned Docker Swarm services on an attachable overlay. It publishes KoAkademy on host port `8000`; local RustFS additionally publishes its S3 API on host port `9000`. PostgreSQL, Redis, Gotenberg, and the RustFS console are not published.

The manual `compose.production.yaml` topology remains supported. It runs the application, PostgreSQL, Redis, and Gotenberg and binds only the application to `127.0.0.1:8000`. Manual Compose uploads use external S3-compatible storage.

Both topologies require an operator-managed Caddy, Nginx, Traefik, or tunnel for HTTPS. Swarm host-mode ports listen on the manager's host interfaces, so restrict `8000` and `9000` with the host firewall until the HTTPS edge is ready.

## Image channels

Stable releases are published identically to GHCR (`ghcr.io/yukazakiri/koakademy`) and Docker Hub (`yukazaki/koakademy`) for AMD64 and ARM64. Production deployments should use an exact `vX.Y.Z` tag. `latest` follows only the newest verified stable release; it is not the production recommendation because its target changes.

The `edge` tag follows the newest successfully delivered `master` commit, including documentation, workflow, and stable release commits. It is unsupported rolling software; when the commit is also a stable release, `edge` and the stable aliases may resolve to the same verified image. Immutable `sha-<40-character-commit>` tags are available for audit, canary pinning, and exact rollback. Historical `dev-latest` and `v*-dev.*` images are frozen and receive no new publications.

The default is one hostname:

```text
https://school.example/        portal
https://school.example/admin   administration
```

Split portal and admin subdomains are an advanced configuration. Add both hostnames to `PORTAL_HOST`, `ADMIN_HOST`, and the proxy certificate/routing configuration.

## First deployment

Follow [Getting Started](GETTING_STARTED.md). The Swarm installer performs this order:

1. Verify Docker's Linux engine and initialize Swarm only when it is inactive.
2. Preserve an existing manager cluster and create an attachable KoAkademy overlay.
3. Resolve stable image tags and create generated credentials as Docker secrets.
4. Start manager-pinned PostgreSQL, Redis, Gotenberg, and optional RustFS services.
5. Create the local RustFS bucket or validate the external S3 bucket.
6. Run `php artisan migrate --force` as a restartable one-off Swarm job.
7. Start KoAkademy and verify the host-published `/up` endpoint.
8. Print `/setup` and `/admin`, then leave HTTPS configuration to the operator.

The installer is idempotent for an already-complete installation: it reports the existing service instead of recreating the Swarm or rotating secrets. It never calls `docker swarm leave`, removes the existing overlay, or deletes persistent services. Container startup keeps `RUN_MIGRATIONS=false`; schema changes remain explicit one-off jobs.

For the manual Compose path, the required order remains: secure `.env`, validate Compose, start dependencies, generate `APP_KEY`, run migrations, start the app, verify `/up`, configure HTTPS, and complete `/setup`.

## Reverse proxy requirements

Terminate TLS at the edge. Forward Swarm installations to manager port `8000`; forward manual Compose installations to `http://127.0.0.1:8000`. Preserve these headers:

```text
Host
X-Forwarded-For
X-Forwarded-Host
X-Forwarded-Proto
```

`TRUSTED_HOSTS` accepts additional comma-separated exact hostnames. `TRUSTED_PROXIES` accepts `*`, IP addresses, or CIDRs. The Swarm installer leaves `TRUSTED_PROXIES` empty because its host port is not loopback-only; set `KOAKADEMY_TRUSTED_PROXIES` to the explicit edge addresses/CIDRs before installation when forwarded headers are required. Trusting all proxies is appropriate only for the loopback-only Compose origin.

Example Caddy site:

```text
school.example {
    encode zstd gzip
    reverse_proxy 127.0.0.1:8000
}
```

When local RustFS backs an HTTPS KoAkademy installation, expose port `9000` through a separate HTTPS storage hostname and set `KOAKADEMY_S3_PUBLIC_URL` to the bucket base URL. Serving an HTTP object URL inside an HTTPS page is blocked as mixed content.

## Backups

Back up both data planes:

- PostgreSQL database, including a periodic restore test
- S3-compatible bucket or local RustFS volume, according to the storage system's versioning and retention controls
- Swarm manager state when installer-generated Docker secrets are used

The Redis volume is operational state, not the source of record. Compose operators preserve `.env` and `APP_KEY` in an encrypted secret store. Swarm operators back up the manager's Swarm state using Docker's documented procedure because generated secret values are not written to a plaintext recovery file. Losing `APP_KEY` can make encrypted application data and sessions unreadable.

Example Swarm database backup:

```sh
docker ps --filter label=com.docker.swarm.service.name=koakademy-postgres \
  --format '{{.ID}} {{.Names}}'
docker exec -i <postgres-container-id> sh -c \
  'PGPASSWORD="$(cat /run/secrets/koakademy-db-password)" exec pg_dump --clean --if-exists --no-owner --username=koakademy koakademy' \
  > koakademy.sql
```

Example manual Compose database backup:

```sh
docker compose --env-file .env -f compose.production.yaml exec -T postgres \
  pg_dump --clean --if-exists --no-owner --username="$DB_USERNAME" "$DB_DATABASE" > koakademy.sql
```

Run this from a shell where the variables were loaded safely, or substitute the configured non-secret database name and user. Protect the resulting dump as sensitive institutional data.

## Upgrade

KoAkademy supports upgrades to the latest stable release. Read [CHANGELOG.md](CHANGELOG.md) and the GitHub release notes first.

The one-line installer is deliberately conservative when `koakademy-app` already exists: it reports the installation and does not mutate it. A supported Swarm upgrade needs a backup, an explicit migration job using the existing service's secrets and environment, an image update to a reviewed stable tag, and post-update smoke tests. Until a dedicated upgrade command is published, operators who need a fully scripted upgrade path should use the manual Compose topology or maintain an equivalent reviewed Swarm stack definition.

Manual Compose upgrade:

```sh
# 1. Back up PostgreSQL and verify object-storage protection.
# 2. Update the checked-out stable tag and KOAKADEMY_VERSION in .env.
docker compose --env-file .env -f compose.production.yaml pull app

# 3. Run migrations as a deliberate one-off operation.
docker compose --env-file .env -f compose.production.yaml run --rm app php artisan migrate --force

# 4. Replace the running application and verify it.
docker compose --env-file .env -f compose.production.yaml up -d app
curl --fail --silent --show-error http://127.0.0.1:8000/up
```

Test the portal, `/admin`, authentication, uploads, a queued job, and a PDF export after every upgrade.

When an upgrade includes the Digital Library migrations, run the migration command once as the release job before replacing the application replicas. Never let both Swarm replicas run migrations during startup. After the private bucket and its exact-origin CORS policy are configured, smoke-test through the public KoAkademy origin:

1. Confirm a catalog-only title is visible but cannot open the reader.
2. Upload a small licensed test PDF as a draft and confirm it is unavailable to normal users.
3. Publish it with a rights basis and confirm inline reading, page navigation, bookmarks, and saved progress.
4. Verify downloads return `403` while disabled, then enable and verify the attachment response.
5. Repeat reader and download requests while routing through each application replica.
6. Unpublish or remove the edition and confirm the previous signed URL expires and no new URL is issued.

## Rollback

Application-image rollback is safe only when the previous code supports the migrated database schema. If release notes do not explicitly allow a code-only rollback, restore the pre-upgrade database backup and matching image together in a maintenance window. Never run `migrate:rollback` blindly on production data.

Record the exact current and previous `vX.Y.Z` image references before every upgrade. Roll back by restoring the previous exact tag, not by selecting `latest` or `edge`; those aliases do not preserve historical state. For incident forensics, compare the release tag with its `sha-<commit>` image and the checksummed `version.json` attached to the GitHub Release.

## Production checklist

- `APP_ENV=production` and `APP_DEBUG=false`
- Unique `APP_KEY`, database password, Redis password, and S3 credentials
- `SESSION_SECURE_COOKIE=true`, HTTPS active, and correct trusted hosts/proxies
- Swarm ports `8000` and optional `9000` restricted to the intended edge, or Compose bound only to `127.0.0.1:8000`
- PostgreSQL, Redis, and Gotenberg have no host port mappings
- `RUN_MIGRATIONS=false`
- `/up`, `/setup` or `/admin`, upload, queue, mail, and PDF smoke tests pass
- Private Digital Library bucket has no public domain, exact-origin CORS supports PDF range requests, and the takedown address is monitored
- Librarian and legal approval exists before publishing production digital editions
- Database and object-storage recovery tested
- Logs, disk use, queue depth, certificate expiry, and backup failures monitored
