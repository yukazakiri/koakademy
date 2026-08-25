# OSS Documentation Notes

This repository is distributed as a self-hosted application. The public
documentation should help an operator decide whether KoAkademy fits their
institution, install it safely, and find the deeper operational guides without
duplicating those guides in the README.

## README improvement checklist

- [x] Explain the product, its self-hosted purpose, and beta status.
- [x] Provide the supported Linux one-line installer command, domain example,
  and a safe inspection path.
- [x] Identify Docker Compose as the manual deployment fallback.
- [x] Link to installation, deployment, configuration, development, security,
  contribution, and API documentation.
- [ ] Add a maintained compatibility table for Linux distributions, Docker
  Engine versions, and supported architectures.
- [ ] Add a public staging/demo environment only if one is intentionally
  operated and safe for non-production data.

## FAQ draft

### What does the online installer support?

The supported automated path is Linux with Docker Engine and a public domain.
It creates or preserves a single-node Docker Swarm manager, creates an
attachable overlay network, uses Caddy for ports 80 and 443, and keeps the
application dependencies private on the Swarm network.

The stable bootstrap defaults to the latest checksummed GitHub Release. Set
`KOAKADEMY_DOMAIN` for a non-interactive install. The Dokploy installer URL
must not be substituted: it installs Dokploy, not KoAkademy.

The bootstrap can be started by a normal user when `sudo` is available. The
operator self-elevates only for Docker, Swarm, and runtime-file operations,
then adds the invoking user to Docker's `docker` group so later Docker commands
can run without `sudo` after a login refresh. Docker-group membership grants
root-equivalent access to the host.

### Can I install without running the online installer?

Yes. Operators who manage their own reverse proxy and environment can follow
the Docker Compose instructions in `GETTING_STARTED.md` and `DEPLOYMENT.md`.

### What happens if an installer release is incomplete?

The bootstrap and operator verify the checksummed release bundle before the
deployment proceeds. A missing or mismatched asset should fail before services
are changed.

### Is KoAkademy production-ready?

KoAkademy is beta software. Use staging first, configure durable object storage
and backups, and review the security and deployment guidance before handling
institutional data.

### Where should a vulnerability be reported?

Use GitHub Private Vulnerability Reporting as described in `SECURITY.md`; do
not include student data, credentials, or sensitive logs in public issues.

## Architecture recommendations

Keep `ARCHITECTURE.md` as the short system map and the documentation site as
the detailed operator and domain reference. The minimum public architecture
story is:

- Laravel and Filament provide the server-side application and administration
  boundary.
- Inertia, React, and Vite provide the portal interfaces.
- PostgreSQL stores institutional records; Redis handles cache, sessions, and
  queues; Gotenberg handles production PDF rendering.
- The release-backed Swarm operator owns host-level deployment, Docker Secrets,
  release images, migrations, health checks, updates, and application-only
  rollback.
- Docker Compose remains a manual topology for operators who own the reverse
  proxy and environment configuration.

## Licensing, citation, and reproducibility

- The repository and Composer package identify `AGPL-3.0-or-later`; retain the
  existing `LICENSE.md` and contribution notice.
- No paper, benchmark, dataset, or model is being released, so `CITATION.cff`
  is intentionally deferred.
- Reproducible installation is based on an exact stable Git tag, checksummed
  release assets, and immutable container image metadata. Public release checks
  should verify the asset bundle on a clean Linux host before publication.
- Do not publish real institutional data, credentials, production logs, or
  provider secrets in examples or fixtures.

## Intentionally deferred

- Windows and macOS installers.
- A hosted demo or managed SaaS distribution.
- A formal long-term support matrix and response-time promise.
- A Code of Conduct, which remains an explicit maintainer decision documented
  in `CONTRIBUTING.md`.
