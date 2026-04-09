# Development Guide

This document covers the normal contributor workflow for KoAkademy.

## Daily Workflow

Start the stack:

```bash
vendor/bin/sail up -d
vendor/bin/sail npm run dev
```

Run targeted tests while you work:

```bash
vendor/bin/sail artisan test --compact tests/Feature/SomeFeatureTest.php
```

Format changed PHP files before you finish:

```bash
vendor/bin/sail bin pint --dirty --format agent
```

## Project Conventions

- Use `vendor/bin/sail` for PHP, Artisan, Composer, and Node commands.
- Keep branding dynamic. Use configuration or database-backed settings instead of hardcoded `KoAkademy`, domains, or legacy names in runtime code.
- Follow existing patterns in neighboring files before adding new abstractions.
- Add or update tests for each code change.

## Where Things Live

- `app/Settings/` holds database-backed settings objects.
- `app/Services/` holds shared application services.
- `app/Providers/Filament/` configures the admin and portal panels.
- `resources/js/pages/` contains Inertia page entries.
- `resources/js/components/` contains shared React UI.
- `database/settings/` contains settings migrations and backfills.
- `tests/Feature/` covers user-facing behavior.
- `tests/Unit/` covers focused service and helper logic.

## Backend Work

Useful commands:

```bash
vendor/bin/sail artisan make:test --pest Feature/ExampleFeatureTest
vendor/bin/sail artisan route:list --except-vendor
vendor/bin/sail artisan config:show app.name
vendor/bin/sail artisan migrate
```

Guidelines:

- Prefer named routes and generated route helpers over hardcoded URLs.
- Validate input with form requests or existing request validation patterns.
- Reuse settings and configuration services for branding, domains, and shared metadata.

## Frontend Work

Useful commands:

```bash
vendor/bin/sail npm run dev
vendor/bin/sail npm run build
vendor/bin/sail npm run lint
```

Guidelines:

- Check for existing components before creating new ones.
- Keep route usage aligned with the project’s existing Wayfinder and route helper patterns.
- Preserve the current UI language unless the task explicitly asks for a broader redesign.

## Testing and Verification

Default workflow:

```bash
vendor/bin/sail artisan test --compact tests/Feature/SomeFeatureTest.php
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail npm run build
```

Run the smallest relevant test set first. Expand only when the change touches cross-cutting behavior.

## Local Troubleshooting

- If `vendor/bin/sail` is missing, run `composer install`.
- If Vite assets are stale, run `vendor/bin/sail npm run build` or `vendor/bin/sail npm run dev`.
- If domains do not resolve, check your hosts file entries for `portal.koakademy.test` and `admin.koakademy.test`.
