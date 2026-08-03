# Architecture

KoAkademy is a modular Laravel monolith. The production application is one deployable image with background workers and a scheduler managed alongside the HTTP server.

## Runtime view

```text
Browser
  -> operator-managed HTTPS edge
  -> Swarm manager host port 8000
  -> KoAkademy (Laravel + FrankenPHP + workers + scheduler)
       -> PostgreSQL (source of record)
       -> Redis (cache, sessions, queues)
       -> Gotenberg (PDF rendering)
       -> local RustFS + persistent volume
          or external S3-compatible storage (uploads)
       -> optional operator-configured mail and observability providers
```

The default installer creates manager-pinned Docker Swarm services on a private overlay. PostgreSQL, Redis, Gotenberg, and the RustFS console are not published; only KoAkademy and, when selected, the RustFS S3 API receive host ports. Pinning keeps local volumes attached to one node, so this is not a multi-node high-availability design.

The manual `compose.production.yaml` topology binds KoAkademy to `127.0.0.1:8000` and uses external S3-compatible storage. In both topologies the HTTPS edge remains operator-managed so Caddy, Nginx, Traefik, or an existing tunnel/certificate platform can be used.

## Application layers

- **HTTP and UI:** Laravel routes and middleware, Filament administration, and Inertia React portal pages
- **Application workflows:** services, actions, jobs, policies, and settings objects coordinate use cases
- **Domain/data:** Eloquent models, enums, relationships, validation, and database constraints
- **Infrastructure adapters:** filesystems, mail, queues, broadcasting, search, PDF rendering, and integrations
- **Optional modules:** packages under `Modules/` extend the monolith while sharing its authentication, database, and deployment lifecycle

Dependencies should point inward: HTTP handlers translate requests into application workflows; reusable domain work should not depend on a controller or React page. Infrastructure details belong behind Laravel contracts or focused services where feasible.

## Request and tenancy context

Web requests pass through setup enforcement, Inertia handling, online tracking, and tenant context middleware. Host-header validation accepts exact hosts derived from `APP_URL`, `PORTAL_HOST`, `ADMIN_HOST`, and optional `TRUSTED_HOSTS`. Forwarded headers are interpreted only according to `TRUSTED_PROXIES`.

KoAkademy contains organization and school context, but operators should not infer a compliance-grade isolation guarantee. Review authorization, storage paths, exports, and integrations for the intended deployment and data-governance model.

## Authentication and authorization

Browser authentication uses Laravel's web guard. API authentication uses Sanctum where routes declare `auth:sanctum`. Application roles, policies, middleware, and Filament Shield permissions constrain actions. Route authentication alone is not a substitute for object-level authorization.

The initial super administrator and institution are created only through `/setup`. Subsequent identity lifecycle and role assignment occur through the running application.

## Data and state

- PostgreSQL is the durable relational source of record in the production image.
- S3-compatible storage is the durable upload store. Local RustFS is convenient but only as durable as its single host and backups.
- Redis contains disposable operational state and queued work; it is not the backup source of truth.
- Container-local `storage/` holds runtime files and temporary artifacts, not supported durable uploads.

Schema changes use versioned Laravel migrations. Production startup does not automatically migrate; install and upgrade workflows run migrations explicitly.

## Asynchronous work

Exports, notifications, PDFs, and other expensive operations should use queues. Redis backs queues and Station manages worker processes in the production image. Jobs must be retry-safe where possible and should not expose sensitive payloads in logs or failure UIs.

## PDF boundary

The application depends on `spatie/laravel-pdf`; the supported renderer is the private Gotenberg service. No DOMPDF fallback is installed. This makes renderer failure visible instead of silently changing document fidelity or capabilities.

## Documentation architecture

Technical/project docs are authored once at the repository root and generated into marked MDX mirrors. Astro and the in-app reader consume the same mirrors. Operator guides, enrollment blueprints, and the deliberately small tested API contract stay native to Astro content.

## Extension guidance

Prefer core services and existing module boundaries over new cross-module coupling. A module should declare KoAkademy/AGPL metadata, own its migrations and tests, and avoid assuming its optional feature is always enabled. New external services require configuration, failure behavior, data-flow documentation, and a deployment decision.
