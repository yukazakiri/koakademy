# Changelog

## [1.12.0](https://github.com/yukazakiri/koakademy/compare/v1.11.0...v1.12.0) (2026-07-27)


### Features

* **enrollments:** add fee overrides and discount presets ([62e7296](https://github.com/yukazakiri/koakademy/commit/62e72963c95d35319d8eb0e582007426e271337e))


### Miscellaneous Chores

* update version to 1.11.0 [skip ci] ([1d92c4c](https://github.com/yukazakiri/koakademy/commit/1d92c4c9a639676c9c02600c0bc18a51e502c1b2))
* update version to 1.11.0-dev.1.0 [skip ci] ([34d5f7e](https://github.com/yukazakiri/koakademy/commit/34d5f7eb00b060dc0b00f9ced67a27cf34cfbf9f))

## Changelog

Notable project changes are recorded here. KoAkademy follows semantic versioning for stable releases where practical; because the project is beta, documented APIs and operational contracts can still change between releases and will be called out.

## Unreleased

### Added

- One-line Bash and PowerShell installers for a default Docker Swarm deployment
- Runtime stable-tag discovery for KoAkademy and optional local RustFS
- Docker-secret-backed PostgreSQL, Redis, object storage, migration jobs, and first-run health verification
- Supported production Compose topology with KoAkademy, PostgreSQL, Redis, and Gotenberg
- Canonical self-hosting, deployment, configuration, troubleshooting, architecture, FAQ, security, and contribution documentation
- Deterministic root-Markdown-to-MDX synchronization with CI drift checks
- Secret-free CI covering PHP formatting, Pest, frontend and Astro builds, docs checks, Compose validation, and shell syntax
- Production contract tests for host validation, safe environment values, service exposure, setup onboarding, PDF rendering, metadata, and API documentation
- Release Please configuration and contract tests for reviewed stable release pull requests
- AMD64 and ARM64 container build validation, SBOM/provenance generation, and GitHub attestations

### Changed

- Self-hosting now defaults to manager-pinned Swarm services with host ports for KoAkademy and optional RustFS; Compose remains the manual path
- GitHub Pages deployment is paused while the repository is private
- Production onboarding now uses the real `/setup` wizard
- Production migrations are an explicit operator action and are disabled on container startup by default
- Trusted hosts derive from configured application/portal/admin hosts, with optional additional exact hosts
- Production uploads require S3-compatible storage, either external or the optional local RustFS service
- `spatie/laravel-pdf` uses Gotenberg without an unavailable DOMPDF fallback
- Updated the locked Guzzle, passkey, and WebAuthn dependency chain to clear known security advisories
- Package and container metadata consistently identify KoAkademy and AGPL-3.0-or-later
- Public API documentation is limited to the tested settings and student-verification subset
- Delivery now publishes immutable SHA images to GHCR and Docker Hub before promoting `edge` or stable aliases
- Stable GitHub Releases remain draft until containers, manifests, attestations, assets, and checksums succeed
- Production Docker builds use Node.js 22 with `npm ci` and select a checksummed Supercronic binary by target architecture
- Pull requests use Conventional Commit titles as the squash-merge and release-version input

### Removed

- Devcontainer instructions for a `.devcontainer/` implementation that does not exist
- Fictional student CRUD API documentation
- Project-specific credentials and unsafe development values from the production environment example
- Commit-per-prerelease automation, mutable `dev-latest` updates, and bot-authored build-metadata commits

## Released versions

Release-specific notes before this changelog was introduced remain available on [GitHub Releases](https://github.com/yukazakiri/koakademy/releases). They are not reconstructed here because doing so would invent historical detail.
