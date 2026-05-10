<!-- Improved compatibility of back to top link: See: https://github.com/othneildrew/Best-README-Template/pull/73 -->
<a id="readme-top"></a>

<!-- PROJECT SHIELDS -->
[![Contributors][contributors-shield]][contributors-url]
[![Forks][forks-shield]][forks-url]
[![Stargazers][stars-shield]][stars-url]
[![Issues][issues-shield]][issues-url]
[![AGPLv3 License][license-shield]][license-url]

<!-- PROJECT LOGO -->
<br />
<div align="center">
  <a href="https://github.com/yukazakiri/koakademy">
    <img src="https://raw.githubusercontent.com/koamishin/KoamiStarterKit/main/public/koamishin-logo.svg" alt="KoAkademy Logo" width="96" height="96" />
  </a>

  <h3 align="center">KoAkademy</h3>

  <p align="center">
    Academic management platform built on Laravel 12, Filament, Inertia + React, and Tailwind CSS.
    <br />
    <a href="GETTING_STARTED.md"><strong>Explore the docs »</strong></a>
    <br />
    <br />
    <a href="https://portal.koakademy.edu">View Demo</a>
    &middot;
    <a href="https://github.com/yukazakiri/koakademy/issues/new?labels=bug">Report Bug</a>
    &middot;
    <a href="https://github.com/yukazakiri/koakademy/issues/new?labels=enhancement">Request Feature</a>
  </p>
</div>

<!-- TABLE OF CONTENTS -->
<details>
  <summary>Table of Contents</summary>
  <ol>
    <li>
      <a href="#about-the-project">About The Project</a>
      <ul>
        <li><a href="#built-with">Built With</a></li>
      </ul>
    </li>
    <li>
      <a href="#getting-started">Getting Started</a>
      <ul>
        <li><a href="#quick-start">Quick Start</a></li>
      </ul>
    </li>
    <li><a href="#deployment">Deployment</a></li>
    <li><a href="#development">Development</a></li>
    <li><a href="#usage">Usage</a></li>
    <li><a href="#roadmap">Roadmap</a></li>
    <li><a href="#contributing">Contributing</a></li>
    <li><a href="#license">License</a></li>
    <li><a href="#acknowledgments">Acknowledgments</a></li>
  </ol>
</details>

<!-- ABOUT THE PROJECT -->
## About The Project

[![KoAkademy][product-screenshot]](https://portal.koakademy.edu)

KoAkademy is a Laravel-based academic platform for student lifecycle workflows (enrollment, billing/tuition, schedules, and administrative operations).
It uses Inertia + React for the UI and Filament for admin tooling, with settings-driven branding (logo/favicon/Open Graph) and PWA support.

Here's why:
* Academic workflows shouldn’t sprawl across multiple tools.
* Branding and metadata should be configurable without code changes.
* Local development should be predictable and containerized.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

### Built With

* [![Laravel][Laravel.com]][Laravel-url]
* [![Inertia][Inertia.shield]][Inertia-url]
* [![React][React.js]][React-url]
* [![Tailwind CSS][Tailwind.shield]][Tailwind-url]
* [![PostgreSQL][Postgres.shield]][Postgres-url]
* [![Vite][Vite.shield]][Vite-url]

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- GETTING STARTED -->
## Getting Started

KoAkademy is a self-hosted academic management platform for student portals, admin work, enrollment, finance, schedules, and content.

Just want to run it? Start with Docker below. Want to contribute code? Jump to [Development](#development).

### Quick Start

This quick start uses the smallest practical setup: KoAkademy + SQLite + Redis.

- SQLite is the default database.
- Redis is included because the production image uses Horizon for queues.

<details open>
<summary><strong>One copy-paste quick start</strong></summary>

```sh
mkdir -p koakademy && cd koakademy
mkdir -p database
touch database/database.sqlite
APP_KEY="base64:$(openssl rand -base64 32)"
cat > .env <<EOF
APP_NAME="KoAkademy"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost:8000
APP_KEY=${APP_KEY}
PORTAL_HOST=localhost
ADMIN_HOST=localhost
REDIS_HOST=redis
QUEUE_CONNECTION=redis
EOF
cat > compose.yaml <<'EOF'
services:
  app:
    image: docker.io/yukazakiri/koakademy:latest
    restart: unless-stopped
    env_file: .env
    ports:
      - "8000:8000"
    volumes:
      - koakademy-storage:/app/storage
      - ./database/database.sqlite:/app/database/database.sqlite
    depends_on:
      redis:
        condition: service_started

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    volumes:
      - koakademy-redis:/data

volumes:
  koakademy-storage:
  koakademy-redis:
EOF
docker compose up -d
docker compose logs --tail=100 app
```

Open `http://localhost:8000`, then create your first admin user:

```sh
docker compose exec app php artisan make:filament-user
```

</details>

<details>
<summary><strong>Image tags (which one should I use?)</strong></summary>

* `docker.io/yukazakiri/koakademy:latest` — stable (recommended for most users).
* `docker.io/yukazakiri/koakademy:dev-latest` — rolling updates (for early testing).
* `ghcr.io/yukazakiri/koakademy:latest` — GitHub Container Registry mirror when enabled for a release.

To try the rolling build, change the app image to:

```yaml
image: docker.io/yukazakiri/koakademy:dev-latest
```

</details>

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- DEPLOYMENT -->
## Deployment

For self-hosting, start small with Docker Compose and add extra services only when you need them.

By default, the app already works with:

- SQLite database
- database-backed cache/sessions
- log mail driver
- collection search

<details>
<summary><strong>Minimal Docker Compose (recommended starting point)</strong></summary>

```yaml
services:
  app:
    image: docker.io/yukazakiri/koakademy:latest
    restart: unless-stopped
    env_file: .env
    ports:
      - "8000:8000"
    volumes:
      - koakademy-storage:/app/storage
      - ./database/database.sqlite:/app/database/database.sqlite
    depends_on:
      - redis

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    volumes:
      - koakademy-redis:/data

volumes:
  koakademy-storage:
  koakademy-redis:
```

Required `.env` values for this minimal setup:

```env
APP_NAME="KoAkademy"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
APP_KEY=base64:replace-with-generated-key
PORTAL_HOST=your-domain.example
ADMIN_HOST=your-domain.example
REDIS_HOST=redis
QUEUE_CONNECTION=redis
```

</details>

<details>
<summary><strong>Same thing with docker run</strong></summary>

```sh
mkdir -p koakademy/database
touch koakademy/database/database.sqlite
docker network create koakademy 2>/dev/null || true
docker run -d --name koakademy-redis --restart unless-stopped --network koakademy redis:7-alpine
docker run -d \
  --name koakademy \
  --restart unless-stopped \
  --network koakademy \
  -p 8000:8000 \
  -v koakademy-storage:/app/storage \
  -v "$(pwd)/koakademy/database/database.sqlite:/app/database/database.sqlite" \
  -e APP_NAME="KoAkademy" \
  -e APP_ENV=production \
  -e APP_DEBUG=false \
  -e APP_URL=http://localhost:8000 \
  -e APP_KEY=base64:replace-with-generated-key \
  -e PORTAL_HOST=localhost \
  -e ADMIN_HOST=localhost \
  -e REDIS_HOST=koakademy-redis \
  -e QUEUE_CONNECTION=redis \
  docker.io/yukazakiri/koakademy:latest
```

Generate an app key with:

```sh
printf 'base64:%s\n' "$(openssl rand -base64 32)"
```

</details>

<details>
<summary><strong>Production add-ons you’ll probably want later</strong></summary>

* Put Caddy, Traefik, Nginx, Cloudflare Tunnel, or your platform proxy in front of port `8000` for HTTPS.
* Move to PostgreSQL when you need stronger multi-user production database operations (recommended).
* Add Meilisearch when you want external Scout search indexing.
* Configure SMTP for real outbound email.
* Use S3 or Cloudflare R2 for durable uploads if you run more than one app node or replace hosts often.
* Keep `/app/storage` persistent. For SQLite installs, also keep `/app/database/database.sqlite` persistent.

</details>

<details>
<summary><strong>Database options (pick one)</strong></summary>

### 1) SQLite (fastest way to get running)

```env
DB_CONNECTION=sqlite
```

Mount a writable file:

```yaml
volumes:
  - ./database/database.sqlite:/app/database/database.sqlite
```

### 2) PostgreSQL (recommended for production)

```env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=koakademy
DB_USERNAME=koakademy
DB_PASSWORD=replace-with-strong-password
```

Example service:

```yaml
pgsql:
  image: postgres:17-alpine
  restart: unless-stopped
  environment:
    POSTGRES_DB: koakademy
    POSTGRES_USER: koakademy
    POSTGRES_PASSWORD: replace-with-strong-password
  volumes:
    - koakademy-pgsql:/var/lib/postgresql/data
```

### 3) MySQL / MariaDB (if that’s your existing stack)

```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=koakademy
DB_USERNAME=koakademy
DB_PASSWORD=replace-with-strong-password
```

Example service:

```yaml
mysql:
  image: mysql:8.4
  restart: unless-stopped
  environment:
    MYSQL_DATABASE: koakademy
    MYSQL_USER: koakademy
    MYSQL_PASSWORD: replace-with-strong-password
    MYSQL_ROOT_PASSWORD: replace-with-strong-root-password
  volumes:
    - koakademy-mysql:/var/lib/mysql
```

</details>

<details>
<summary><strong>Environment notes</strong></summary>

For the minimal Docker setup, these are the main values you should set:

* `APP_KEY` — required encryption key.
* `APP_NAME` — display name, defaults to `KoAkademy`.
* `APP_URL` — public URL used for generated links.
* `PORTAL_HOST` — portal route hostname. Use `localhost` for local testing.
* `ADMIN_HOST` — admin route hostname. Use `localhost` for local testing.
* `REDIS_HOST` and `QUEUE_CONNECTION=redis` — needed because Horizon runs in the production image.

The Docker image runs migrations by default (`RUN_MIGRATIONS=true`) and listens on port `8000`.

</details>

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- DEVELOPMENT -->
## Development

Working on KoAkademy locally? Use the setup scripts. They handle the boring setup for you: env file, dependencies, local domains, certs, and services.

<details open>
<summary><strong>Linux</strong></summary>

```sh
git clone https://github.com/yukazakiri/koakademy.git
cd koakademy
./scripts/dev-setup.sh
```

Useful flags:

```sh
./scripts/dev-setup.sh --fresh
./scripts/dev-setup.sh --skip-ssl
./scripts/dev-setup.sh --skip-hosts
./scripts/dev-setup.sh --skip-docker
```

This script prepares the Docker Compose dev stack, local HTTPS certs, and hosts entries for `.test` domains.

</details>

<details open>
<summary><strong>Windows / PowerShell + Laravel Herd</strong></summary>

Install [Laravel Herd](https://herd.laravel.com/) first, then run:

```powershell
git clone https://github.com/yukazakiri/koakademy.git
cd koakademy
.\scripts\dev-setup.ps1
```

Useful flags:

```powershell
.\scripts\dev-setup.ps1 -SkipMigrations
.\scripts\dev-setup.ps1 -SkipNpm
.\scripts\dev-setup.ps1 -SkipHosts
```

The PowerShell script expects Laravel Herd and configures Herd-managed local domains and HTTPS.

</details>

<details>
<summary><strong>Local development URLs</strong></summary>

The scripts use domains from your `.env`. Common defaults are:

* `https://portal.koakademy.test`
* `https://admin.koakademy.test`
* `http://mailpit.local.test:8025`

</details>

<details>
<summary><strong>Common development commands</strong></summary>

```sh
php artisan migrate
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run dev
npm run build
```

If you are using Sail / Docker Compose for development, prefix PHP and Node commands with `vendor/bin/sail`.

</details>

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- USAGE EXAMPLES -->
## Usage

Quick links after the Docker quick start:

* `http://localhost:8000`
* `http://localhost:8000/admin`
* `http://localhost:8000/administrators`

Useful container commands:

```sh
docker compose up -d
docker compose logs --tail=100 app
docker compose exec app php artisan make:filament-user
docker compose exec app php artisan migrate --force
docker compose pull app && docker compose up -d
docker compose down
```

Local development commands:

```sh
php artisan migrate
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run dev
npm run build
```

Docs:

* [Getting Started](GETTING_STARTED.md)
* [Development Guide](DEVELOPMENT.md)
* [Deployment Guide](DEPLOYMENT.md)
* [Dev Container Setup](DEVCONTAINER_SETUP.md)

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- ROADMAP -->
## Roadmap

- [ ] Continue migration of legacy hardcoded brand/domain strings to settings-driven values
- [ ] Expand API docs coverage for enrollment and finance endpoints
- [ ] Improve release automation and deployment validation checks

See the [open issues][issues-url] for a full list of proposed features (and known issues).

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- CONTRIBUTING -->
## Contributing

Contributions are welcome.

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'feat: add amazing feature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

Before opening a PR, please run:

```sh
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail artisan test --compact
```

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- LICENSE -->
## License

Distributed under the GNU Affero General Public License v3.0 or later. See [`LICENSE.md`](LICENSE.md) for more information.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- ACKNOWLEDGMENTS -->
## Acknowledgments

* [Laravel](https://laravel.com)
* [Filament](https://filamentphp.com)
* [Inertia.js](https://inertiajs.com)
* [React](https://react.dev)
* [Tailwind CSS](https://tailwindcss.com)
* [Shields.io](https://shields.io)

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- MARKDOWN LINKS & IMAGES -->
<!-- https://www.markdownguide.org/basic-syntax/#reference-style-links -->
[contributors-shield]: https://img.shields.io/github/contributors/yukazakiri/koakademy.svg?style=for-the-badge
[contributors-url]: https://github.com/yukazakiri/koakademy/graphs/contributors
[forks-shield]: https://img.shields.io/github/forks/yukazakiri/koakademy.svg?style=for-the-badge
[forks-url]: https://github.com/yukazakiri/koakademy/network/members
[stars-shield]: https://img.shields.io/github/stars/yukazakiri/koakademy.svg?style=for-the-badge
[stars-url]: https://github.com/yukazakiri/koakademy/stargazers
[issues-shield]: https://img.shields.io/github/issues/yukazakiri/koakademy.svg?style=for-the-badge
[issues-url]: https://github.com/yukazakiri/koakademy/issues
[license-shield]: https://img.shields.io/github/license/yukazakiri/koakademy.svg?style=for-the-badge
[license-url]: https://github.com/yukazakiri/koakademy/blob/master/LICENSE.md

[product-screenshot]: https://raw.githubusercontent.com/koamishin/KoamiStarterKit/main/public/koamishin-logo.svg

[Laravel.com]: https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white
[Laravel-url]: https://laravel.com
[Inertia.shield]: https://img.shields.io/badge/Inertia-9553E9?style=for-the-badge&logo=inertia&logoColor=white
[Inertia-url]: https://inertiajs.com
[React.js]: https://img.shields.io/badge/React-20232A?style=for-the-badge&logo=react&logoColor=61DAFB
[React-url]: https://react.dev
[Tailwind.shield]: https://img.shields.io/badge/Tailwind_CSS-38BDF8?style=for-the-badge&logo=tailwindcss&logoColor=white
[Tailwind-url]: https://tailwindcss.com
[Postgres.shield]: https://img.shields.io/badge/PostgreSQL-316192?style=for-the-badge&logo=postgresql&logoColor=white
[Postgres-url]: https://www.postgresql.org
[Vite.shield]: https://img.shields.io/badge/Vite-646CFF?style=for-the-badge&logo=vite&logoColor=white
[Vite-url]: https://vitejs.dev
