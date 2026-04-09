# Dev Container Setup

Use this guide if you work on KoAkademy through a VS Code dev container or another Docker-based containerized editor.

## Host Requirements

- Docker Desktop or Docker Engine with Compose on the host machine
- A trusted local certificate setup if you want HTTPS on local domains
- Hosts file access on the host machine

## Local Domains

Add these entries to the host machine:

```text
127.0.0.1 portal.koakademy.test
127.0.0.1 admin.koakademy.test
127.0.0.1 mailpit.local.test
127.0.0.1 minio.local.test
127.0.0.1 minio-console.local.test
```

On Windows PowerShell, from the repository root:

```powershell
.\scripts\dev-setup.ps1
```

## Start the Stack

From the repository root:

```bash
composer install
vendor/bin/sail up -d
vendor/bin/sail npm install
vendor/bin/sail artisan migrate
```

For active frontend work:

```bash
vendor/bin/sail npm run dev
```

## Primary URLs

- `https://portal.koakademy.test`
- `https://admin.koakademy.test`
- `http://mailpit.local.test:8025`
- `http://minio.local.test:9000`

## Helpful Dev Container Files

The `.devcontainer/` folder includes setup notes, checks, and troubleshooting documents. Start with:

- `.devcontainer/00-START-HERE.md`
- `.devcontainer/QUICKSTART.md`
- `.devcontainer/TROUBLESHOOTING.md`

## Verification

If present in your local setup, run:

```bash
.devcontainer/setup-domains.sh
```

Then verify:

- the Docker services are healthy
- local domains resolve
- the admin and portal hosts load

## Troubleshooting

- If the domains do not resolve, re-check the host machine’s hosts file rather than the container’s hosts file.
- If HTTPS is broken locally, use the SSL helper in [scripts/README.md](scripts/README.md).
- If the app loads without styles or scripts, run `vendor/bin/sail npm run build` or `vendor/bin/sail npm run dev`.
