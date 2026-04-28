# Eliminate Duplicate Student Count Queries on the Administrator Students Index

## TL;DR
> **Summary**: Remove the duplicate `students` aggregate query on the administrator students Inertia index by making the page compute one canonical global student total per request and letting the shared sidebar prop consume that value lazily on this route.
> **Deliverables**:
> - Reusable administrator sidebar-count support class with route-aware student-count override support
> - Lazy `adminSidebarCounts` shared prop flow in `HandleInertiaRequests`
> - Controller refactor for a canonical global student total on the students index
> - Dedicated Pest coverage for unfiltered behavior, filtered behavior, and SQL-capture regression
> **Effort**: Short
> **Parallel**: YES - 2 waves
> **Critical Path**: Task 1 → Task 2 → Task 3 → Task 4 → Task 5 → Task 6

## Context
### Original Request
Fix the duplicate queries reported from `HandleInertiaRequests.php#118` and `AdministratorStudentManagementController.php#178`.

### Interview Summary
- Keep the students-page stat as the **global** student total even when filters are active.
- Limit scope to the **administrator students page path only**.
- Require an automated **regression test** that catches the duplicate-query issue returning.

### Metis Review (gaps addressed)
- Do **not** rely on reusing cached sidebar data directly for the page stat, because that would leave the paginator-vs-sidebar duplication untouched on unfiltered loads.
- Preserve `LengthAwarePaginator`; do **not** switch to `simplePaginate()` or otherwise change table pagination semantics.
- Keep query regression assertions narrow: target `students` aggregate SQL only, flush cache before capture, and avoid brittle total-query-count assertions.
- Treat both unfiltered and filtered `GET /administrators/students` requests as in scope for verification.

## Work Objectives
### Core Objective
Ensure the administrator students index performs only the necessary aggregate queries against `students` while preserving the current UI contract: the list paginator stays accurate, the `stats.total_students` card remains global, and `adminSidebarCounts.students` remains present.

### Deliverables
- New support class under `app/Support/` that owns administrator sidebar count resolution and allows a request-scoped student-total override.
- Refactored `app/Http/Middleware/HandleInertiaRequests.php` sharing `adminSidebarCounts` lazily instead of eagerly counting students before the controller runs.
- Refactored `AdministratorStudentManagementController` index flow that computes a single canonical global student total per request and stores it for both page stats and shared props.
- Updated `tests/Feature/AdminSidebarCountsTest.php` for the lazy shared-prop shape.
- New `tests/Feature/AdministratorStudentIndexCountsTest.php` covering unfiltered totals, filtered totals, and SQL-capture regression.

### Definition of Done (verifiable conditions with commands)
- `vendor/bin/sail artisan test --compact tests/Feature/AdminSidebarCountsTest.php` passes.
- `vendor/bin/sail artisan test --compact tests/Feature/AdministratorStudentIndexCountsTest.php` passes.
- `vendor/bin/sail bin pint --dirty --format agent` completes without further changes.
- The unfiltered index request emits only one unfiltered `students` aggregate query during the captured request.
- The filtered index request emits one filtered paginator aggregate plus one global `students` aggregate, with no duplicate global count.

### Must Have
- Preserve the `adminSidebarCounts` prop name and array shape.
- Preserve `stats.total_students` as a global system-wide total under both filtered and unfiltered index requests.
- Preserve existing `/administrators/students` paginator behavior and response component name.
- Reuse existing test conventions: `portalUrlForAdministrators('/administrators/students')`, `AssertableInertia`, and `withoutVite()` / `inertia.testing.ensure_pages_exist` setup.

### Must NOT Have (guardrails, AI slop patterns, scope boundaries)
- Must **not** broaden into a full sidebar-count refactor for unrelated pages.
- Must **not** change sidebar cache TTL semantics for unrelated routes.
- Must **not** replace `paginate()` with `simplePaginate()` or `cursorPaginate()`.
- Must **not** add manual-only verification or rely on ad hoc scripts outside the test suite.
- Must **not** use a brittle “total SQL queries for the whole request” assertion.

## Verification Strategy
> ZERO HUMAN INTERVENTION - all verification is agent-executed.
- Test decision: tests-after + Pest feature coverage
- QA policy: Every task includes agent-executed scenarios and produces evidence files
- Evidence: `.sisyphus/evidence/task-{N}-{slug}.{ext}`

## Execution Strategy
### Parallel Execution Waves
> Target: 5-8 tasks per wave. <3 per wave (except final) = under-splitting.
> Extract shared dependencies as Wave-1 tasks for max parallelism.

Wave 1: foundation + shared-prop plumbing + test harness (`quick`, `unspecified-low`)
- Task 1: add reusable administrator sidebar-count resolver
- Task 2: switch middleware to lazy shared sidebar counts
- Task 3: add dedicated administrator student index counts test harness

Wave 2: controller canonicalization + behavior/regression coverage (`quick`)
- Task 4: refactor controller to compute one canonical global total per request
- Task 5: lock behavior for unfiltered and filtered index totals
- Task 6: add SQL-capture regression and run targeted quality gates

### Dependency Matrix (full, all tasks)
- Task 1 → Blocks Tasks 2, 3, 4, 5, and 6
- Task 2 → Blocks Tasks 3, 4, 5, and 6
- Task 3 → Blocks Tasks 4, 5, and 6
- Task 4 → Blocks Tasks 5 and 6
- Task 5 → Blocks Task 6
- Task 6 → Blocks Final Verification Wave

### Agent Dispatch Summary (wave → task count → categories)
- Wave 1 → 3 tasks → `quick`, `unspecified-low`
- Wave 2 → 3 tasks → `quick`
- Final Verification Wave → 4 tasks → `oracle`, `unspecified-high`, `deep`

## TODOs

- [x] 1. Introduce a reusable administrator sidebar-count resolver

  **What to do**: Create `app/Support/AdministratorSidebarCounts.php` as the single backend source for administrator sidebar counts. Make it an injectable `final` class with explicit methods for resolving counts from a `Request`, preserving the outward array shape `['students', 'enrollments', 'faculties', 'users']`. Split the current single cached array strategy so `students` can be satisfied from a request attribute named `admin_students_global_total` when present, while non-student counts keep their current cached behavior keyed by school/year/semester. Keep a cached fallback student count for non-students-index routes only.
  **Must NOT do**: Do not use static mutable state, do not change cache TTLs, and do not fold unrelated dashboard analytics into this class.

  **Recommended Agent Profile**:
  - Category: `quick` - Reason: focused Laravel support-class extraction with narrow blast radius.
  - Skills: [`laravel-best-practices`, `pest-testing`] - PHP architecture consistency plus Pest coverage for the new resolver contract.
  - Omitted: [`inertia-react-development`, `wayfinder-development`] - no client-side or route-generation work is required.

  **Parallelization**: Can Parallel: NO | Wave 1 | Blocks: [2, 3, 4, 5, 6] | Blocked By: []

  **References** (executor has NO interview context - be exhaustive):
  - Pattern: `app/Support/AdministratorPortalData.php:19-128` - existing `app/Support` aggregation style and single-source count logic.
  - Pattern: `app/Http/Middleware/HandleInertiaRequests.php:101-125` - current sidebar-count fields, cache key inputs, and array shape that must be preserved.
  - Test: `tests/Feature/AdminSidebarCountsTest.php:17-55` - current expectations for sidebar count values.

  **Acceptance Criteria** (agent-executable only):
  - [ ] `app/Support/AdministratorSidebarCounts.php` exists and exposes request-based sidebar count resolution without static state.
  - [ ] `vendor/bin/sail artisan test --compact tests/Feature/AdminSidebarCountsTest.php --filter="resolves administrator sidebar counts"` passes.
  - [ ] `vendor/bin/sail artisan test --compact tests/Feature/AdminSidebarCountsTest.php --filter="returns null sidebar counts for non-admin users"` passes.

  **QA Scenarios** (MANDATORY - task incomplete without these):
  ```
  Scenario: Resolver returns the expected sidebar-count shape for admin users
    Tool: Bash
    Steps: Run `vendor/bin/sail artisan test --compact tests/Feature/AdminSidebarCountsTest.php --filter="resolves administrator sidebar counts"`
    Expected: Test passes and proves the resolver returns `students`, `enrollments`, `faculties`, and `users` counts for an admin request.
    Evidence: .sisyphus/evidence/task-1-sidebar-resolver.txt

  Scenario: Resolver safely returns null for non-admin users
    Tool: Bash
    Steps: Run `vendor/bin/sail artisan test --compact tests/Feature/AdminSidebarCountsTest.php --filter="returns null sidebar counts for non-admin users"`
    Expected: Test passes and proves no sidebar counts are resolved for a non-admin request.
    Evidence: .sisyphus/evidence/task-1-sidebar-resolver-non-admin.txt
  ```

  **Commit**: NO | Message: `fix(admin-students): remove duplicate student count query` | Files: [`app/Support/AdministratorSidebarCounts.php`, `tests/Feature/AdminSidebarCountsTest.php`]

- [x] 2. Switch `HandleInertiaRequests` to lazy shared sidebar counts

  **What to do**: Refactor `app/Http/Middleware/HandleInertiaRequests.php` so `adminSidebarCounts` is shared lazily via closure instead of eagerly counting during `share()`. Delegate all sidebar count resolution to `AdministratorSidebarCounts`. Preserve the top-level shared prop key as `adminSidebarCounts`, and make sure the lazy closure resolves against the same `Request` instance so route-scoped overrides written later by the controller are visible when the prop is finally evaluated.
  **Must NOT do**: Do not leave `Student::query()->count()` inside `share()` or any eager pre-controller path, and do not rename or nest the shared prop differently.

  **Recommended Agent Profile**:
  - Category: `quick` - Reason: one middleware file plus one existing test file.
  - Skills: [`laravel-best-practices`, `pest-testing`] - middleware refactor plus regression updates.
  - Omitted: [`inertia-react-development`] - shared-prop evaluation is backend-only here.

  **Parallelization**: Can Parallel: NO | Wave 1 | Blocks: [3, 4, 5, 6] | Blocked By: [1]

  **References** (executor has NO interview context - be exhaustive):
  - Pattern: `app/Http/Middleware/HandleInertiaRequests.php:101-125` - current eager implementation to replace.
  - API/Type: `vendor/inertiajs/inertia-laravel/src/Middleware.php:100-123` - `share()` is registered before the controller runs, so lazy closure resolution is required for route-specific overrides.
  - External: `https://inertiajs.com/docs/v2/data-props/shared-data` - official Inertia guidance for lazy shared-data closures.
  - Test: `tests/Feature/AdminSidebarCountsTest.php:17-55` - existing direct middleware-share coverage that must be updated for the lazy prop shape.

  **Acceptance Criteria** (agent-executable only):
  - [ ] `HandleInertiaRequests::share()` no longer performs an eager student count before controller execution.
  - [ ] `vendor/bin/sail artisan test --compact tests/Feature/AdminSidebarCountsTest.php --filter="shares admin sidebar counts"` passes.
  - [ ] `vendor/bin/sail artisan test --compact tests/Feature/AdminSidebarCountsTest.php --filter="uses a lazy shared sidebar counts prop"` passes.

  **QA Scenarios** (MANDATORY - task incomplete without these):
  ```
  Scenario: Middleware still resolves admin sidebar counts correctly
    Tool: Bash
    Steps: Run `vendor/bin/sail artisan test --compact tests/Feature/AdminSidebarCountsTest.php --filter="shares admin sidebar counts"`
    Expected: Test passes and proves the final resolved `adminSidebarCounts` payload still matches expected values.
    Evidence: .sisyphus/evidence/task-2-middleware-shared-counts.txt

  Scenario: Shared prop is now lazy instead of eagerly counted
    Tool: Bash
    Steps: Run `vendor/bin/sail artisan test --compact tests/Feature/AdminSidebarCountsTest.php --filter="uses a lazy shared sidebar counts prop"`
    Expected: Test passes and proves the middleware returns a lazily-resolved shared prop rather than an already-materialized array.
    Evidence: .sisyphus/evidence/task-2-middleware-lazy.txt
  ```

  **Commit**: NO | Message: `fix(admin-students): remove duplicate student count query` | Files: [`app/Http/Middleware/HandleInertiaRequests.php`, `tests/Feature/AdminSidebarCountsTest.php`]

- [x] 3. Use paginator totals as the canonical global total on unfiltered index requests

  **What to do**: Add `tests/Feature/AdministratorStudentIndexCountsTest.php` using the existing index-route conventions (`withoutVite()`, `config(['inertia.testing.ensure_pages_exist' => false])`, `portalUrlForAdministrators('/administrators/students')`). Refactor the students index path in `AdministratorStudentManagementController` so unfiltered requests compute one canonical global student total from `$students->total()`, write it to the request attribute `admin_students_global_total`, and use the same value for `stats.total_students`.
  **Must NOT do**: Do not add a second explicit `Student::count()` for unfiltered requests, and do not change the page component, per-page options, or sorting/filter semantics.

  **Recommended Agent Profile**:
  - Category: `quick` - Reason: localized controller change plus a new focused feature test file.
  - Skills: [`laravel-best-practices`, `pest-testing`] - controller logic and deterministic Pest assertions.
  - Omitted: [`wayfinder-development`, `inertia-react-development`] - no frontend route wiring changes are needed.

  **Parallelization**: Can Parallel: NO | Wave 1 | Blocks: [4, 5, 6] | Blocked By: [1, 2]

  **References** (executor has NO interview context - be exhaustive):
  - Pattern: `app/Http/Controllers/AdministratorStudentManagementController.php:173-189` - paginator creation and filter detection.
  - Pattern: `app/Http/Controllers/AdministratorStudentManagementController.php:261-289` - current `stats.total_students` logic and Inertia response payload.
  - Test: `tests/Feature/AdministratorStudentIndexClearanceTest.php:18-21` - index-test setup conventions.
  - Test: `tests/Feature/AdministratorStudentIndexClearanceTest.php:60-69` - request path + Inertia component assertion pattern for the students index.
  - Test: `tests/Feature/AdministratorStudentManagementTest.php:13-18` - `AssertableInertia` import pattern already used in the suite.

  **Acceptance Criteria** (agent-executable only):
  - [ ] `tests/Feature/AdministratorStudentIndexCountsTest.php` exists.
  - [ ] `vendor/bin/sail artisan test --compact tests/Feature/AdministratorStudentIndexCountsTest.php --filter="uses the paginator total as the global student total on the unfiltered students index"` passes.
  - [ ] The unfiltered test proves `stats.total_students`, `students.total`, and `adminSidebarCounts.students` are identical on the index route.

  **QA Scenarios** (MANDATORY - task incomplete without these):
  ```
  Scenario: Unfiltered index request reuses the paginator total for global totals
    Tool: Bash
    Steps: Run `vendor/bin/sail artisan test --compact tests/Feature/AdministratorStudentIndexCountsTest.php --filter="uses the paginator total as the global student total on the unfiltered students index"`
    Expected: Test passes and proves the unfiltered page returns the same global student total in `stats.total_students`, `students.total`, and `adminSidebarCounts.students`.
    Evidence: .sisyphus/evidence/task-3-unfiltered-index.txt

  Scenario: Unfiltered index request preserves existing response contract
    Tool: Bash
    Steps: Run `vendor/bin/sail artisan test --compact tests/Feature/AdministratorStudentIndexCountsTest.php --filter="preserves the administrators students index inertia contract"`
    Expected: Test passes and proves the response component remains `administrators/students/index` with working paginator data.
    Evidence: .sisyphus/evidence/task-3-index-contract.txt
  ```

  **Commit**: NO | Message: `fix(admin-students): remove duplicate student count query` | Files: [`app/Http/Controllers/AdministratorStudentManagementController.php`, `tests/Feature/AdministratorStudentIndexCountsTest.php`]

- [ ] 4. Reuse one explicit global count when filters are active

  **What to do**: Extend the same controller path so filtered index requests compute exactly one explicit global `Student::query()->count()`, store it in `admin_students_global_total`, and reuse that same value for `stats.total_students`. Keep the paginator’s own total untouched so `students.total` continues reflecting the filtered result set. Use a deterministic filter such as `?type=college` in the new feature test so the filtered total is provably smaller than the global total.
  **Must NOT do**: Do not let the filtered path fall back to the sidebar cache, and do not overwrite `students.total` with the global count.

  **Recommended Agent Profile**:
  - Category: `quick` - Reason: a narrow follow-up controller change plus one focused feature test.
  - Skills: [`laravel-best-practices`, `pest-testing`] - filtered-count semantics and Pest assertions.
  - Omitted: [`inertia-react-development`] - no client code changes are needed.

  **Parallelization**: Can Parallel: NO | Wave 2 | Blocks: [5, 6] | Blocked By: [1, 2, 3]

  **References** (executor has NO interview context - be exhaustive):
  - Pattern: `app/Http/Controllers/AdministratorStudentManagementController.php:181-189` - current active-filter detection.
  - Pattern: `app/Http/Controllers/AdministratorStudentManagementController.php:261-278` - current filtered/unfiltered branch for `stats.total_students`.
  - Test: `tests/Feature/AdministratorStudentIndexClearanceTest.php:138-146` - index route assertions already proving list payload details.
  - Test: `tests/Feature/AdministratorStudentIndexCountsTest.php` - extend the file created in Task 3 rather than opening a second index-count test file.

  **Acceptance Criteria** (agent-executable only):
  - [ ] `vendor/bin/sail artisan test --compact tests/Feature/AdministratorStudentIndexCountsTest.php --filter="keeps the global student total when students index filters are active"` passes.
  - [ ] The filtered-path test proves `stats.total_students` equals the global count while `students.total` equals the filtered count.
  - [ ] `adminSidebarCounts.students` matches the global count on the filtered index response.

  **QA Scenarios** (MANDATORY - task incomplete without these):
  ```
  Scenario: Filtered index keeps a global stat and a filtered paginator total
    Tool: Bash
    Steps: Run `vendor/bin/sail artisan test --compact tests/Feature/AdministratorStudentIndexCountsTest.php --filter="keeps the global student total when students index filters are active"`
    Expected: Test passes and proves `stats.total_students` / `adminSidebarCounts.students` remain global while `students.total` reflects the `type=college` filter.
    Evidence: .sisyphus/evidence/task-4-filtered-index.txt

  Scenario: Filtered request handles zero matching records without changing the global total
    Tool: Bash
    Steps: Run `vendor/bin/sail artisan test --compact tests/Feature/AdministratorStudentIndexCountsTest.php --filter="keeps the global student total when filters return no matching students"`
    Expected: Test passes and proves `students.total` can be `0` while the global stat and sidebar count stay at the seeded system total.
    Evidence: .sisyphus/evidence/task-4-filtered-zero-results.txt
  ```

  **Commit**: NO | Message: `fix(admin-students): remove duplicate student count query` | Files: [`app/Http/Controllers/AdministratorStudentManagementController.php`, `tests/Feature/AdministratorStudentIndexCountsTest.php`]

- [ ] 5. Add SQL-capture regression coverage for duplicate count prevention

  **What to do**: In `tests/Feature/AdministratorStudentIndexCountsTest.php`, capture executed SQL with `DB::listen` or equivalent request-scoped query collection, flushing `Cache` before each request. Normalize SQL strings and assert narrowly on `students` aggregate queries only. For the unfiltered index request, assert exactly one unfiltered `select count(*) as aggregate from "students" ...` query occurs. For the filtered request (`type=college`), assert exactly one unfiltered/global `students` aggregate query and exactly one filtered paginator aggregate query containing the filter column. Keep the assertion logic inside the test file; do not add one-off debug helpers.
  **Must NOT do**: Do not assert the total number of SQL statements for the whole request and do not depend on query order beyond what is necessary to distinguish filtered vs unfiltered aggregate SQL.

  **Recommended Agent Profile**:
  - Category: `quick` - Reason: one focused test-file enhancement.
  - Skills: [`pest-testing`, `laravel-best-practices`] - deterministic Pest regression tests with Laravel query listeners.
  - Omitted: [`inertia-react-development`, `wayfinder-development`] - not relevant.

  **Parallelization**: Can Parallel: NO | Wave 2 | Blocks: [6] | Blocked By: [2, 3, 4]

  **References** (executor has NO interview context - be exhaustive):
  - Pattern: `app/Http/Middleware/HandleInertiaRequests.php:101-125` - the original eager student count that produced one side of the duplication.
  - Pattern: `app/Http/Controllers/AdministratorStudentManagementController.php:173-178` - paginator call that produces the other aggregate count.
  - Test: `tests/Feature/AdminSidebarCountsTest.php:17-55` - use `Cache::flush()` style isolation already present in the suite.
  - Guardrail: `tests/Feature/AdministratorStudentIndexCountsTest.php` - keep all SQL-capture logic in this dedicated regression file.

  **Acceptance Criteria** (agent-executable only):
  - [ ] `vendor/bin/sail artisan test --compact tests/Feature/AdministratorStudentIndexCountsTest.php --filter="issues only one unfiltered students aggregate query on the unfiltered students index"` passes.
  - [ ] `vendor/bin/sail artisan test --compact tests/Feature/AdministratorStudentIndexCountsTest.php --filter="does not duplicate the global students aggregate query when filters are active"` passes.
  - [ ] The tests filter only `students` aggregate SQL and ignore unrelated queries.

  **QA Scenarios** (MANDATORY - task incomplete without these):
  ```
  Scenario: Unfiltered request performs only one unfiltered students aggregate query
    Tool: Bash
    Steps: Run `vendor/bin/sail artisan test --compact tests/Feature/AdministratorStudentIndexCountsTest.php --filter="issues only one unfiltered students aggregate query on the unfiltered students index"`
    Expected: Test passes and proves the unfiltered request emits one unfiltered `students` aggregate query rather than two equivalent ones.
    Evidence: .sisyphus/evidence/task-5-unfiltered-query-budget.txt

  Scenario: Filtered request avoids a duplicate global students aggregate query
    Tool: Bash
    Steps: Run `vendor/bin/sail artisan test --compact tests/Feature/AdministratorStudentIndexCountsTest.php --filter="does not duplicate the global students aggregate query when filters are active"`
    Expected: Test passes and proves there is one global/unfiltered `students` aggregate query plus one filtered paginator aggregate query, with no second global duplicate.
    Evidence: .sisyphus/evidence/task-5-filtered-query-budget.txt
  ```

  **Commit**: NO | Message: `fix(admin-students): remove duplicate student count query` | Files: [`tests/Feature/AdministratorStudentIndexCountsTest.php`]

- [ ] 6. Run targeted quality gates and finalize the narrow fix

  **What to do**: Run `pint` and the targeted feature suite covering sidebar counts, new index-count behavior, and the pre-existing index clearance tests. If any failures appear, fix only failures caused by the new count-sharing path; do not widen the scope. Keep the final diff limited to the support class, middleware, controller, and the two feature test files unless an unavoidable test helper import is required.
  **Must NOT do**: Do not run or modify unrelated suites to justify extra refactors, and do not add temporary debug output or logging.

  **Recommended Agent Profile**:
  - Category: `quick` - Reason: deterministic verification and polish.
  - Skills: [`pest-testing`, `laravel-best-practices`] - final verification plus narrow remediation if something regresses.
  - Omitted: [`review-work`] - the plan already reserves the formal review in the Final Verification Wave.

  **Parallelization**: Can Parallel: NO | Wave 2 | Blocks: [F1, F2, F3, F4] | Blocked By: [3, 4, 5]

  **References** (executor has NO interview context - be exhaustive):
  - Test: `tests/Feature/AdminSidebarCountsTest.php` - sidebar shared-prop coverage.
  - Test: `tests/Feature/AdministratorStudentIndexCountsTest.php` - new unfiltered/filtered/query-regression coverage.
  - Test: `tests/Feature/AdministratorStudentIndexClearanceTest.php:23-184` - existing students-index behavior that must keep passing.
  - Command Pattern: `README.md:69-80` - project uses Sail for test and formatting commands.

  **Acceptance Criteria** (agent-executable only):
  - [ ] `vendor/bin/sail bin pint --dirty --format agent` passes.
  - [ ] `vendor/bin/sail artisan test --compact tests/Feature/AdminSidebarCountsTest.php tests/Feature/AdministratorStudentIndexCountsTest.php tests/Feature/AdministratorStudentIndexClearanceTest.php` passes.
  - [ ] Final changed-file set stays limited to the planned implementation/test files.

  **QA Scenarios** (MANDATORY - task incomplete without these):
  ```
  Scenario: Targeted quality gate passes across the affected scope
    Tool: Bash
    Steps: Run `vendor/bin/sail bin pint --dirty --format agent && vendor/bin/sail artisan test --compact tests/Feature/AdminSidebarCountsTest.php tests/Feature/AdministratorStudentIndexCountsTest.php tests/Feature/AdministratorStudentIndexClearanceTest.php`
    Expected: Formatter exits cleanly and all targeted feature tests pass.
    Evidence: .sisyphus/evidence/task-6-targeted-suite.txt

  Scenario: Focused regression rerun stays green after any last-minute fixes
    Tool: Bash
    Steps: Run `vendor/bin/sail artisan test --compact tests/Feature/AdministratorStudentIndexCountsTest.php --filter="students aggregate query"`
    Expected: The query-regression subset remains green after formatting and any follow-up edits.
    Evidence: .sisyphus/evidence/task-6-query-rerun.txt
  ```

  **Commit**: YES | Message: `fix(admin-students): remove duplicate student count query` | Files: [`app/Support/AdministratorSidebarCounts.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `app/Http/Controllers/AdministratorStudentManagementController.php`, `tests/Feature/AdminSidebarCountsTest.php`, `tests/Feature/AdministratorStudentIndexCountsTest.php`]

## Final Verification Wave (MANDATORY — after ALL implementation tasks)
> 4 review agents run in PARALLEL. ALL must APPROVE. Present consolidated results to user and get explicit "okay" before completing.
> **Do NOT auto-proceed after verification. Wait for user's explicit approval before marking work complete.**
> **Never mark F1-F4 as checked before getting user's okay.** Rejection or user feedback -> fix -> re-run -> present again -> wait for okay.
- [ ] F1. Plan Compliance Audit — oracle
- [ ] F2. Code Quality Review — unspecified-high
- [ ] F3. Real Manual QA — unspecified-high (+ playwright if UI)
- [ ] F4. Scope Fidelity Check — deep

## Commit Strategy
- Use a single final commit after Task 6 and before the Final Verification Wave.
- Commit message: `fix(admin-students): remove duplicate student count query`

## Success Criteria
- The administrator students index no longer performs two equivalent global `students` aggregate queries on an unfiltered page load.
- Filtered index requests still show a global `stats.total_students` value while the table paginator reflects the filtered result set.
- `adminSidebarCounts.students` remains available and numerically aligned with the page stat on the students index.
- The targeted Pest suite proves behavior and query regression without relying on manual inspection.
