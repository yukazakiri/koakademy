# Feature access and distribution revamp

**Date:** 2026-10-12

**Status:** Approved direction; implementation complete for local feature access; optional follow-ups deferred

**Scope:** Local feature access, built-in role defaults, users/roles/schools, beta groups, stable percentage releases, and the existing `/administrators/feature-toggles` page.

## Decisions

- Keep Laravel Pennant 1.26.0, existing feature classes, FQCN storage names, route names, and frontend flag keys.
- Separate management policy and explicit assignments from Pennant's automatically persisted results.
- Fresh installations receive explicit built-in defaults for each eligible role. Never activate everything for everyone.
- Existing installations retain legacy results until an administrator explicitly adopts managed policy for a feature. Never guess operator intent from timestamps.
- Deny wins over allow. Kill switch, role eligibility, and school boundaries cannot be bypassed by user grants or beta opt-in. Existing action policies remain mandatory.
- Replace `/administrators/feature-toggles` in place. No redirect to another page.
- Retire competing Filament writes; keep a read-only pointer to feature management.
- No Marketplace/package-delivery changes, billing, central service, or new flag dependency.

## Source audit corrections

Sources: `FeatureToggleService`, `FeatureToggleRegistry`, `ResolvesFeatureToggle`, `ProfileController`, `UserFeatureFlagService`, Filament Pennant manager, and migration `2026_06_04_102238_sync_pennant_manager_discovered_features`.

- Global portal writes replace stored user results and JSON metadata; Filament writes have different semantics.
- Automatic evaluations and manual overrides occupy indistinguishable rows. Existing plain rows cannot safely be imported as confirmed administrator assignments.
- `purgeOverrides()` forgets only default scope, despite claiming all users.
- Process-static global cache can survive across Octane requests/workers.
- Legacy `onboarding-*` aliases are normalized inconsistently.
- JSON envelopes containing `enabled: false` are not boolean false to Pennant; native `active()` treats every value other than false as active. Public null-scope checks can collide with application's global sentinel.
- Shuffled user-ID selection is not a stable, reversible rollout for future users.
- June discovery migration seeds disabled global rows; earlier bulk activations update only existing rows. Fresh defaults need explicit initialization. Not every feature necessarily resolves false: special class resolvers and persisted results also matter.
- Management routes have portal-entry checks, not dedicated distribution authority. Self-service lacks server-side role eligibility enforcement.

These are source findings, not inspected production state. Previous plan overstated uniform defaults, suggested unsafe timestamp inference, hashed target rather than user identity, and mixed schema changes into a supposedly migration-free phase. This revision replaces those claims.

## Built-in fresh defaults

One code-owned catalog extends `FeatureToggleRegistry`: canonical key, legacy aliases, existing class, eligible roles, default availability, release tier, and user-opt-in eligibility. Registration derives from catalog instead of another manual list.

| Role family | Enabled built-ins | Targeted / opt-in features |
| --- | --- | --- |
| `student`, `graduate_student`, `shs_student` | Dashboard, classes, tuition, schedule, announcements, settings, help, information updates, avatar upload, signature pad | Grade preview and attendance tracker; developer mode administrator-controlled |
| `professor`, `associate_professor`, `assistant_professor`, `instructor`, `part_time_faculty` | Dashboard, action center, classes, schedule, announcements, settings, help, toolkit, grades, attendance, resources | At-risk alerts, assessments, inbox, office hours, requests/approvals, insights, forms; developer mode administrator-controlled |
| Administrative/support roles | Existing desks and permission-driven administration unchanged; no new desk grants | Administrative developer mode and relevant AI features explicitly distributed |
| Guest/public | College and TESDA enrollment are installation-level built-ins | No user beta groups/opt-in |
| AI | Disabled until explicitly released to eligible roles | Provider configuration, domain permissions, rate limits, data authorization remain separate |

New users inherit role defaults automatically. Baseline functions are not self-service experimental toggles.

Detect fresh installation once during additive initialization: no users and no completed setup means managed role defaults; otherwise legacy compatibility. Persist mode per feature. Preprovisioned installations conservatively remain legacy and can explicitly adopt defaults.

## Storage and evaluator

Add `feature_definitions` (enabled kill switch, `legacy`/`enforced` mode, `defaults`/`targeted`/`percentage` strategy, percentage, stable seed, opt-in policy, revision); `feature_assignments` (feature, user/role/school/group subject, allow/deny, administrator/self-service source, actor); beta groups and membership; audit entries. A separate additive migration adds optional `activate_at`/`retire_at` schedule columns.

Leave legacy `features` rows/schema intact. Never store control-plane JSON in new Pennant values. No arbitrary SQL field targeting or runtime expression execution.

Managed precedence:

1. Validate known feature, role eligibility, and authorized school context.
2. Disabled kill switch denies.
   Release window then applies: before activation is not-yet-released; at/after retirement is deliberately unavailable. Both beat explicit grants.
3. Any matching administrator deny wins.
4. Matching administrator allow grants inside eligibility boundaries.
5. Valid self-service opt-in applies only to approved experimental features when administrator enabled it. Opt-out never disables baseline functions.
6. Release strategy resolves role defaults, explicit targets only, or percentage of eligible users.

Assignments OR-match subjects, but denials are evaluated first. Missing school never matches another school. Group membership is ignored outside its school boundary. Primary `UserRole` controls targeting; Spatie action permissions stay separate.

Stable percentage hashes bounded SHA-256 prefix of seed, feature key, school context, and user ID modulo 10,000. Do not hash group/role ID instead of user ID. Seed independent of revision: larger percentage contains prior users. No mass writes or lottery redraw.

Shared class `before(?User $scope)` hook returns complete managed boolean for enforced mode; returns null in legacy mode. Request-scoped access service never recursively calls same feature through Pennant. Preview compares current legacy state with managed state without switching access.

Pennant hook is subject to in-memory cache. Flush after writes and at request/job boundaries. Start with request-scoped batched DB snapshots, not cross-request cached policy. All workers observe committed policy on next request. Measure before introducing shared caching.

## Administrator page

Operational, table-backed, Frame-led, dense, no page-load animation.

- Header: **Feature access**, local-control context, beta-group action.
- Search/filter catalog: roles, tier, compatibility mode, distribution, explicit assignments; never label resolved rows overrides.
- Feature inspector: built-in role destinations, compatibility notice, kill switch, release strategy, percentage, opt-in, explicit save.
- Targets: role/school/group selectors and searchable users; allow/deny/remove-to-inherit.
- Access preview: choose user; current versus proposed managed state plus reason.
- Beta cohorts: create/edit group and membership; school validation on server.
- Audit: actual persisted changes.

Preserve URL and existing endpoint names as authorized compatibility adapters. Reuse ReUI/shadcn primitives; Wayfinder endpoint URLs; responsive inspector stacks below locally scrollable grid. Each data region handles empty/loading/error. No optimistic security-policy saves.

## Delivery and rollback

1. Add schema/catalog and explicit fresh role defaults; existing installs stay legacy.
2. Add evaluator, deterministic rollout, lifecycle cache flushing, precise assignment accounting, and safe self-service adapters.
3. Replace administrator page; add policy/assignment/group/preview/audit endpoints. Make competing Filament manager read-only in same release.
4. Adopt feature by feature after preview. Legacy rows remain separate and recoverable.
5. Scheduled windows now supported without cron. Later: automated shadow dashboards and optional, separately reviewed cleanup. Never prune ambiguous legacy rows automatically.

Runtime rollback switches feature mode to legacy; no purge. Older binaries cannot read new policy. Before code downgrade, freeze policy writes and review/export effective state or retain new binary until rollback safety proven. No automatic destructive migration rollback.

## Validation gates

- Fresh defaults for every intended role and future users; beta/AI/developer off; public enrollment booleans.
- Upgrade preserves raw rows and legacy effective state, including special resolvers/JSON. Unknown or conflicting aliases reported, never guessed/deleted.
- Existing behavioral coverage runs in explicit legacy compatibility; managed coverage tests new contracts. Bulk activation in old tests is not proof of production behavior.
- Every subject allow/deny; deny precedence; wrong-role and cross-school rejection; group removal.
- Stable/monotonic percentage, 0/100 boundaries, no evaluation writes.
- Kill switch preserves assignments; assignment counts exclude cached results.
- Self-service cannot bypass administrator deny or enable privileged developer tools; unrelated profile save never clears assignments.
- Guests, students, faculty, non-system administrative roles and unauthorized JSON callers cannot manage distribution.
- Isolated DB feature tests; lifecycle cache checks; PHP formatting/static checks; touched TypeScript/lint/build checks.
- Actual desktop/mobile, light/dark visual and interaction checks; report environment blockers honestly.

## Progress

Shipped in this change set:

- [x] Additive schema, code-owned catalog, canonical alias handling, explicit fresh-install role defaults.
- [x] Managed evaluator with deterministic rollout, request-scoped policy reads, lifecycle cache flushing, precise assignment accounting, and server-side opt-in validation.
- [x] `/administrators/feature-toggles` replaced in place: catalog grid, policy inspector, audience targets, beta groups, access preview, audit. Competing Filament writes made read-only with a pointer to the new page.
- [x] Full suite green: 1944 tests / 14204 assertions. New distribution coverage: 17 tests / 1732 assertions. Pint clean, production build passes, ESLint clean on touched files, browser-verified desktop/light/mobile.

Follow-up implemented: scheduled release/retirement windows, read-only current/draft impact comparison (500 eligible users maximum, bounded counts), actual reviewed `PUT` adoption, safe rollout redirects for page GETs, and durable one-time destination notices that survive background polling. User-level legacy comparison reads stored values or class resolvers without inserting subject evaluation rows. Dates round-trip with explicit timezone offsets. Scheduled/retired badges reflect saved policy.

Latest full suite: **1959 tests, 14282 assertions passed**. Production client/SSR build, touched-file ESLint/Prettier, documentation validation, and targeted PHPStan level 5 (without broken repository baseline; PHPDoc certainty disabled for existing nullable model annotations) pass. Repository-wide TypeScript still reports unrelated errors; no errors in new feature-management files. Actual browser verifies legacy adoption `PUT`, draft impact `POST`, schedule save/timezone round-trip, desktop/mobile layout and zero page errors. Light mode checked through persisted theme preference and reload, not direct DOM-class overrides.

Remaining: automated shadow-mode mismatch dashboards and an operator-approved legacy cleanup workflow. No bulk adoption or automatic legacy purge. Repository-wide TypeScript and default PHPStan baseline still contain unrelated errors/missing paths; targeted checks reported separately. The unused former edit page was removed in this session after reference search.
