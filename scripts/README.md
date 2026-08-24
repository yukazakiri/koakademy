# Scripts

Helper scripts used by the repository. Local development and certificate tooling
lives with the Docker Compose development topology and the application docs, not
here; this folder only keeps the scripts the project actually runs or ships.

## Installation

- `install.sh` — small Linux bootstrap that resolves a published stable GitHub
  Release with `--stable`, or the current unreleased `master` commit with
  `edge`, verifies/downloads the matching operator command, and hands control
  to it.
- `koakademy` — privileged production operator for the single-node Docker Swarm
  deployment. It installs, updates, configures, checks status, and rolls back
  the application.
- `swarm-stack.yml`, `Caddyfile`, and `koakademy-app-entrypoint.sh` — release
  assets consumed by the operator when it deploys the Swarm topology.
- `check-release-assets.sh` — validates that a release bundle contains every
  required asset and that `SHA256SUMS` matches the files.

## Maintainers

- `generate-version-metadata.sh` — writes the `version.json` metadata file baked
  into the production image during delivery.
- `check-docs.mjs` — validates canonical documentation files, links, and
  documented API endpoints (`npm run docs:check`).
- `sync-docs.mjs` — copies canonical root markdown into the docs site and in-app
  viewer mirrors (`npm run docs:sync`).
