# Scripts

Helper scripts used by the repository. Local development and certificate tooling
lives with the Docker Compose development topology and the application docs, not
here; this folder only keeps the scripts the project actually runs or ships.

## Installation

- `install.sh` — supported production installer for Linux (Docker Swarm). Runs
  `docker compose` against `compose.production.yaml`, provisions services, runs
  migrations, and prints the one-time `/setup` URL.
- `install.ps1` — Windows PowerShell equivalent of `install.sh`.

## Maintainers

- `generate-version-metadata.sh` — writes the `version.json` metadata file baked
  into the production image during delivery.
- `check-docs.mjs` — validates canonical documentation files, links, and
  documented API endpoints (`npm run docs:check`).
- `sync-docs.mjs` — copies canonical root markdown into the docs site and in-app
  viewer mirrors (`npm run docs:sync`).
