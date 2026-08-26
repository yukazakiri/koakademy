# OSS hardening status

## Current stage

Public Composer module distribution and signed registry are published.

## Completed artifacts

- Audited the architecture brief against the active Laravel 13/PHP 8.5
  repository.
- Confirmed existing release delivery, checksummed installer assets, AGPL
  licensing, CI, and six local modules.
- Added typed module manifests, releases, registry entries, and compatibility
  results under app/Modules.
- Added a signed HTTPS registry client, disabled by default.
- Added manifest and registry tests.
- Added public module publishing guidance and a maintained environment table.
- Confirmed the full Pest suite (1,263 tests / 7,574 assertions) under its
  isolated test profile, plus a live Laravel boot check with vendor scanning
  enabled reporting six modules. The application frontend build and Astro docs
  build also pass.
- Confirmed Composer dependency audit, docs validation, and Laravel application
  boot checks pass.
- Corrected stale documentation that described the GitHub Pages workflow as
  disabled; the public-release instructions now match the active workflow.
- Published six standalone public Composer repositories under `yukazakiri` and
  tagged each package at `v1.0.0`.
- Published `yukazakiri/koakademy-modules` with signed `registry.json`, a
  Composer `packages.json` index, GitHub Pages deployment, and a container
  entrypoint that persists Ed25519 keys across restarts.
- Verified the public Composer index resolves `koakademy/announcement:1.0.0`.
- Verified the registry container builds and preserves its generated public key
  across a restart.

## Execution mode

Continuous implementation with explicit Composer installation and a read-only
signed catalog. No web-triggered code installer is enabled.

## Unresolved maintainer decisions

- Decide whether module activation should remain file-backed or move to a
  database activator for multi-instance deployments.
- Review 10 high/low npm audit findings in the docs toolchain; automatic
  remediation currently requires a breaking Astro upgrade and was not applied.
- Repair the existing PHPStan baseline/config paths before treating static
  analysis as a release gate; the configured ignore list references removed
  files and prevents PHPStan from starting.

## Next recommended release work

Configure the production deployment with the published Pages key, or deploy
the registry container and store its generated public key in the application
secret. Resolve the docs npm advisories and repair the existing PHPStan ignore
paths before calling the public release fully green.

## Stop and rollback conditions

- Do not enable registry access without a public key and a signed catalog.
- Do not add a web-triggered installer until asset extraction, dependency
  execution, authorization, audit logging, rollback, and worker restart behavior
  are independently tested.
- Stop if module manifest validation breaks an existing shipped module or if
  registry failures can block normal application boot.
