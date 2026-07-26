# Open-Source CI Contract

The primary workflow is [`.github/workflows/ci.yml`](.github/workflows/ci.yml). It runs for pull requests into `master`, pushes to `master`, merge-queue checks, and manual dispatches. Feature-branch pushes do not create a second run when a pull request already exists.

CI is secret-free and fork-safe. Every third-party action is pinned to a full commit SHA, checkout credentials are not persisted, jobs have explicit timeouts and least-privilege permissions, and untrusted event values enter shell steps only through environment variables.

## Required check

Branch protection requires the single stable check name `CI / required`. It aggregates these jobs:

1. **Application and documentation** — PHP 8.5, Node.js 22, locked Composer audit, Pint, frontend build, parallel Pest, generated-doc and local-link checks, Astro build, production Compose validation, and Bash/ShellCheck/PowerShell syntax checks.
2. **Container (amd64/arm64)** — parallel Buildx validation of `docker/Dockerfile` for both supported Linux architectures with `push: false`.
3. **Workflow security** — checks every workflow with checksum-pinned `actionlint` and `zizmor`.
4. **Conventional PR title** — requires a Conventional Commit title such as `feat(enrollments): add fee overrides`. It is skipped for trusted push, merge-queue, and manual runs.

Composer downloads, both npm lockfiles, and architecture-specific BuildKit layers participate in dependency caching. The aggregator fails if any applicable job fails or is cancelled.

## Local reproduction

Install the toolchain from [DEVELOPMENT.md](DEVELOPMENT.md), then run:

```sh
composer install
npm ci
npm --prefix docs ci
composer audit --locked
vendor/bin/pint --test
npm run build
php artisan test --parallel --compact
npm run docs:check
npm --prefix docs run build
KOAKADEMY_ENV_FILE=.env.production.example \
  docker compose --env-file .env.production.example -f compose.production.yaml config --quiet
bash -n scripts/install.sh scripts/generate-version-metadata.sh
shellcheck scripts/install.sh scripts/generate-version-metadata.sh \
  tests/Fixtures/installer/docker tests/Fixtures/installer/curl
docker buildx build --platform linux/amd64 --file docker/Dockerfile .
docker buildx build --platform linux/arm64 --file docker/Dockerfile .
```

The workflow also parses `scripts/install.ps1` with PowerShell's AST parser. Installer tests use a fake Docker CLI, so CI never creates or mutates a real Swarm.

Repository-wide Prettier, PHPStan, strict Composer validation, and npm audit are tracked quality follow-ups but are not required checks until their existing independent findings are resolved.

## Delivery boundary

CI never publishes packages or accesses registry credentials. [`.github/workflows/delivery.yml`](.github/workflows/delivery.yml) runs only after a successful same-repository `push` CI run on `master`, or as a maintainer-requested recovery for an existing strict stable tag. See the [release runbook](docs/src/content/docs/maintainers/releases.mdx) for channels, credentials, promotion order, and rollback.
