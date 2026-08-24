# Deployment

This is the production runbook for the supported Linux one-line installer and manual Docker Compose alternative.

## Supported Swarm architecture

The installer creates one manager-pinned Docker Swarm stack:

- Caddy publishes ports 80 and 443 and obtains TLS certificates automatically.
- FrankenPHP, PostgreSQL, Redis, and Gotenberg stay private on an attachable overlay.
- PostgreSQL, Redis, Caddy state, and app storage use local persistent volumes.
- Docker Secrets supply application, database, Redis, storage, mail, and Meilisearch credentials.

The stable installer uses the checksummed `version.json` from the latest stable
GitHub Release. Its image value is an immutable GHCR digest. Use the separate
`edge` channel only for staging or development; it follows the current
unreleased `master` commit and uses the mutable `edge-frankenphp` image.

## First deployment

Before installation, point the production domain to the VPS and make ports 80/443 available:

```sh
curl -fsSL https://github.com/yukazakiri/koakademy/releases/latest/download/install.sh | sudo bash -s -- --stable --domain school.example
```

The process installs Docker if necessary, initializes Swarm if it is inactive, creates Docker Secrets, starts Caddy and dependencies, runs one migration job, starts the app, and verifies `https://school.example/up`.

Complete `https://school.example/setup`, then check `https://school.example/admin`. The initial local application volume is suitable for a new single node only; configure external S3/R2 and backups before production reliance.

For an unreleased `master` build in staging, use the edge bootstrap:

```sh
curl -fsSL https://raw.githubusercontent.com/yukazakiri/koakademy/master/scripts/install.sh | sudo bash -s -- edge --domain school.example
```

Edge is not a stable or production-supported release and may change or break
without notice.

## Day-two operations

```sh
sudo koakademy status
sudo koakademy update
sudo koakademy update --stable
sudo koakademy update --edge
sudo koakademy configure storage r2
sudo koakademy configure mail smtp
sudo koakademy configure search enable
```

`update` follows the installed channel by default. Pass `--stable` to select a
published release or `--edge` to select the current unreleased `master` build.
Stable updates download and verify the release bundle; edge updates download
the matching files from the master commit and use `edge-frankenphp`. Both
create a custom-format PostgreSQL dump under `/opt/koakademy/backups`, run
release migrations, perform a start-first app rollout, and check the HTTPS
health endpoint.

The command retains the preceding image:

```sh
sudo koakademy rollback
```

Rollback changes application code only. It never runs `migrate:rollback` or restores a database automatically. If the deployed migration is incompatible with old code, restore the recorded database backup and previous image together during a maintenance window.

## Backups and recovery

Back up and restore-test all of these:

- PostgreSQL custom dumps in `/opt/koakademy/backups`
- External upload and private-library buckets, including their retention/versioning policy
- Docker Swarm manager state, which protects installer-created secrets

The Redis volume is operational state, not the system of record. Losing `APP_KEY` can make encrypted application data unreadable.

## Legacy Swarm migration

The new command refuses any host containing legacy `koakademy-app`, `koakademy-postgres`, `koakademy-redis`, `koakademy-gotenberg`, or `koakademy-rustfs` services. It exits before creating, changing, or deleting anything.

Migrate deliberately:

1. Schedule a maintenance window and stop writes to the old deployment.
2. Back up and restore-test PostgreSQL, uploads/object storage, and the old `APP_KEY`.
3. Provision a fresh VPS or remove the legacy stack only after its backup is verified.
4. Run the new installer, restore data according to the target release's migration instructions, and configure external providers through `koakademy configure`.
5. Verify login, uploads, queues, PDF generation, mail, and the public health endpoint before DNS cutover.

Never point both stacks at the same PostgreSQL database, Redis data, or storage volume.

## Manual Compose

`compose.production.yaml` remains for operators who own their own edge proxy. It binds the application to `127.0.0.1:8000`, so the proxy must preserve `Host`, `X-Forwarded-For`, `X-Forwarded-Host`, and `X-Forwarded-Proto`. Use an exact stable image tag, protected `.env`, external S3-compatible storage, explicit migrations, and an application restart after configuration changes.

```sh
docker compose --env-file .env -f compose.production.yaml pull app
docker compose --env-file .env -f compose.production.yaml run --rm app php artisan migrate --force
docker compose --env-file .env -f compose.production.yaml up -d app
curl --fail --silent --show-error http://127.0.0.1:8000/up
```
