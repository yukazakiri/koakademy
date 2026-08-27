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
curl -fsSL https://github.com/yukazakiri/koakademy/releases/latest/download/install.sh | bash
```

For a non-interactive installation such as `koakademy.koamishin.com`, pass the domain through the environment:

```sh
curl -fsSL https://github.com/yukazakiri/koakademy/releases/latest/download/install.sh \
  | env KOAKADEMY_DOMAIN=koakademy.koamishin.com bash
```

The bootstrap can be started as a normal user. It self-elevates only for
Docker, Swarm, and `/opt/koakademy` operations, then adds the invoking user to
Docker's `docker` group. Activate the change in the current shell with
`newgrp docker`, or log out and back in, before using Docker without `sudo`.
Docker-group membership grants root-equivalent access to the host.

The process installs Docker if necessary, initializes Swarm if it is inactive, preserves an active manager, creates an attachable overlay network, creates Docker Secrets, starts Caddy and dependencies, runs one migration job, starts the app, and verifies `https://koakademy.koamishin.com/up`.

Complete `https://koakademy.koamishin.com/setup`, then check `https://koakademy.koamishin.com/admin`. The initial local application volume is suitable for a new single node only; configure external S3/R2 and backups before production reliance.

For an unreleased `master` build in staging without a domain, use the edge
bootstrap. It publishes the app directly on port `8000`:

```sh
curl -fsSL https://raw.githubusercontent.com/yukazakiri/koakademy/master/scripts/install.sh | bash -s -- edge
```

Open `http://127.0.0.1:8000/setup`, or pass `--port 8080` to choose another
port. Edge is not a stable or production-supported release and may change or
break without notice.

## Day-two operations

```sh
koakademy status
koakademy update
koakademy update --stable
koakademy update --edge
koakademy configure storage r2
koakademy configure mail smtp
koakademy configure search enable
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
koakademy rollback
```

Rollback changes application code only. It never runs `migrate:rollback` or restores a database automatically. If the deployed migration is incompatible with old code, restore the recorded database backup and previous image together during a maintenance window.

## Module releases and upgrades

The module registry and the application image have separate release lifecycles.
The registry publishes signed catalog and Composer metadata; it does not
change code in an existing Swarm service. To update a standalone module such
as Announcement:

1. Release the module repository with a matching semver tag, for example
   `v1.0.2`.
2. Update and sign the registry catalog, then wait for the registry Pages
   workflow to publish it.
3. In the KoAkademy repository, run
   `composer update koakademy/announcement --with-dependencies` and commit
   `composer.json` and `composer.lock`. Use `composer require` for a package
   that is not declared yet.
4. Build and deploy a new application image through the normal stable or edge
   release process.
5. Run `php artisan migrate --force` in the release container when the module
   has migrations, clear/rebuild application caches, and roll every Swarm
   replica.
6. Open **Administrators → Marketplace** and enable the installed module if it
   is disabled.

Refreshing Marketplace only refreshes catalog information. It does not install
Composer packages, rebuild frontend assets, run migrations, or restart Swarm.
Existing source-tree modules under `Modules/` also do not receive updates from
their standalone repositories until the application is deliberately migrated
to the Composer package. Keep the legacy module enabled during that migration
unless the migration plan explicitly changes its status.

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
