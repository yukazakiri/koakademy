# Development

This guide covers native development and testing. Production operators should use [Getting Started](GETTING_STARTED.md) instead.

## Toolchain

- PHP 8.5 and Composer 2
- Node.js 22 and npm
- `rsvg-convert` from `librsvg2-bin` for SVG brand uploads
- SQLite for the default test/development path, or PostgreSQL for integration work
- Redis and Gotenberg when working on queues, sessions, cache, or PDF features

The repository also includes a development-oriented `compose.yaml`. It is not the production topology and publishes additional tools and ports.

## Native setup

```sh
git clone https://github.com/yukazakiri/koakademy.git
cd koakademy
composer install
npm ci
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000/setup` to create development data and the first administrator. Run a Vite development server with `npm run dev` when editing frontend assets.

## Common commands

```sh
composer test
vendor/bin/pint --test
npm run build
npm run format:check
npm run docs:check
npm --prefix docs ci
npm --prefix docs run build
```

Run a focused Pest file while iterating:

```sh
php artisan test --compact tests/Feature/SetupAvailabilityTest.php
php artisan test --compact --filter="shows the setup screen"
```

## Documentation workflow

Root project/technical Markdown files are canonical. Edit those files, then run:

```sh
npm run docs:sync
npm run docs:check
```

The sync command generates marked MDX mirrors consumed by both Astro and the in-app documentation. Never edit generated mirrors directly. Operator guides and enrollment blueprints under `docs/src/content/docs/` remain native MDX and are edited in place.

## Hosted documentation site

The Astro site in `docs/` is always buildable locally with `npm --prefix docs run build`. Its `deploy-docs.yml` workflow is intentionally disabled while the repository is private.

After making the repository public, enable GitHub Pages in **Settings → Pages → Build and deployment → Source: GitHub Actions**, then re-enable the workflow:

```sh
gh workflow enable deploy-docs.yml --repo yukazakiri/koakademy
```

Successful deployments publish to `https://yukazakiri.github.io/koakademy/`.

## Application structure

- `app/` — application services, models, middleware, jobs, policies, and support code
- `routes/` — web, API, console, and channel routes
- `resources/js/` — Inertia React pages and frontend components
- `Modules/` — optional domain modules loaded by Laravel Modules
- `database/` — migrations, factories, and seeders
- `tests/` — Pest feature and unit tests
- `docs/` — Astro documentation site and in-app Markdown sources
- `docker/` — production image and runtime scripts

See [Architecture](ARCHITECTURE.md) for boundaries and production dependencies.

## Database changes

Create migrations rather than modifying historical migrations. Include factories or test fixtures when a behavior needs representative data. Test migrations against SQLite when supported and PostgreSQL when using database-specific features. Production migrations must be compatible with the documented explicit `migrate --force` upgrade step.

## Backend conventions

- Use strict types in new PHP files.
- Validate request input and authorize protected actions.
- Keep controllers focused on HTTP translation; place reusable workflows in services or actions.
- Use Eloquent relationships, eager loading, and database constraints deliberately.
- Dispatch expensive exports, mail, indexing, and PDF work to queues.
- Keep secrets and host-specific values in configuration, never source code.

## Frontend conventions

Use TypeScript for React code and existing design-system primitives before adding dependencies. Preserve keyboard access, visible focus, meaningful labels, reduced-motion behavior, loading states, and actionable error messages. Frontend calls to Laravel routes should use generated Wayfinder helpers where available.

## Adding or changing APIs

API routes are not documented merely because they exist. New public documentation requires:

1. Authentication and authorization behavior
2. Request validation and response tests
3. Stable examples based on actual controller responses
4. An update to the API documentation contract test

The currently published subset is listed in `docs/src/content/docs/api/api-overview.mdx`.
