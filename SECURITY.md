# Security Policy

## Reporting a vulnerability

Please do not open a public issue for a suspected vulnerability or include sensitive institutional data in an issue, discussion, or pull request.

Use [GitHub Private Vulnerability Reporting](https://github.com/yukazakiri/koakademy/security/advisories/new) to send a report privately. Include the affected version or commit, a clear reproduction path, impact, and any relevant configuration details. Redact passwords, keys, student data, and production logs.

If private reporting is unavailable, open a GitHub issue containing only a request for a private contact channel; do not describe the vulnerability publicly.

## Supported versions

Only the latest stable KoAkademy release is supported. Beta releases can change operational and compatibility contracts; operators should review release notes, test upgrades in staging, and retain backups before upgrading.

## What to expect

Maintainers will assess private reports and coordinate disclosure or a fix where appropriate. KoAkademy makes no response-time or remediation SLA. Please allow reasonable time for investigation before disclosing a vulnerability publicly.

## Operator baseline

Security is shared with the institution operating the software. Before handling real data, use HTTPS, restrict infrastructure access, keep secrets out of source control, run supported stable releases, patch the host and Docker runtime, and test database and object-storage recovery. See [Deployment](docs/src/content/docs/self-hosting/deployment.mdx) and [Configuration](docs/src/content/docs/self-hosting/configuration.mdx) for the supported production boundary.

## Runtime version floors

The application requires **PHP 8.5.12 or newer** and **Node.js 22.23.4 or newer**. These are security floors rather than compatibility preferences:

- PHP 8.5.12 fixes CVE-2026-109618 and CVE-2026-109619, two critical unauthenticated remote code execution flaws published on 21 October 2026. The PHP project states that upgrading is the only complete mitigation.
- Node.js 22.23.4 fixes CVE-2026-111497, a Brotli decoder out-of-bounds write that permits remote code execution, together with the CVE-2026-106069 and CVE-2026-105482 hostname and DNS resolution flaws.

The published container images pin these versions, so Docker and Swarm deployments only need the current image. Bare-metal deployments must meet the floors directly. The application logs a warning on an unpatched interpreter and refuses to boot in a production environment.
