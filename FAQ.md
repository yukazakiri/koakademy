# FAQ

## Is KoAkademy production ready?

KoAkademy is a production-capable beta. It provides a hardened reference topology, health endpoint, migrations, tests, and operator docs. Each institution remains responsible for staging, capacity planning, backups, monitoring, privacy, security review, and recovery exercises.

## Which release is supported?

Only the latest stable, non-prerelease release is supported. Development and prerelease images are for evaluation. Read the changelog and release notes before upgrading.

## How do I install a module?

The Marketplace is enabled by default in the current installer and shows the
signed public catalog alongside modules already installed in the application
image. It does not install packages itself. Add the module's Composer package
to the KoAkademy application, commit the generated `composer.lock`, build and
deploy a new image, run migrations when required, and then enable the module in
**Administrators → Marketplace**.

For example, a Forms installation from the application repository is:

```sh
composer require koakademy/forms:^1.0
php artisan migrate --force
php artisan optimize:clear
```

The production image must also run the normal frontend build. Do not run
Composer as a web request or depend on a live container changing its own
`vendor/` directory.

## How does a module update reach an existing deployment?

Publishing a new module tag updates the registry catalog only after a
maintainer signs and merges the registry change. The KoAkademy application must
then update its Composer lockfile and publish a new image. Dokploy or the
Swarm operator deploys that image, runs the release migration/cache steps, and
rolls the replicas. An existing container remains on its previous module
version until that image rollout occurs.

If the module is still a local source-tree module under `Modules/`, its
standalone repository updates do not affect it. Migrate the application to the
Composer package as a separate, tested change first.

## Why does Marketplace show a module as disabled?

The package may be installed but absent or disabled in the persistent module
status file. A super administrator can enable it from **Administrators →
Marketplace** after confirming the compatibility checks pass. If it is not
installed in the image, enabling is not possible; update Composer and redeploy
the image first. Existing status choices are preserved during application
upgrades.

## What does the one-line installer change?

It installs Docker when needed, initializes Swarm only when inactive, creates an attachable overlay network, and deploys Caddy, KoAkademy, PostgreSQL, Redis, and Gotenberg on a single VPS. Caddy owns ports 80/443; the remaining services are private on the Swarm overlay. The installer preserves an active manager cluster, never leaves Swarm, and stops before changing a legacy `koakademy-*` deployment.

The Linux command uses the stable channel by default to resolve a published
GitHub Release, verify its checksummed bundle, and deploy the immutable GHCR
digest in `version.json`. Set `KOAKADEMY_DOMAIN` for a non-interactive install;
an attached terminal can provide the domain interactively. Use `edge` only for
staging or development; it resolves the current `master` commit, publishes
port `8000` when no domain is provided, and deploys the mutable
`ghcr.io/yukazakiri/koakademy:edge-frankenphp` image.

The bootstrap can be started by a normal user when `sudo` is available. It
self-elevates for Docker, Swarm, and runtime-file operations, and adds the
invoking user to Docker's `docker` group. Activate the change in the current
shell with `newgrp docker`, or log out and back in, before using Docker without
`sudo`; Docker-group membership grants root-equivalent access to the host.

## Can I use MySQL or SQLite in production?

The prebuilt production image supports PostgreSQL only. SQLite remains the default lightweight option for native development and automated tests. Other databases are not part of the supported production contract.

## Which ports are exposed?

The default Swarm installer publishes Caddy only on ports `80` and `443`. KoAkademy, PostgreSQL, Redis, and Gotenberg have no host-published port.

The manual Compose topology publishes only `127.0.0.1:8000`. PostgreSQL, Redis, and Gotenberg are private in both topologies.

## Can portal and admin use separate subdomains?

Yes, as an advanced option. The supported default uses one hostname with `/admin`. For split hosts, configure `APP_URL`, `PORTAL_HOST`, `ADMIN_HOST`, certificates, proxy routes, cookie scope, and cross-host behavior together.

## How do I create the first administrator?

Run migrations, start the app, then open `/setup`. The one-time wizard creates the institution and first super administrator, and can pre-create CHED-aligned degree programs, DepEd SHS tracks and strands, TESDA NC qualifications, or record the DepEd MATATAG framework from a built-in, research-backed catalog. It becomes forbidden after setup. Do not use `make:filament-user` for initial installation.

## Does KoAkademy run migrations automatically?

Yes. `AUTO_MIGRATE=true` is the default, so the application also runs pending
migrations when it starts. The installer and `koakademy update` still run
`php artisan migrate --force` explicitly before rollout, after taking a backup
and reviewing release notes.

## Which PDF library and renderer are used?

KoAkademy uses `spatie/laravel-pdf` with Gotenberg. Gotenberg runs as a private production service. DOMPDF is not installed and is not a fallback.

## Is local storage supported for production uploads?

Fresh installs use a persistent application volume so setup works without a provider account. It is a single-node starter default, not resilient object storage. Use `koakademy configure storage s3` or `koakademy configure storage r2` before upload data must survive a host failure.

## Are all routes under `/api` public API contracts?

No. The documented beta contract intentionally includes only public settings, authenticated settings, and authenticated student verification. Other internal routes may change without public compatibility guarantees.

## How should vulnerabilities be reported?

Use a private GitHub Security Advisory as described in [SECURITY.md](SECURITY.md). Do not include exploit details or sensitive institutional data in a public issue.

## Is hosted support or an SLA included?

No support SLA, compliance certification, uptime guarantee, or compatibility guarantee is offered. Community issues and pull requests are handled as maintainer capacity allows.

## Why is there no Code of Conduct?

The maintainers have not adopted one yet. Choosing and enforcing a Code of Conduct is explicitly deferred as a maintainer governance decision.
