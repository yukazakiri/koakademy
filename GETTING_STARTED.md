# Getting Started with KoAkademy

This guide is for contributors setting up the repository locally.

## Prerequisites

- Docker Desktop or Docker Engine with Compose
- PHP and Composer for the initial bootstrap step
- Node.js 22+
- Git

## 1. Clone the Repository

```bash
git clone https://github.com/yukazakiri/koakademy.git
cd koakademy
```

## 2. Use the Setup Scripts

The preferred contributor setup path is through the helper scripts in [`scripts/`](scripts/).

### Linux and macOS

Run the main Docker-based bootstrap script:

```bash
./scripts/dev-setup.sh
```

Common options:

```bash
./scripts/dev-setup.sh --skip-ssl
./scripts/dev-setup.sh --skip-hosts
./scripts/dev-setup.sh --skip-docker
```

Optional helpers:

```bash
./scripts/setup-sail-alias.sh
./scripts/setup-ssl.sh
./scripts/fix-ssl.sh
```

### Windows

Run the PowerShell setup script:

```powershell
.\scripts\dev-setup.ps1
```

Common options:

```powershell
.\scripts\dev-setup.ps1 -SkipMigrations
.\scripts\dev-setup.ps1 -SkipNpm
.\scripts\setup-ssl.ps1
```

## 3. Manual Sail Bootstrap

If you prefer to do the setup manually, use the same Sail-based flow that the scripts automate:

```bash
# Required once so vendor/bin/sail exists
composer install

cp .env.example .env
vendor/bin/sail up -d
vendor/bin/sail npm install
vendor/bin/sail artisan key:generate
vendor/bin/sail artisan migrate
```

If you want sample data:

```bash
vendor/bin/sail artisan db:seed
```

## 4. Configure Local Domains

The setup scripts can manage hosts and certificates for you.

If you want to manage hosts manually, add these entries:

```text
127.0.0.1 portal.koakademy.test
127.0.0.1 admin.koakademy.test
127.0.0.1 mailpit.local.test
127.0.0.1 minio.local.test
127.0.0.1 minio-console.local.test
```

If local HTTPS certificates need to be created or repaired, use:

```bash
./scripts/setup-ssl.sh
./scripts/fix-ssl.sh
```

## 5. Start the App

For day-to-day development:

```bash
vendor/bin/sail npm run dev
```

If the Docker stack is not already running:

```bash
vendor/bin/sail up -d
```

Primary local entrypoints:

- `https://portal.koakademy.test`
- `https://admin.koakademy.test`
- `http://mailpit.local.test:8025`

## 6. Verify the Setup

Run a small test slice and a production asset build:

```bash
vendor/bin/sail artisan test --compact
vendor/bin/sail npm run build
```

## Project Layout

```text
app/                  Application services, models, controllers, settings
app/Filament/         Filament resources, pages, widgets, clusters
config/               Framework and application configuration
database/             Migrations, factories, seeders, settings migrations
docs/                 Product and API documentation
resources/js/         Inertia pages, shared React UI, client logic
routes/               Web, admin, portal, and API routes
scripts/              Local setup, SSL, and developer utility scripts
tests/                Pest feature and unit tests
```

## Common Commands

```bash
vendor/bin/sail artisan migrate
vendor/bin/sail artisan db:seed
vendor/bin/sail artisan test --compact tests/Feature/SomeFeatureTest.php
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail npm run dev
vendor/bin/sail npm run build
```

## Notes for Contributors

- Prefer the scripts in `scripts/` for first-time setup, SSL repair, and local convenience tasks.
- Prefer config values, settings records, and shared services over hardcoded product names or domains.
- Use existing factories and seeders when writing tests.
- Keep changes small and verify the affected feature before moving on.
