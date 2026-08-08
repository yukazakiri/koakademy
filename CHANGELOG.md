# Changelog

## [1.18.0](https://github.com/yukazakiri/koakademy/compare/v1.17.0...v1.18.0) (2026-08-08)


### Features

* **delivery:** publish a FrankenPHP Octane image variant ([#149](https://github.com/yukazakiri/koakademy/issues/149)) ([e07c411](https://github.com/yukazakiri/koakademy/commit/e07c411bca4f2a78372159a93ebf374675bdd482))
* **newsletter:** add configurable marketing providers ([#143](https://github.com/yukazakiri/koakademy/issues/143)) ([2ff1241](https://github.com/yukazakiri/koakademy/commit/2ff12416ac9ab01b27b2716857babecb8422086f))
* **newsletter:** Sequenzy subscription prompt for students and faculty ([#140](https://github.com/yukazakiri/koakademy/issues/140)) ([3c97f18](https://github.com/yukazakiri/koakademy/commit/3c97f18126031721211c5149aacf6417c48f3eb5))
* **newsletter:** surface configuration in administrator settings ([#144](https://github.com/yukazakiri/koakademy/issues/144)) ([23eb1bf](https://github.com/yukazakiri/koakademy/commit/23eb1bf01bfac46e5eb55f095371abf1bb6536c7))


### Bug Fixes

* **delivery:** construct image refs locally ([#153](https://github.com/yukazakiri/koakademy/issues/153)) ([fd0a45a](https://github.com/yukazakiri/koakademy/commit/fd0a45adffca8cb74bd8d21bc41c31f57c483f79))
* **delivery:** construct image refs locally instead of passing through job outputs ([#152](https://github.com/yukazakiri/koakademy/issues/152)) ([db5f6a1](https://github.com/yukazakiri/koakademy/commit/db5f6a147a0230eb1e7bf9a89c66fb2119946ac9))
* **delivery:** separate frankenphp aliases ([1c4ba63](https://github.com/yukazakiri/koakademy/commit/1c4ba63ec65aecb91e1b81ac501dba384f0286bc))
* **deps:** update frontend packages and regenerate lockfile ([cea0549](https://github.com/yukazakiri/koakademy/commit/cea0549eb6af1b330373d520f5cc0ac32bce9d39))
* **docker:** remove COPY of deleted patches directory ([130eccf](https://github.com/yukazakiri/koakademy/commit/130eccf60726195ce0090ac509cb6b20463bbbc0))
* **inertia:** parse initial page payload ([#154](https://github.com/yukazakiri/koakademy/issues/154)) ([a89236e](https://github.com/yukazakiri/koakademy/commit/a89236e377743e2f06258e9cf2c0fd7112f1eb11))
* **newsletter:** expose administrator settings route ([#145](https://github.com/yukazakiri/koakademy/issues/145)) ([95fbbe0](https://github.com/yukazakiri/koakademy/commit/95fbbe0794a8de4a872ad9688e84fe9c34ea3e71))
* **newsletter:** keep student consent prompt available ([#146](https://github.com/yukazakiri/koakademy/issues/146)) ([ac93761](https://github.com/yukazakiri/koakademy/commit/ac93761606f78751f5b285fe60d3fdca44866ca7))


### Performance Improvements

* **docker:** native parallel builds and slimmer container images ([#151](https://github.com/yukazakiri/koakademy/issues/151)) ([2eb00a2](https://github.com/yukazakiri/koakademy/commit/2eb00a28e6ddd6ea72b1a3cbf3477f3a1ac195b7))
* **docker:** trim PHP extensions and fix container boot ([#150](https://github.com/yukazakiri/koakademy/issues/150)) ([79f7761](https://github.com/yukazakiri/koakademy/commit/79f7761f5227d6521ebf2d4690de39cc2aa205d2))


### Build System

* **deps:** bump the docker-base-images group in /docker with 2 updates ([#142](https://github.com/yukazakiri/koakademy/issues/142)) ([4e22755](https://github.com/yukazakiri/koakademy/commit/4e22755e9712d8f3e705184e5b095ee46e081c5b))
* **deps:** bump the github-actions group with 2 updates ([#141](https://github.com/yukazakiri/koakademy/issues/141)) ([b047c2d](https://github.com/yukazakiri/koakademy/commit/b047c2d1fcee070d2cab98f39a214ed13cd0cffa))


### Documentation

* **readme:** add notes from the author ([c2f307d](https://github.com/yukazakiri/koakademy/commit/c2f307d1e85e76b3798a45c1093a36dfa7c60b59))
* **readme:** add notes from the author ([#148](https://github.com/yukazakiri/koakademy/issues/148)) ([e9a02dd](https://github.com/yukazakiri/koakademy/commit/e9a02dd4548cfa9bc3d35f5214962ff246bbe337))
* **readme:** center self-hosted story ([8d63bfd](https://github.com/yukazakiri/koakademy/commit/8d63bfd8c9c53842e28748fe2037a278af201bab))


### Code Refactoring

* **branding:** rebrand DCCP to KoAkademy ([d2f5723](https://github.com/yukazakiri/koakademy/commit/d2f57237c704a5e840f5663b73ccd41dd641899c))
* **queues:** replace Station with Horizon and self-host Pennant Manager ([#147](https://github.com/yukazakiri/koakademy/issues/147)) ([ff087d0](https://github.com/yukazakiri/koakademy/commit/ff087d05dc602ff4a5098974b98e5557f93b6c17))


### Tests

* add newsletter subscription prompt for students and faculty ([#138](https://github.com/yukazakiri/koakademy/issues/138)) ([c3bac3f](https://github.com/yukazakiri/koakademy/commit/c3bac3ffb7b1a261f7787b78cfc5c6095fbf3a1a))

## [1.17.0](https://github.com/yukazakiri/koakademy/compare/v1.16.2...v1.17.0) (2026-08-01)


### Features

* **enrollments:** add resilient realtime assessment exports ([#134](https://github.com/yukazakiri/koakademy/issues/134)) ([3e29818](https://github.com/yukazakiri/koakademy/commit/3e29818786f52ac0b97247534e7f5f1465c52a20))
* **installer:** add repair/heal path for existing deployments ([f4b432f](https://github.com/yukazakiri/koakademy/commit/f4b432f2259b84a30f140030dac812745be81ff0))
* **library:** add filters, pagination, and bulk delete to admin book… ([#129](https://github.com/yukazakiri/koakademy/issues/129)) ([ba83d25](https://github.com/yukazakiri/koakademy/commit/ba83d2545d703a7ef3913bc7b1121d6972b85463))


### Bug Fixes

* **enrollments:** support legacy export IDs and Pusher cluster ([#137](https://github.com/yukazakiri/koakademy/issues/137)) ([89e7c51](https://github.com/yukazakiri/koakademy/commit/89e7c51f3dd22cb79f0680f9e3370471674db0ac))
* **enrollments:** use postgres-safe export locking ([#136](https://github.com/yukazakiri/koakademy/issues/136)) ([55cabf7](https://github.com/yukazakiri/koakademy/commit/55cabf74ec0213af79e7d28913c2e79f5c5dd08d))


### Maintenance

* remove unused files and dev artifacts ([c17229a](https://github.com/yukazakiri/koakademy/commit/c17229a424ddffa4eaa5fbbcb083aacc97343267))

## [1.16.2](https://github.com/yukazakiri/koakademy/compare/v1.16.1...v1.16.2) (2026-07-29)


### Bug Fixes

* **delivery:** publish edge and allow manual releases ([#127](https://github.com/yukazakiri/koakademy/issues/127)) ([88fc7b2](https://github.com/yukazakiri/koakademy/commit/88fc7b2922c3a5ec27a23dbdb5576f2469f6da37))


### Continuous Integration

* **actions:** streamline validation and edge delivery ([#126](https://github.com/yukazakiri/koakademy/issues/126)) ([1b12efb](https://github.com/yukazakiri/koakademy/commit/1b12efb9f937311500f45463194b42145024a5d1))

## [1.16.1](https://github.com/yukazakiri/koakademy/compare/v1.16.0...v1.16.1) (2026-07-29)


### Bug Fixes

* **enrollments:** restore analytics loading icon import ([#124](https://github.com/yukazakiri/koakademy/issues/124)) ([fb673a5](https://github.com/yukazakiri/koakademy/commit/fb673a54cfad134c418efc381dc1126efc8acebd))

## [1.16.0](https://github.com/yukazakiri/koakademy/compare/v1.15.1...v1.16.0) (2026-07-29)


### Features

* **enrollments:** add course filter and reliable assessment exports ([#122](https://github.com/yukazakiri/koakademy/issues/122)) ([2e6b63c](https://github.com/yukazakiri/koakademy/commit/2e6b63c1286e8ad385bfaf6ce3cf293cd5461173))

## [1.15.1](https://github.com/yukazakiri/koakademy/compare/v1.15.0...v1.15.1) (2026-07-29)


### Bug Fixes

* **enrollment:** repair cashier ledger schema ([#120](https://github.com/yukazakiri/koakademy/issues/120)) ([1313072](https://github.com/yukazakiri/koakademy/commit/1313072cb68dd68e285cea1e079d1df085eea865))

## [1.15.0](https://github.com/yukazakiri/koakademy/compare/v1.14.0...v1.15.0) (2026-07-29)


### Features

* **finance:** deliver official student eReceipts and eInvoices ([#118](https://github.com/yukazakiri/koakademy/issues/118)) ([f16a540](https://github.com/yukazakiri/koakademy/commit/f16a5408003a92e2120367e264df91c5a9f38149))

## [1.14.0](https://github.com/yukazakiri/koakademy/compare/v1.13.1...v1.14.0) (2026-07-29)


### Features

* **notifications:** add personal notification inbox ([#116](https://github.com/yukazakiri/koakademy/issues/116)) ([1aac6a3](https://github.com/yukazakiri/koakademy/commit/1aac6a3290a6c15c6bf4818f9a53f06f79bcc969))

## [1.13.1](https://github.com/yukazakiri/koakademy/compare/v1.13.0...v1.13.1) (2026-07-29)


### Bug Fixes

* **finance:** restore receive payment student search ([#114](https://github.com/yukazakiri/koakademy/issues/114)) ([755f55c](https://github.com/yukazakiri/koakademy/commit/755f55c8d76fc9b630a581d6d0dd3bbb0c0aa2bd))

## [1.13.0](https://github.com/yukazakiri/koakademy/compare/v1.12.1...v1.13.0) (2026-07-29)


### Features

* **enrollment:** make enrollment workflows policy-driven ([#111](https://github.com/yukazakiri/koakademy/issues/111)) ([84a742c](https://github.com/yukazakiri/koakademy/commit/84a742cd9b26ef17c70ec175559e15016e05e268))

## [1.12.1](https://github.com/yukazakiri/koakademy/compare/v1.12.0...v1.12.1) (2026-07-27)


### Bug Fixes

* **release:** allow recovery publication ([ee250e6](https://github.com/yukazakiri/koakademy/commit/ee250e6ddb964eb69252edb53e26bc09a39a5232))
* **release:** tolerate cache export failures ([aef0679](https://github.com/yukazakiri/koakademy/commit/aef067959867558f834b4a1fda9a7f18127787f2))


### Miscellaneous Chores

* added Agent Skills ([7cd8041](https://github.com/yukazakiri/koakademy/commit/7cd804157095576c66569626e535bd76bb51d02e))
* Codex/fix release version contract ([#109](https://github.com/yukazakiri/koakademy/issues/109)) ([2f9f0f9](https://github.com/yukazakiri/koakademy/commit/2f9f0f903a832820809f25b276acf7f54f28647a))
* refactored by rector ([1a082e2](https://github.com/yukazakiri/koakademy/commit/1a082e2eab4bbc08d89493ebed3531451dcf176d))
* update readme ([42e3f6a](https://github.com/yukazakiri/koakademy/commit/42e3f6af8137f8ffe89d0abaa3b67555b7297687))

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
