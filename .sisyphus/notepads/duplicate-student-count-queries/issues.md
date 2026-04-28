## 2026-04-28T00:00:00Z Task: initialization
- Risk: paginator count + shared count duplication on unfiltered index.
- Risk: stale cache semantics if global total sourced only from cache.

## 2026-04-28T00:00:00Z Task: verification
- Sail could not start in this environment because containerd socket access timed out while pulling Meilisearch.
- Verified the feature test file locally with `php artisan test --compact tests/Feature/AdminSidebarCountsTest.php`.

## 2026-04-28T00:00:00Z Task: lazy shared prop
- `vendor/bin/sail` could not be used for verification because the Docker daemon/containerd socket was unavailable.
