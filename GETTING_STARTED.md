# Installation

KoAkademy runs as a self-managed Linux service. The default path is a single-node Docker Swarm with Caddy HTTPS, PostgreSQL, Redis, Gotenberg, and a private FrankenPHP application service.

KoAkademy is beta software. Use staging first, review release notes, and keep a tested recovery path for institutional data.

## Prerequisites

- A Linux VPS with root access
- A public domain with its DNS pointing to the VPS
- Free ports 80 and 443 for Caddy
- At least 4 GB RAM for a small evaluation
- Outbound access to GitHub, GHCR, Docker Hub, and any external providers

Docker does not need to be preinstalled. The installer uses Docker's official installer when the engine is absent. It supports Docker's Linux distributions and AMD64 or ARM64 hosts.

## One-line production installation

Run this with the real public domain:

```sh
curl -fsSL https://github.com/yukazakiri/koakademy/releases/latest/download/install.sh | sudo bash -s -- install --domain school.example
```

The command resolves a stable GitHub Release, downloads the checksummed release bundle, deploys its immutable GHCR image digest, and then verifies `https://school.example/up`. It does not use `edge` or `latest`.

It:

1. Installs Docker when needed and initializes Swarm only when inactive.
2. Generates application, database, and Redis credentials as Docker Secrets.
3. Starts Caddy on ports 80/443; the application, PostgreSQL, Redis, and Gotenberg remain private on the overlay network.
4. Runs migrations once before enabling the app service.
5. Creates a persistent application volume for first-boot local uploads, then prints the `/setup` URL.

Visit `https://school.example/setup` to create the school and first super administrator. The setup route closes after initialization.

The initial filesystem volume is a simplified single-node default, not durable object storage. Configure external S3/R2 and scheduled backups before treating the installation as resilient production infrastructure.

## Operate the installation

The installer adds a single server command:

```sh
sudo koakademy status
sudo koakademy update
sudo koakademy configure storage r2
sudo koakademy configure mail smtp
sudo koakademy configure search enable
```

Provider credentials are requested without echoing, stored as versioned Docker Secrets, and never written to the application database or container filesystem. Every configuration change redeploys the FrankenPHP service so persistent workers load the new configuration.

`update` creates a PostgreSQL backup under `/opt/koakademy/backups`, runs the target release migrations, deploys the new immutable image, and checks the public health endpoint. It records the previous image for `sudo koakademy rollback`, but rollback never reverses database migrations. Restore the recorded backup when release notes do not guarantee schema compatibility.

## Inspect before running

Running remote privileged code is a trust decision. Download the matching stable release asset to inspect it:

```sh
curl -fSLO https://github.com/yukazakiri/koakademy/releases/latest/download/install.sh
less install.sh
bash install.sh install --domain school.example
```

## Existing Swarm installation

The new installer deliberately refuses a host that still has legacy `koakademy-*` services. It makes no changes and points to the [legacy Swarm migration guide](./deployment/#legacy-swarm-migration). Back up PostgreSQL and storage, migrate during a maintenance window, and do not run the two topologies against the same data volumes.

## Manual Docker Compose installation

Compose remains supported for operators who manage their own reverse proxy and environment file:

```sh
git clone https://github.com/yukazakiri/koakademy.git
cd koakademy
git checkout <latest-stable-tag>
cp .env.production.example .env
chmod 600 .env
docker compose --env-file .env -f compose.production.yaml config --quiet
docker compose --env-file .env -f compose.production.yaml up -d postgres redis gotenberg
docker compose --env-file .env -f compose.production.yaml run --rm app php artisan migrate --force
docker compose --env-file .env -f compose.production.yaml up -d app
```

Configure HTTPS in front of the Compose loopback port and complete `/setup`. Review [Configuration](CONFIGURATION.md) and [Deployment](DEPLOYMENT.md) before accepting traffic.
