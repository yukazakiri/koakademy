# Getting Started

This guide installs KoAkademy with Docker Swarm, PostgreSQL, Redis, Gotenberg, and S3-compatible storage. KoAkademy is a production-capable beta; start in a staging environment and keep a tested recovery path.

## Prerequisites

- Linux host with Bash and Docker Engine, or a Windows x64 machine with PowerShell and Docker Desktop using Linux containers
- Docker daemon access from the account running the installer
- 4 GB RAM minimum for a small evaluation; size from observed workload
- Free host port `8000`; local RustFS also uses host port `9000`
- Outbound access to GitHub, GitHub Container Registry, Docker Hub, and any external object-storage endpoint

Only PostgreSQL is supported by the prebuilt production image. SQLite is available for native development and tests.

## One-line Docker Swarm installation

The installer initializes a single-node Swarm when Docker is inactive. If Swarm is already active, it preserves the cluster, requires the current node to be a manager, and pins KoAkademy's stateful services to that manager.

On Linux, copy and run:

```sh
bash -c "$(curl -fsSL https://raw.githubusercontent.com/yukazakiri/koakademy/master/scripts/install.sh)"
```

On Windows, open PowerShell and copy and run:

```powershell
& ([scriptblock]::Create((irm https://raw.githubusercontent.com/yukazakiri/koakademy/master/scripts/install.ps1)))
```

The commands become anonymously accessible when this repository is public. Before then, authenticated maintainers can download and run the matching script from `scripts/`.

The installer:

1. Resolves the newest stable KoAkademy tag from GitHub, rejecting development and preview tags.
2. Generates the application key and database, Redis, and storage credentials as Docker secrets.
3. Prompts for either local RustFS or an external S3-compatible service such as Cloudflare R2.
4. Creates an attachable overlay network and manager-pinned PostgreSQL, Redis, Gotenberg, optional RustFS, and KoAkademy services.
5. Creates or validates the storage bucket, runs database migrations as a one-off Swarm job, starts KoAkademy, and verifies `/up`.
6. Prints the public application, `/setup`, and `/admin` URLs.

Local RustFS uses a dedicated public-read upload bucket and persistent Docker volume. It is a single-node convenience topology without storage redundancy; back it up and use a redundant external S3 service for higher availability. The RustFS console is not published.

The default application URL is `http://<detected-ip>:8000`. HTTP is suitable only for local or LAN evaluation. Before production use, put an HTTPS reverse proxy or tunnel in front of port `8000`. If local RustFS is used with HTTPS, also provide an HTTPS edge for port `9000` and set `KOAKADEMY_S3_PUBLIC_URL`.

The scripts accept environment overrides for automation, including `KOAKADEMY_VERSION`, `RUSTFS_VERSION`, `KOAKADEMY_APP_URL`, `KOAKADEMY_APP_PORT`, `KOAKADEMY_STORAGE=rustfs|external`, and the `KOAKADEMY_S3_*` values. Without `KOAKADEMY_VERSION`, version discovery fails closed if GitHub cannot return a stable tag; it never silently falls back to a mutable image.

An explicit `KOAKADEMY_VERSION=edge` selects the unsupported rolling channel and prints a warning. Use it only for disposable evaluation or pre-release compatibility testing. Automatic discovery never selects `edge`, and production installations should pin an exact `vX.Y.Z` stable tag.

Running remote code is a privileged operation. To inspect the Linux installer first:

```sh
curl -fsSLO https://raw.githubusercontent.com/yukazakiri/koakademy/master/scripts/install.sh
less install.sh
bash install.sh
```

On Windows:

```powershell
Invoke-WebRequest https://raw.githubusercontent.com/yukazakiri/koakademy/master/scripts/install.ps1 -OutFile install.ps1
Get-Content .\install.ps1
& .\install.ps1
```

## Manual Docker Compose installation

Use this path when an operator-managed Compose topology is preferred over the default Swarm installer.

### 1. Obtain a supported release

Clone the repository at the latest stable release tag so the deployment files and image version stay together:

```sh
git clone https://github.com/yukazakiri/koakademy.git
cd koakademy
git fetch --tags
git checkout <latest-stable-tag>
```

Replace `<latest-stable-tag>` with the latest non-prerelease tag shown on the GitHub Releases page. KoAkademy supports only the latest stable release; do not deploy `edge`, `-dev`, prerelease, or arbitrary branch images as production releases.

### 2. Configure the environment

```sh
cp .env.production.example .env
chmod 600 .env
```

At minimum, replace:

- `APP_URL`, `PORTAL_HOST`, and `ADMIN_HOST` with the same production hostname
- `DB_PASSWORD` and `REDIS_PASSWORD` with unique random values
- every `AWS_*` value with credentials for a dedicated S3-compatible bucket
- `MAIL_*` when real email delivery is required
- `KOAKADEMY_VERSION` with the matching stable image tag

Generate the application key:

```sh
docker compose --env-file .env -f compose.production.yaml run --rm app php artisan key:generate --show
```

Copy the complete output into `APP_KEY`. Never reuse a key from another installation or commit `.env`.

Validate configuration before starting anything:

```sh
docker compose --env-file .env -f compose.production.yaml config --quiet
```

### 3. Start dependencies and migrate

```sh
docker compose --env-file .env -f compose.production.yaml pull
docker compose --env-file .env -f compose.production.yaml up -d postgres redis gotenberg
docker compose --env-file .env -f compose.production.yaml run --rm app php artisan migrate --force
```

Migrations are intentionally separate from container startup. Review release notes and take a database backup before the same command during upgrades.

### 4. Start and verify KoAkademy

```sh
docker compose --env-file .env -f compose.production.yaml up -d app
docker compose --env-file .env -f compose.production.yaml ps
curl --fail --silent --show-error http://127.0.0.1:8000/up
```

If `/up` fails, inspect `docker compose --env-file .env -f compose.production.yaml logs app` and use [Troubleshooting](TROUBLESHOOTING.md).

### 5. Add HTTPS and complete setup

Forward your HTTPS edge to `http://127.0.0.1:8000` and preserve `Host`, `X-Forwarded-For`, `X-Forwarded-Host`, and `X-Forwarded-Proto` headers. Then visit:

```text
https://school.example/setup
```

The one-time wizard creates the institution, initial academic period, and first super administrator. After completion, verify `https://school.example/admin`. The setup route returns `403` after the application is initialized.

## Next steps

- Finish the production checklist in [Deployment](DEPLOYMENT.md).
- Review every variable in [Configuration](CONFIGURATION.md).
- Configure scheduled backups for PostgreSQL and external object storage.
- Subscribe to security and release notifications for the repository.
