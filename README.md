<h1 align="center">KoAkademy</h1>

<p align="center">
  <strong>An open-source school administration platform built from real classroom experience.</strong><br>
  Your students' records belong to your institution—not to a vendor subscription.
</p>

<p align="center">
  <a href="#why-koakademy"><strong>Why KoAkademy</strong></a> ·
  <a href="#the-platform"><strong>The Platform</strong></a> ·
  <a href="#the-stack"><strong>Stack</strong></a> ·
  <a href="#quick-start"><strong>Quick Start</strong></a> ·
  <a href="#operate-it"><strong>Operate It</strong></a> ·
  <a href="CONTRIBUTING.md"><strong>Contributing</strong></a>
</p>

<p align="center">
  <a href="https://github.com/yukazakiri/koakademy/actions/workflows/ci.yml"><img alt="CI" src="https://github.com/yukazakiri/koakademy/actions/workflows/ci.yml/badge.svg"></a>
  <a href="https://github.com/yukazakiri/koakademy/releases"><img alt="Latest release" src="https://img.shields.io/github/v/release/yukazakiri/koakademy?sort=semver"></a>
  <a href="LICENSE.md"><img alt="AGPL-3.0-or-later licence" src="https://img.shields.io/badge/licence-AGPL--3.0--or--later-0b6e4f.svg"></a>
  <img alt="PHP 8.5" src="https://img.shields.io/badge/PHP-8.5-777bb4.svg">
  <img alt="Laravel 13" src="https://img.shields.io/badge/Laravel-13-ff2d20.svg">
  <img alt="Docker" src="https://img.shields.io/badge/runs%20on-Docker-2496ed.svg">
</p>

<p align="center">
  <img alt="KoAkademy administrator dashboard with institutional overview, enrolment monitoring, and finance shortcuts" src="docs/src/assets/screenshots/admin-dashboard.png" width="100%">
</p>

---

> [!WARNING]
> KoAkademy is beta software and is not yet recommended for production use. Pre-v2 releases can contain breaking changes or incompatible upgrades. Evaluate it in staging, keep tested backups, and review the [security guidance](SECURITY.md) before storing institutional data.

## Why KoAkademy

KoAkademy started in 2023 as a college feasibility project here in the Philippines. Back then, my groupmates and I were just students trying to solve a real problem at our school: the existing systems were either too expensive, too rigid, or simply not built for how Philippine schools actually operate. We wrote the first version in vanilla PHP. In 2024, we rebuilt it on Laravel. After graduation, I kept going—alone at first, then with the help of tools like Codex, and now as a working developer.

The system went live at our school and has been running there ever since. Today, two schools use it as part of their core infrastructure. I have never charged for it. I am not great at closing deals, and honestly, I have never been paid for this project. I just wanted to help my school innovate, even a little.

That is why I open-sourced it. If your institution cannot afford a commercial SIS or LIS, you should still have access to a system that respects your data and your budget.

**School software holds more than a timetable.** It holds student identities, grades, tuition, medical records, attendance, and years of institutional history. When that system is a hosted product, the school inherits someone else'"'"'s pricing, roadmap, retention policy, and operational decisions.

KoAkademy takes a different position: **the institution should own the system of record.** It is a complete school administration and learning platform that runs on infrastructure you choose. The PostgreSQL database, object storage, backups, domains, and upgrade schedule are yours to manage and audit.

No per-student pricing. No feature gates hidden behind a sales call. Just a tool that works.

## From the Author

Yes, this project is only half complete. But it is usable today, and it is already helping real schools. If you have suggestions, improvements, or just want to rant about a bug you found, you are always welcome. And if KoAkademy has been useful to you, a star on the repository goes a long way—it keeps me motivated to continue.

Feel free to try it and share feedback. Even the harsh kind. I appreciate every single one.

## A Note on School Operations

Every feature and data model in KoAkademy is shaped by the day-to-day workflow of real Philippine schools. The enrollment flow, grading schema, finance structure, and registrar processes reflect how schools here actually operate—which can differ quite a bit from what you might call "standard" international practice.

If you are from another country and find that our data schema or school operations do not quite match yours, pull requests are genuinely appreciated. I would love to make KoAkademy flexible enough to support schools worldwide, but I can only build for what I know. Your input helps us all.

## Screenshots

<table>
  <tr>
    <td width="50%">
      <img alt="KoAkademy enrolment management table" src="docs/src/assets/screenshots/enrollments.png">
      <p align="center"><sub><b>Enrollment</b> — track applicants, verification status, balances, and the registrar workflow in one place.</sub></p>
    </td>
    <td width="50%">
      <img alt="KoAkademy classes and academic scheduling workspace" src="docs/src/assets/screenshots/classes.png">
      <p align="center"><sub><b>Academics</b> — manage sections, capacity, curricula, and schedules with the school context intact.</sub></p>
    </td>
  </tr>
  <tr>
    <td width="50%">
      <img alt="KoAkademy finance dashboard" src="docs/src/assets/screenshots/finance.png">
      <p align="center"><sub><b>Finance</b> — assess tuition, receive payments, and issue records without exporting student data to another product.</sub></p>
    </td>
    <td width="50%">
      <img alt="KoAkademy searchable digital library catalogue" src="docs/src/assets/screenshots/library.png">
      <p align="center"><sub><b>Library</b> — catalogue physical holdings and publish rights-cleared digital editions through a controlled reader.</sub></p>
    </td>
  </tr>
</table>

## The Platform

KoAkademy is built around the daily work of a school, not a generic database with education fields added later.

|                                 |                                                                                                                                                                                               |
| ------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Administration**              | Students, faculty, programs, courses, classrooms, terms, schedules, attendance, grading, reports, and institution branding.                                                                   |
| **Enrollment**                  | A registrar-to-cashier workflow driven by versioned enrollment blueprints. Rules can be scoped, simulated, staged, and rolled back instead of being buried in code.                           |
| **Three portals**               | Distinct administrator, faculty, and student experiences for the work each role actually needs: classes, attendance, submissions, grades, schedules, tuition, announcements, and digital IDs. |
| **Finance**                     | Tuition assessment, payment posting, statements of account, receipts, public verification, and finance reporting.                                                                             |
| **Optional modules**            | Library, inventory, cashier, student medical records, announcements, and notification tools can be enabled as the institution needs them.                                                     |
| **Security and accountability** | Role-based permissions, multi-factor authentication, passkeys, audited impersonation, and an operator-controlled deployment boundary.                                                         |

The public API is intentionally small while it is beta. Only the endpoints described in the [API documentation](docs/src/content/docs/api/api-overview.mdx) are part of its supported contract.

### The enrollment engine

Enrollment is where a school’s rules become visible: who can enrol, which documents they need, which checks must happen before payment, what tuition applies, and when an account becomes active. Those are institutional policies, not constants that should require a deployment to change.

KoAkademy models them as versioned blueprints with inheritance, availability and eligibility checks, academic and billing gates, approvals, notifications, simulations, staged publication, and rollback. The result is a workflow a registrar can understand and an institution can adapt without losing its audit trail. Read the [blueprint overview](docs/src/content/docs/enrollment-policies/overview.mdx) for the model and the [quick start](docs/src/content/docs/enrollment-policies/quick-start.mdx) to configure one.

## The Stack

A Laravel application with a React portal layer, packaged for self-hosting rather than split across vendor services.

|                   |                                                                                                                      |
| ----------------- | -------------------------------------------------------------------------------------------------------------------- |
| **Application**   | [Laravel 13](https://laravel.com) · PHP 8.5 · [Filament 5](https://filamentphp.com)                                  |
| **Portals**       | [Inertia v3](https://inertiajs.com) · [React 19](https://react.dev) · TypeScript · [Vite 8](https://vitejs.dev)                                 |
| **Data and jobs** | PostgreSQL · Redis · Laravel queues, cache, sessions, and scheduled work                                             |
| **Documents**     | [Gotenberg](https://gotenberg.dev) for PDFs · S3-compatible object storage for uploads                               |
| **Runtime**       | nginx + PHP-FPM in a Docker image, with a FrankenPHP (Octane) variant · Docker Swarm installer or supported Docker Compose topology |
| **Quality**       | [Pest 5](https://pestphp.com) · Laravel Pint · frontend and documentation builds · CI validation                                              |

### Layout

| Path            |                                                                           |
| --------------- | ------------------------------------------------------------------------- |
| `app/`          | Laravel application services, models, policies, jobs, and HTTP boundaries |
| `resources/js/` | Inertia React pages and shared portal components                          |
| `Modules/`      | Optional domain modules                                                   |
| `database/`     | Migrations, factories, and seeders                                        |
| `docker/`       | Production image and runtime processes                                    |
| `docs/`         | Operator, maintainer, API, and staff documentation                        |
| `tests/`        | Pest feature and unit coverage                                            |

## Quick Start

The supported production installer is Linux-only. It installs Docker when needed, creates a single-node Swarm, deploys Caddy HTTPS with PostgreSQL, Redis, Gotenberg, and a private FrankenPHP application, then opens the one-time `/setup` wizard.

**Linux**

```sh
curl -fsSL https://github.com/yukazakiri/koakademy/releases/latest/download/install.sh | sudo bash -s -- install --domain school.example
```

Visit `/setup` when the installer finishes to create the institution, the first academic period, and the first super administrator. The setup route closes after initialization.

The installer runs privileged remote code. Inspect it first if that is not appropriate for your environment:

```sh
curl -fSLO https://github.com/yukazakiri/koakademy/releases/latest/download/install.sh
less install.sh
bash install.sh install --domain school.example
```

For a manually managed deployment, use [Getting Started](GETTING_STARTED.md). It covers the supported Docker Compose topology, explicit migrations, S3-compatible storage, and the reverse-proxy requirements.

## Operate It

Run a current stable release in production. Caddy owns ports 80/443; the application, PostgreSQL, Redis, and Gotenberg stay private. Use `sudo koakademy update` for an explicit backed-up release update and `sudo koakademy configure storage|mail|search` for Docker-Secret-backed provider changes.

| Need                                               | Start here                                                                                              |
| -------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| Install, configure, or upgrade a server            | [Getting Started](GETTING_STARTED.md) · [Deployment](DEPLOYMENT.md) · [Configuration](CONFIGURATION.md) |
| Plan backups, HTTPS, storage, or recovery          | [Deployment runbook](DEPLOYMENT.md#backups) · [Self-hosting FAQ](FAQ.md)                                |
| Understand the codebase and run it locally         | [Development](DEVELOPMENT.md) · [Architecture guide](docs/src/content/docs/start-here/architecture.mdx) |
| Configure enrollment policies                      | [Enrollment blueprints](docs/src/content/docs/enrollment-policies/overview.mdx)                         |
| Use the administrator, faculty, or student portals | [Staff user guide](docs/src/content/docs/user-guide/introduction.mdx)                                   |
| Integrate with the supported API                   | [API overview](docs/src/content/docs/api/api-overview.mdx)                                              |

The hosted documentation site, when enabled for the repository, is available at [yukazakiri.github.io/koakademy](https://yukazakiri.github.io/koakademy/).

## Contributing and Security

Contributions are welcome when they are focused, tested, and safe for self-hosting institutions. Start with [CONTRIBUTING.md](CONTRIBUTING.md). To report a vulnerability, use a private GitHub Security Advisory—not a public issue—and follow [SECURITY.md](SECURITY.md).

## License

KoAkademy is licensed under the [GNU AGPL-3.0-or-later](LICENSE.md). If people use a modified version over a network, they must be able to obtain that modified source. The code that handles a school’s records should remain available to the people who depend on it.


