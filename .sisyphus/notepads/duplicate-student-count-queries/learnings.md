## 2026-04-28T00:00:00Z Task: initialization
- Preserve adminSidebarCounts prop shape.
- Keep stats.total_students global under filters.
- Avoid brittle total query count assertions.

## 2026-04-28T00:00:00Z Task: resolver extraction
- Resolver should cache the full sidebar payload, then override students from request attribute when present.
- Tenant, school-year, and semester inputs must stay in the cache key to preserve existing behavior.

## 2026-04-28T00:00:00Z Task: lazy shared prop
- Sharing `adminSidebarCounts` as a closure keeps the shared prop shape intact while deferring resolution until Inertia reads it.
- Capturing the same `Request` instance lets controller-set attributes become visible later without changing the payload contract.

## 2026-04-28T00:00:00Z Task: warning cleanup
- Use `\\Closure::class` in tests when checking closure instances to avoid an unnecessary import warning.

## 2026-04-28T00:00:00Z Task: paginator total canonicalization
- On the unfiltered students index, the paginator total can serve as the canonical global total and be written to `admin_students_global_total` before the response is rendered.
- The index contract should keep `students.total`, `stats.total_students`, and `adminSidebarCounts.students` aligned on the unfiltered path.
## 2026-04-28
- Filtered `/administrators/students` requests should set `admin_students_global_total` before Inertia shares resolve, so the sidebar count and `stats.total_students` can reuse one explicit global count while `students.total` stays filtered.
- Deterministic filter coverage works well with `type=college` plus a zero-match variant seeded with only non-college students.
