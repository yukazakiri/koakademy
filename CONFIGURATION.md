# Configuration

The one-line Swarm installation is deployment-managed. Non-secret runtime settings live in `/opt/koakademy/runtime.env` with mode 0600; credentials live only in Docker Secrets. Do not edit that file or a container `.env` by hand. Use the `koakademy` command so a change is validated, secrets are rotated, and FrankenPHP workers restart.

## Runtime commands

```sh
sudo koakademy status
sudo koakademy configure storage local
sudo koakademy configure storage s3
sudo koakademy configure storage r2
sudo koakademy configure storage library-r2
sudo koakademy configure mail log
sudo koakademy configure mail smtp
sudo koakademy configure mail sequenzy
sudo koakademy configure search enable
sudo koakademy configure search disable
```

Commands prompt for values and hide secrets. For unattended use, supply the documented `KOAKADEMY_*` environment variable for each prompt; do not put credential values in shared shell history or source control.

## Storage

Fresh installs use `FILESYSTEM_DISK=public` on the persistent app volume. This keeps first boot simple but is tied to one VPS. Move uploads to S3 or Cloudflare R2 before relying on the server as production infrastructure:

```sh
sudo koakademy configure storage r2
```

The command collects the endpoint, bucket, region, optional public/CDN URL, access key, and secret key. It stores only the two keys as Docker Secrets. R2 selects region `auto` and path-style requests by default; generic S3 asks for those provider-specific values.

Digital Library editions require a separate private bucket. Configure it separately:

```sh
sudo koakademy configure storage library-r2
```

Never expose that bucket through a public URL. Limit credentials to its bucket, retain the existing reader/download authorization controls, and configure exact-origin CORS for PDF range requests.

## Mail

First boot uses Laravel's `log` mailer. Configure external delivery only through the operator command:

```sh
sudo koakademy configure mail smtp
```

SMTP accepts host, port, scheme, username, password, and sender identity. Sequenzy accepts its API endpoint and API key. The password or API key becomes a Docker Secret; the System Management page intentionally shows only the active mailer and sender identity. It cannot edit or test credentials from the web application.

## Search

Meilisearch is external-only:

```sh
sudo koakademy configure search enable
```

Provide the external HTTPS endpoint and API key. KoAkademy enables the Scout Meilisearch driver, rolls the app service, synchronizes index settings, and imports the registered searchable models. No Meilisearch service is added to the Swarm stack. Disable it with `sudo koakademy configure search disable`.

## Application and routing

Caddy terminates HTTPS and is the only public service. It forwards to the internal FrankenPHP app over the Swarm overlay. The installer sets:

```dotenv
APP_URL=https://school.example
PORTAL_HOST=school.example
ADMIN_HOST=school.example
TRUSTED_PROXIES=*
SESSION_SECURE_COOKIE=true
OCTANE_SERVER=frankenphp
```

The wildcard proxy trust is valid because the app service is not published outside the overlay. If the topology is changed to expose the app directly, replace it with explicit proxy addresses or CIDRs.

## Manual Compose configuration

`.env.production.example` remains the public interface for `compose.production.yaml`. Compose operators must create a protected `.env`, generate one `APP_KEY` per installation, configure a dedicated PostgreSQL/Redis password and external S3-compatible storage, then restart the app after any environment change. Under FrankenPHP, an environment or configuration change does not reach already booted workers until a restart.

Keep `RUN_MIGRATIONS=false`. Migrations are explicit install/update operations, never app-startup work.
