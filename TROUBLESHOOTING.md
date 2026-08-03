# Troubleshooting

Start with the smallest observable failure and preserve logs before restarting services.

## Useful diagnostics

Default Swarm installation:

```sh
docker service ls --filter label=com.koakademy.managed-by=swarm-installer
docker service ps --no-trunc koakademy-app
docker service logs --tail=200 koakademy-app
docker service logs --tail=100 koakademy-postgres
docker service logs --tail=100 koakademy-redis
docker service logs --tail=100 koakademy-gotenberg
curl --verbose http://127.0.0.1:8000/up
```

Manual Compose installation:

```sh
docker compose --env-file .env -f compose.production.yaml ps
docker compose --env-file .env -f compose.production.yaml logs --tail=200 app
docker compose --env-file .env -f compose.production.yaml logs --tail=100 postgres redis gotenberg
curl --verbose http://127.0.0.1:8000/up
```

Never paste `.env`, access tokens, student data, database dumps, or unredacted production logs into a public issue.

## The installer cannot resolve the latest tag

The installer intentionally reads immutable tags from published GitHub Releases and never falls back to draft tags or `latest`. Anonymous release discovery works after the repository is public. Until then, authenticated maintainers can run a local script with an explicit stable tag:

```sh
KOAKADEMY_VERSION=<stable-tag> bash scripts/install.sh
```

Also confirm outbound HTTPS access to `api.github.com`, `github.com`, `ghcr.io`, and Docker Hub. For local RustFS, `RUSTFS_VERSION` can pin a reviewed stable or non-preview beta tag.

## The installer says Swarm is unavailable

Docker must already be installed and running with the Linux container engine. The installer initializes Swarm only when `docker info --format '{{.Swarm.LocalNodeState}}'` reports `inactive`. An active worker is preserved but rejected because service creation requires a manager; run the installer on an existing manager instead of forcing the worker to leave.

On Windows, start Docker Desktop, select Linux containers, then verify:

```powershell
docker version
docker info --format '{{.OSType}} {{.Architecture}} {{.Swarm.LocalNodeState}}'
```

## A Swarm service does not converge

Inspect placement, health, and the full task error:

```sh
docker service ps --no-trunc koakademy-app
docker service inspect --pretty koakademy-app
docker node ls
```

The installer pins services to the manager where it ran because PostgreSQL, Redis, KoAkademy runtime storage, and optional RustFS use local named volumes. A drained, unavailable, or renamed node prevents those services from starting. Do not remove and recreate volumes while diagnosing.

## Compose rejects the configuration

Run:

```sh
docker compose --env-file .env -f compose.production.yaml config --quiet
```

Errors usually mean `.env` is missing, `DB_PASSWORD` or `REDIS_PASSWORD` is empty, or a value contains Compose interpolation characters that need escaping. Compare variable names with `.env.production.example`; do not copy values from that example unchanged.

## The application container does not become healthy

Check dependency health and application logs. Common causes are:

- Incorrect PostgreSQL or Redis password
- Missing or malformed `APP_KEY`
- Migrations were not run
- Incompatible image and deployment-file versions
- Unwritable application storage volume

For Swarm, inspect the one-off migration job before the installer times out and the long-running services afterward:

```sh
docker service ps --no-trunc koakademy-migrate
docker service logs koakademy-migrate
docker service ps --no-trunc koakademy-postgres
docker service ps --no-trunc koakademy-redis
docker service ps --no-trunc koakademy-gotenberg
```

For manual Compose, run the explicit migration after dependencies are healthy:

```sh
docker compose --env-file .env -f compose.production.yaml run --rm app php artisan migrate:status
docker compose --env-file .env -f compose.production.yaml run --rm app php artisan migrate --force
```

## `/up` works locally but the public site fails

Check the external proxy or tunnel, host firewall, DNS, certificate, and forwarding headers. A Swarm install listens on manager host port `8000`; manual Compose listens on `127.0.0.1:8000`. The edge must target the matching address, not a database or container-private address.

Confirm `APP_URL`, `PORTAL_HOST`, and `ADMIN_HOST` match the public hostname. Add intentional aliases to `TRUSTED_HOSTS`. A rejected or unexpected host can produce an HTTP 400 response before application routing.

## Redirect loop or generated HTTP links behind HTTPS

Verify the edge sends `X-Forwarded-Proto: https` and its source is allowed by `TRUSTED_PROXIES`. Use explicit proxy addresses/CIDRs for Swarm's host-published port; reserve `TRUSTED_PROXIES=*` for the loopback-only Compose origin. Clear cached configuration after changing Compose environment values:

```sh
docker compose --env-file .env -f compose.production.yaml exec app php artisan optimize:clear
docker compose --env-file .env -f compose.production.yaml restart app
```

The Swarm installer generates its service environment once. Review it with `docker service inspect koakademy-app` and use an explicit `docker service update` or reviewed redeployment to change proxy settings; rerunning the installer does not overwrite an existing service.

## `/setup` is forbidden

The setup wizard is one-time. It returns `403` after core data or completed setup state exists. Use the normal `/admin` sign-in. Do not delete setup records to recreate an administrator; use supported password-reset or database recovery procedures.

If this happens on a genuinely empty database, verify the app is connected to the intended PostgreSQL database and inspect migration status.

## Uploads fail

Confirm `FILESYSTEM_DISK=s3`, the bucket exists, and the `AWS_*` endpoint, region, path-style, and credentials match the provider. Credentials need the operations used by uploads and deletes but should be limited to the installation's bucket. Check clocks on the host and provider when signatures expire unexpectedly.

For local RustFS:

```sh
docker service ps --no-trunc koakademy-rustfs
docker service logs --tail=100 koakademy-rustfs
curl --fail --silent --show-error http://127.0.0.1:9000/health
```

If KoAkademy uses HTTPS, its browser-visible RustFS URL must also use HTTPS or browsers will block objects as mixed content.

## PDF generation fails

KoAkademy uses `spatie/laravel-pdf` with Gotenberg. There is no DOMPDF fallback. Check:

```sh
docker service ps --no-trunc koakademy-gotenberg
docker service logs --tail=100 koakademy-gotenberg
docker service logs --tail=100 koakademy-app

# Manual Compose
docker compose --env-file .env -f compose.production.yaml ps gotenberg
docker compose --env-file .env -f compose.production.yaml logs --tail=100 gotenberg app
```

`GOTENBERG_URL` must target the private service name (`http://koakademy-gotenberg:3000` in Swarm or `http://gotenberg:3000` in Compose). Restore Gotenberg, then retry the failed queued job according to your operational policy.

## Queued work does not run

Verify Redis health, `QUEUE_CONNECTION=redis`, and the application logs. Station workers run inside the application container in the production image. Avoid repeatedly dispatching the same export or notification until the original job state is understood.

## Asking for help

Search existing GitHub issues first. For a public bug report, include the KoAkademy version, deployment method, expected/actual behavior, sanitized logs, and minimal reproduction. Report security concerns privately according to [SECURITY.md](SECURITY.md).
