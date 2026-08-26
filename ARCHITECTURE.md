# Architecture

KoAkademy is a modular Laravel monolith packaged as a FrankenPHP image. Inertia React portals, Filament administration, jobs, and scheduled work share the same application and authorization model.

## Supported production runtime

```text
Browser
  -> Caddy HTTPS on ports 80/443
  -> private FrankenPHP application service
       -> PostgreSQL: durable source of record
       -> Redis: sessions, queues, cache
       -> Gotenberg: PDF rendering
       -> persistent app volume or external S3/R2
       -> optional external SMTP/Sequenzy and Meilisearch
```

The one-line installer deploys that topology as a single-node Swarm. Caddy is the only public service. Database, Redis, Gotenberg, and application ports are private to the overlay. Local volumes make this a simple single-node design, not multi-node high availability.

Docker Secrets are the infrastructure credential boundary. The app loads secrets at task start; provider changes redeploy FrankenPHP because persistent workers retain booted configuration. The web application reports mail status but never has Docker socket authority or writes provider credentials.

## Application boundaries

- HTTP/UI translates routes, middleware, Inertia pages, and Filament resources into authorized workflows.
- Application services, actions, jobs, policies, and settings coordinate use cases.
- Eloquent models, migrations, enums, and constraints own durable data.
- Laravel adapters integrate storage, mail, queues, search, PDF rendering, and external APIs.
- Optional modules extend the monolith while sharing authentication and deployment lifecycle.

## Public module distribution

The core repository ships the application and the local module contract. Each
module manifest declares its semantic version, license, providers, core/PHP
requirements, and Laravel/Filament compatibility. The six bundled modules
remain in Modules/ for the core application's backward-compatible defaults;
matching standalone Composer repositories are published for independent use.

The public distribution boundary is intentionally separate:

~~~text
yukazakiri/koakademy                       -> core application and marketplace client
yukazakiri/koakademy-modules               -> signed catalog and Composer repository
yukazakiri/koakademy-module-<name>         -> one standalone Composer package per module
~~~

Registry reads are disabled by default. When enabled, the core accepts only an
HTTPS catalog with an Ed25519 signature and a SHA-256 checksum declaration for
every release asset. Composer remains the installation boundary: a host
explicitly adds a package from the signed catalog's `packages.json`, then
Laravel discovers the package provider and module manifest. The marketplace
client does not execute arbitrary downloaded code.

Production migrations are explicit release jobs. Application startup never migrates the database, and rollback never assumes database migrations can be safely reversed.
