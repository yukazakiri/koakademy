# Extension integration checks

The existing primary CI workflow now validates installed extension contracts and runs composer test:extensions after the application suite. Delivery invokes that workflow on branch/release pushes; pull requests and manual dispatches use its existing entry points.

The extension suite uses phpunit.extensions.xml, includes the six installed packages' own tests and any remaining bundled tests, enables vendor module scanning, and exercises the development-only Composer package from extensions/starter. It checks discovery from vendor/, permissions without implicit grants, navigation, Filament plugin registration, disabled HTTP/Livewire requests, deferred queue execution, and the starter's tenant-scoped endpoints.

The existing Composer and npm caches are reused. No signing key, deployment credential, license server, or external service is needed for these checks. Tests fake the host release feed.

Run locally:

    composer install
    php artisan modules:validate --all
    composer test:extensions
    node --test tests/Unit/module-page-registry.test.mjs
    npm run build

The starter's own workflow runs on pushes and pull requests in a contributor's repository. It installs that package into a Koakademy host workbench, checks formatting and behavior, and builds browser/SSR assets. Pin the workbench to the first compatible released SDK revision before publishing the template externally.

Registry signing and production publishing remain separate from contributor CI. The module registry documentation explains the review handoff; workflow changes are maintained in the separate registry repository.

The normal application suite also enables vendor scanning, matching production package discovery. The extension suite asserts one Composer owner per extracted feature, provider boot with scanning disabled, migrations, and Filament resource/page registration. Each dedicated package runs its own contract and pinned-host integration/build workflow.
