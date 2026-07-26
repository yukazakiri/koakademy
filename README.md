# KoAkademy

**Self-hosted school administration and learning platform** — enrollment workflows, student and faculty records, classes, schedules, grading, finance, and optional institutional modules in one application.

Built with **Laravel 12**, **Filament 5**, **Inertia**, and **React 19**. Ships as a single FrankenPHP (PHP 8.5) container image.

> [!WARNING]
> KoAkademy is currently in **Beta** and is **not recommended for production use**. Data loss, breaking changes, and incompatible upgrades are possible between pre-v2 releases. A stable, production-recommended release stream is planned starting at **v2.0.0**.

[![CI](https://github.com/yukazakiri/koakademy/actions/workflows/ci.yml/badge.svg)](https://github.com/yukazakiri/koakademy/actions/workflows/ci.yml)
[![Release](https://img.shields.io/github/v/release/yukazakiri/koakademy?sort=semver)](https://github.com/yukazakiri/koakademy/releases)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL%20v3-blue.svg)](LICENSE.md)
[![PHP](https://img.shields.io/badge/PHP-8.5-8892BF.svg?logo=php)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20.svg?logo=laravel)](https://laravel.com/)
[![Filament](https://img.shields.io/badge/Filament-5-27AE60.svg?logo=filament)](https://filamentphp.com/)
[![React](https://img.shields.io/badge/React-19-61DAFB.svg?logo=react)](https://react.dev/)
[![FrankenPHP](https://img.shields.io/badge/FrankenPHP-1.0-461B73.svg?logo=frankenphp)](https://frankenphp.com/)
[![Docker](https://img.shields.io/docker/pulls/yukazakiri/koakademy?logo=docker)](https://hub.docker.com/r/yukazakiri/koakademy)
[![GitHub stars](https://img.shields.io/github/stars/yukazakiri/koakademy?logo=github)](https://github.com/yukazakiri/koakademy/stars)
[![GitHub forks](https://img.shields.io/github/forks/yukazakiri/koakademy?logo=github)](https://github.com/yukazakiri/koakademy/network)
[![GitHub last commit](https://img.shields.io/github/last-commit/yukazakiri/koakademy)](https://github.com/yukazakiri/koakademy/commits)
[![Maintenance](https://img.shields.io/badge/maintenance-✓-green.svg)](https://github.com/yukazakiri/koakademy)
[![Status: Beta](https://img.shields.io/badge/status-beta-orange.svg)](https://github.com/yukazakiri/koakademy)

> **Project status: production-capable beta.** KoAkademy has a documented production topology and automated tests, but operators should validate upgrades in staging, maintain backups, and review the security model for their institution. Only the latest stable release is supported.

## Highlights

- **Guided first-run setup** — a `/setup` wizard creates the institution and the first super administrator; no CLI user seeding required.
- **Full academic administration** — students, faculty, courses, classes, rooms, schedules/timetables with conflict detection, enrollment, and grading (point/percent scales, GWA rules) through a Filament admin panel.
- **Enrollment engine** — a verification pipeline (registrar → cashier) plus versioned enrollment **blueprints**: scoped, inheritable policies with simulation, staged rollout, and rollback.
- **Three React portals** — dedicated Inertia/React experiences for administrators, faculty (attendance, class posts, submissions, grading), and students (classes, schedule, tuition, digital ID).
- **Finance** — tuition assessment, cashier workflows with payment posting, statements of account with signed public verification, and finance reports.
- **Security built in** — role- and permission-aware access (30 roles via Filament Shield), TOTP and email-code MFA, WebAuthn passkeys, impersonation, and full activity logging.
- **Optional modules** — Inventory, Library, Cashier, Student Medical Records, Announcements, and a template-based Notification Center, all toggleable per institution.
- **Platform features** — Gotenberg-backed PDF generation (SOA, timetables, assessments), Excel exports, school-scoped multi-tenancy, 36 runtime feature flags (Laravel Pennant), PWA manifest, broadcasting, and a documented Sanctum API.

The API surface is beta. Only endpoints listed in the [API documentation](docs/src/content/docs/api/api-overview.mdx) are part of the documented public contract.

## Screenshots

<p align="center">
  <img src="docs/src/assets/enrollment-policies/blueprint-overview.png" alt="Enrollment blueprint overview" width="49%" />
  <img src="docs/src/assets/enrollment-policies/approval-workflow.png" alt="Enrollment approval workflow editor" width="49%" />
</p>

## Supported production topology

- KoAkademy application image (PHP 8.5 with FrankenPHP)
- PostgreSQL
- Redis for cache, sessions, and queues
- Gotenberg for `spatie/laravel-pdf`
- Local RustFS for single-node evaluation/small installations, or external S3-compatible object storage for redundant production uploads
- An operator-managed HTTPS edge such as Caddy, Nginx, Traefik, or a tunnel

The default installer uses Docker Swarm, publishes the application on host port `8000`, and keeps PostgreSQL, Redis, and Gotenberg on a private overlay. Local RustFS publishes only its S3 API on host port `9000`; its console remains private. The manual `compose.production.yaml` topology remains available and binds the application only to `127.0.0.1:8000`.

## Quick start

Requirements: Docker Engine on Linux, or Docker Desktop using Linux containers on Windows.

```sh
bash -c "$(curl -fsSL https://raw.githubusercontent.com/yukazakiri/koakademy/master/scripts/install.sh)"
```

Windows PowerShell:

```powershell
& ([scriptblock]::Create((irm https://raw.githubusercontent.com/yukazakiri/koakademy/master/scripts/install.ps1)))
```

The installer preserves an existing Swarm, generates Docker secrets, prompts for local RustFS or external S3 credentials, runs migrations, and verifies the application before printing `/setup`. The raw commands become anonymously available when the repository is public; review the scripts before executing privileged remote code.

Verify the default host port again at any time with `curl --fail http://127.0.0.1:8000/up`.

Stable multi-architecture images are published to `ghcr.io/yukazakiri/koakademy:vX.Y.Z` and `yukazaki/koakademy:vX.Y.Z`. Exact stable tags are the production contract. `latest` tracks the newest stable release, while the explicitly selected `edge` tag is unsupported rolling software for evaluation only.

See [Getting Started](GETTING_STARTED.md) for installer overrides and the manual Compose path, and [Deployment](DEPLOYMENT.md) before exposing the service.

## Documentation

- [Getting started](GETTING_STARTED.md) — production installation
- [Deployment](DEPLOYMENT.md) — topology, upgrades, backups, rollback
- [Configuration](CONFIGURATION.md) — environment and service contract
- [Architecture](ARCHITECTURE.md) — runtime, layers, tenancy, queues
- [Operations and troubleshooting](TROUBLESHOOTING.md)
- [Native development](DEVELOPMENT.md)
- [FAQ](FAQ.md)
- [Changelog](CHANGELOG.md)
- [Open-source readiness](OSS_DOCS.md)
- [CI contract](OSS_CI.md)

Root Markdown files are canonical for technical and project documentation. Marked MDX copies are generated for Astro and the in-app documentation; run `npm run docs:sync` after editing a canonical file.

The GitHub Pages deployment is intentionally disabled while the repository is private. The complete Starlight site remains buildable locally from `docs/`.

## Contributing and security

Bug reports and pull requests are welcome; start with [CONTRIBUTING.md](CONTRIBUTING.md). Do not report suspected vulnerabilities in public issues — follow [SECURITY.md](SECURITY.md) and use GitHub Security Advisories.

## License

KoAkademy is licensed under [GNU AGPL-3.0-or-later](LICENSE.md). Network users must be offered the corresponding source code for the version they are using, including your modifications.
