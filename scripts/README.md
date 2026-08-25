# Scripts

Helper scripts used by the repository. Local development and certificate tooling
lives with the Docker Compose development topology and the application docs, not
here; this folder only keeps the scripts the project actually runs or ships.

## Installation

- `install.sh` — small Linux bootstrap that resolves the latest published
  stable GitHub Release by default, or the current unreleased `master` commit
  with explicit `edge`, verifies/downloads the matching operator command, and
  hands control to it. It can be started by a normal user when `sudo` is
  available; stable installs accept `KOAKADEMY_DOMAIN` or `--domain`.
- `koakademy` — production operator for the single-node Docker Swarm
  deployment. It self-elevates for root-owned operations, installs Docker when
  needed, adds the invoking user to Docker's `docker` group, and installs,
  updates, configures, checks status, and rolls back the application.
- `swarm-stack.yml`, `swarm-stack-direct.yml`, `Caddyfile`, and
  `koakademy-app-entrypoint.sh` — release assets consumed by the operator when
  it deploys the domain-backed or direct-port Swarm topology.
- `check-release-assets.sh` — validates that a release bundle contains every
  required asset and that `SHA256SUMS` matches the files.

## Maintainers

- `generate-version-metadata.sh` — writes the `version.json` metadata file baked
  into the production image during delivery.
- `check-docs.mjs` — validates canonical documentation files, links, and
  documented API endpoints (`npm run docs:check`).
- `sync-docs.mjs` — copies canonical root markdown into the docs site and in-app
  viewer mirrors (`npm run docs:sync`).
