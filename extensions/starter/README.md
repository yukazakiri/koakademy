# Koakademy extension starter

This working community package demonstrates the current Koakademy extension contract. It supplies a school-scoped notes page, validation and policies, a migration, explicit permissions, a Filament plugin, a Livewire component, and a queued job. It requires no commercial license.

The application uses this directory as a development-only Composer path package. Production installations with --no-dev omit it.

From the Koakademy checkout:

    composer install
    php artisan modules:validate --all
    composer test:extensions
    php artisan wayfinder:generate
    npm run build

For an interactive development installation, set MODULE_MARKETPLACE_ENABLED=true to manage module switches (the registry URL may remain blank), run migrations, run modules:sync-permissions, enable SchoolNotes in Extensions & licenses, and explicitly assign school-notes.view and school-notes.create to the intended staff. Permission synchronization never grants permissions by itself.

To create your own extension, copy this directory to a separate repository and change:

- Composer name, PHP namespace, module name, alias, and provider metadata.
- Permission names, table names, route names, page name, and navigation configuration.
- The generated Wayfinder controller import after regenerating host route helpers.
- Package version, core requirements, author, repository and homepage in module.json before requesting a catalog listing.

Keep the Composer package identity independent of the module name; the host uses discovered paths rather than guessing directory names.

The included tests run in a real Koakademy workbench using its existing Pest dependencies and Tests\TestCase. They verify authorization, validation, tenant isolation, protected writes, and disabled routes. The host additionally tests package discovery, Filament registration, cached navigation, Livewire updates, and queued work.

Copy .github/workflows/ci.yml into the new repository. After this SDK is released, pin its host checkout to a tested core tag or commit. The workflow cannot run against older core revisions that do not contain the SDK and phpunit.extensions.xml.

Frontend imports use the host's existing UI and npm dependencies. A package-local package.json does not install dependencies into the host; additional frontend dependencies need an explicit host lockfile change and a tested image build.

Class-based jobs, queued listeners, mailables, and notifications are checked by the host's queue handler. Disabled asynchronous work is released for 60 seconds and remains subject to the job's normal attempt/deadline limits. Synchronous queued work throws instead of silently disappearing. Scheduled commands and anonymous queued closures must explicitly check ModuleAccessService before performing extension work.

Registration remains independent of access. The provider always loads installed routes and migrations; ensure.module checks availability on requests, while policies and tenant checks decide who may access school data.

See the [module authoring guide](../../docs/src/content/docs/maintainers/create-module.mdx) for publication, migration, and deployment.
