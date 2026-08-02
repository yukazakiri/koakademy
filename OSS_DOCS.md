# Open-source documentation notes

This file records the documentation boundary for maintainers. It is not an operator guide.

## README checklist

- [x] Explains KoAkademy's self-hosted, institution-owned system-of-record position.
- [x] Shows the core platform with current screenshots.
- [x] Includes the supported Docker Swarm quick start and a manual-installation link.
- [x] States the beta boundary and points to the security policy.
- [x] Links contributors, operators, staff, and integrators to deeper documentation.
- [x] States the AGPL-3.0-or-later license.

## FAQ draft and operator questions

The maintained [FAQ](FAQ.md) should continue to answer the practical adoption questions: supported deployment topology, Docker requirements, storage, PDFs, backups, upgrade channels, and API scope. Add questions only after the answer is supported by code or the documented operational contract.

## Architecture documentation

The README gives a short module map. The detailed architecture belongs in the documentation site: Laravel boundaries in `app/`, Inertia React portals in `resources/js/`, optional modules in `Modules/`, data in PostgreSQL/S3-compatible storage, Redis-backed operational work, and Docker-packaged runtime services. Keep product rationale in the README and implementation detail in the architecture guide.

## License, security, citation, and reproducibility

- The repository declares AGPL-3.0-or-later in `LICENSE.md` and `composer.json`.
- `SECURITY.md` provides private GitHub vulnerability reporting and the supported-version boundary.
- KoAkademy is an application, not a paper, benchmark, or dataset release; citation metadata is not currently applicable.
- Reproducibility means following the documented Docker installation, pinning an exact stable image tag, recording configuration outside source control, and testing database and object-storage restores.

## Intentionally deferred

- A Code of Conduct requires a maintainer decision before one is introduced.
- A public service-level or support policy is not promised.
- The hosted docs deployment remains conditional on the repository's GitHub Pages configuration.
