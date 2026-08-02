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

Security is shared with the institution operating the software. Before handling real data, use HTTPS, restrict infrastructure access, keep secrets out of source control, run supported stable releases, patch the host and Docker runtime, and test database and object-storage recovery. See [Deployment](DEPLOYMENT.md) and [Configuration](CONFIGURATION.md) for the supported production boundary.
