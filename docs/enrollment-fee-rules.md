# Enrollment fee rules

## Configure modular pricing

In System Management → Enrollment Pipeline → Stage 5: Tuition & Billing, edit the course-rate billing strategy:

- **Modular laboratory multiplier:** `1` charges the full lab fee, `0.5` charges half, and `0` waives it. New presets default to `1`.
- **Fee per modular subject:** separate surcharge added to lecture and laboratory fees. Set `0` to disable. Default remains `2400`.
- **Lecture fee basis:** combined lecture/lab units (compatibility default), or lecture units only. Subjects with no lecture units have no calculated lecture charge.
- **Laboratory fee basis:** one charge per subject (compatibility default), or rate multiplied by lab units.
- **Reduced-lecture subject code marker:** case-insensitive text fragment, default `NSTP`. Empty disables matching. The lecture multiplier remains `0.5` by default.
- **Default expected downpayment:** suggested fixed amount for new administrator enrollments. Default `3500`; zero allowed. This does not record a payment or change the minimum-payment completion gate.
- **Policy miscellaneous fee override:** blank uses existing program/curriculum fee; zero waives it. An explicit staff assessment amount takes precedence.

Publish the draft and activate the dynamic enrollment engine for new enrollments to use scoped policy settings. Existing published values are not rewritten. Legacy enrollments use compatibility defaults, not newly published policies.

Policy enrollments use their immutable enrollment policy snapshot when editing or recalculating. Publishing a policy does not bulk-reprice existing students. Existing saved subject amounts are preserved; correcting an old assessment requires an explicit staff edit or the finance revision workflow.

## Fee contract

`lecture_fee` and `laboratory_fee` are **final assessed amounts**, not base amounts awaiting another multiplier. Explicit zero is valid. Missing/null amounts are calculated independently from program rates by `EnrollmentFeeCalculator`.

Administrator create/edit requests quote subject fees through the backend calculator. Assignment, tuition creation, edit totals, recalculation, assessment calculations, and administrator assessment previews use the same subject fee contract. A saved modular lab fee of `1000` must remain `1000`, not become `500`, when tuition is calculated again.

Discounts apply to lecture charges only. Modular surcharge, lab, miscellaneous, and additional fees remain outside that discount.

An explicitly selected finance fee schedule is a repricing operation: it computes new subject amounts from that schedule instead of reusing saved amounts. Ordinary recalculation preserves saved amounts.

Simulation returns numeric component estimates when an existing enrollment is selected, without writing tuition. Estimates use saved subject fees plus draft surcharge settings; they do not pretend to reprice saved overrides when a multiplier changes. Without an enrollment, no monetary estimate is claimed.

## Developer extensions

Register code-backed strategies through `EnrollmentPolicyRegistry::registerBillingStrategy()`. A strategy must implement `RuntimeEnrollmentBillingStrategy`; implement `EnrollmentOperatorSchemaProvider` to expose approved configuration fields in the pipeline editor. Use `calculate()` for a read-only quote and `execute()` for persisted billing behavior.

Settings are data, not executable PHP/JavaScript. Keep custom calculations in reviewed application code. Custom strategies must also define how their prices behave during administrator edits and finance revisions; registering a strategy alone does not replace every compatibility calculation.

## Remaining compatibility boundaries

Filament subject selection, modular toggles, editable-unit quotes, row creation/hydration, and provider modular totals now use the shared pricing bridge. The Filament API create handler now submits through the typed workflow and uses shared assignment/assessment logic in its legacy fallback. Full interactive create/edit lifecycle verification and remaining legacy form defaults are still required before claiming parity across every entry point. Curriculum-year miscellaneous inference remains the fallback when no policy override is supplied. Filament downpayment field now accepts zero and resolves the policy suggestion; selecting a student loads scoped miscellaneous and downpayment defaults.

## Assignment behavior

The first-available strategy supports **Prefer the class with the most available seats**. It ranks remaining active seats, prioritizes unlimited sections, and breaks ties by class ID. It rechecks capacity under the class row lock; ranking is advisory under concurrent enrollment. Default remains class-ID order.

The unsupported **Include eligible irregular subjects** control was removed. Automatic curriculum assignment still selects matching program/year/semester subjects. Existing stored flags are retained for compatibility but do not enable irregular selection. Prerequisite-aware irregular assignment needs a real eligibility provider before this control returns.

## Deferred work

This change does not add dynamic semesters/year catalogs, arbitrary discount scopes, SMS/broadcast notification channels, prerequisite/grade fact providers, or full Filament/API migration. Those require separate domain changes and end-to-end coverage; their controls are not added here.

## Trusted completion limits

Two new registry rules are available in the pipeline rule editor:

- `academics.maximum_assigned_units`: configure `maximum`; sums current curriculum lecture + lab units for saved assigned subjects, including tuition-excluded rows. It deliberately ignores caller facts and editable submitted unit counts. Missing subjects fail closed. This is not a frozen historical curriculum-unit snapshot.
- `billing.maximum_other_assessment_balance`: configure `maximum`; sums authoritative positive ledger balances for the same student's other non-cancelled assessments in the enrollment's school. Excludes the current assessment, cancelled/deleted enrollments, and unlinked legacy tuition. Credits do not offset another assessment's debt. It covers other assessments, not strictly earlier periods.

These rules enforce at terminal completion, including terminal-entry submissions. A failure rolls back the transition/submission. They may also be used explicitly as branch conditions. Simulation evaluates saved records; hypothetical samples without a saved enrollment report these checks as deferred, not verified eligibility. Existing fact-based `eligibility.unit_limit` and `eligibility.outstanding_balance` retain their old behavior and are not automatically migrated.

MCP subject creation calculates assessed fees before saving, rather than relying on zero fees to trigger recalculation. Explicit zero fee overrides remain final.

## Cumulative minimum-payment gate

Payment verification and the `billing.minimum_payment` completion rule share `EnrollmentMinimumPayment`. The gate counts verified allocations to the current assessment plus verified enrollment-linked `tuition_fee` receipts not already represented by those allocations. Allocation rows replace, rather than duplicate, the same transaction's legacy settlement amount. Mixed allocated and new linked receipts are supported.

The minimum is cumulative, not a fresh minimum on every installment. Pending/reversed transactions, additional-fee-only receipts, opening paid caches, expected downpayments, and caller `payment_amount` facts do not satisfy this gate. This intentionally tightens older completion checks that counted every linked charge. Percentage minimums require a saved assessment. No-receipt verification still requires the configured minimum to have already been received. Settlement values must be finite and non-negative. Idempotent replays do not add their incoming amount twice.

The Filament API create handler now honors policy workflow actions and snapshots, shares fee formulas with administrator enrollment, and supports `Idempotency-Key`. Legacy creation preserves supplied final fees and validates assigned classes through the shared assignment service. Invalid workflows/assignments use framework validation responses rather than returning raw exception details in a custom 500 response. Form validation retains its existing field constraints.

## Verified schedule completion gate

`academics.verified_schedule` adds a code-backed rule to the pipeline editor. Configure `minimum_gap_minutes` (whole minutes, 0–1440; default 0) and `missing_schedule` (`block` by default, or explicit `allow`). It checks saved active class roster sections for the same school, semester, and normalized school year. It includes other active sections in that period even if not linked to this enrollment's subject rows, and ignores caller eligibility facts.

Different sections cannot overlap on the same normalized weekday. Back-to-back classes pass with zero gap; an exact configured gap passes. Repeated sessions within the same section are not treated as cross-class conflicts. Missing/unknown days and non-increasing or overnight time ranges are unchecked data: default blocks, while explicit allow reports unchecked class IDs. No active sections fails closed. Subjects without assigned sections are outside this rule's scope.

The gate runs at completion, or explicitly as a branch condition. Saved-enrollment simulation evaluates it; hypothetical samples defer it. A blocked transition rolls back. Existing `eligibility.schedule_conflict` retains its fact-based compatibility behavior. This check reads current schedules and is not a reservation or a guarantee against concurrent timetable edits after verification.

## Verified prerequisite completion gate

`academics.verified_prerequisites` checks all prerequisite subject IDs on currently assigned curriculum subjects. Each prerequisite must belong to this school and have a passing attempt in another enrollment marked `terminal_outcome=completed` from an earlier academic period. Any passing earlier attempt counts; this is not a latest-retake/GWA rule. Same/current/future periods, cancelled enrollments, and unlinked legacy rows cannot satisfy it. School years must use consecutive `YYYY - YYYY` format (spaces optional). Missing school, malformed prerequisite IDs, and unavailable curriculum fail closed.

Recorded `pass`/`passed` outcomes count. If outcome is null, the attempt's published pinned grading policy evaluates its numeric or symbolic grade. No current global grade threshold is guessed, and evaluation does not write back outcomes. Legacy numeric grades without a recorded outcome or pinned policy need registrar reconciliation before using this gate.

`accept_credited` defaults false. Enabling it trusts an existing `is_credited` row with matching `subject_id` or mapped `credited_subject_id` without requiring a grade; it does not invent an approval workflow. The source enrollment must still be completed in an earlier period. Arbitrary code equivalences and concurrent corequisites are not inferred.

This is an opt-in completion gate, also usable as a branch condition. Existing fact-based `eligibility.prerequisites` remains unchanged. Saved-enrollment simulation evaluates it; hypothetical samples defer it. Curriculum prerequisites and grade records are read at check time rather than frozen into the enrollment policy snapshot. Irregular automatic assignment and full grading-policy migration remain separate work.

## Interactive Filament pricing bridge

`EnrollmentFormPricing` resolves published scoped policy values before creation and pinned snapshot values on existing enrollments. Subject callbacks use the shared fee calculator; form `lecture`/`laboratory` fields map explicitly to final `lecture_fee`/`laboratory_fee` during typed submission and relation creation. Opening a row no longer writes missing calculated fees back to the database during hydration.

Provider totals include modular surcharge once in total tuition, honor tuition exclusions and zero miscellaneous, and treat an explicitly empty additional-fee list as removal rather than restoring database fees. Editing enrolled units recalculates both fee components through configured unit bases without writing curriculum units back to the subject model. Historical saved amounts still remain final until an explicit fee-affecting edit.

Tests cover the pricing bridge/provider utilities plus actual Livewire legacy/policy create and modular edit/save paths. Existing live edit-total persistence, aggregate manual overrides, and legacy initial/reset defaults remain compatibility behavior; real-browser workflow coverage and wider manual override cases remain pending.

## Filament lifecycle verification

Actual Livewire tests cover legacy and policy creation, saved-fee preservation, modular toggles, and a pinned fractional multiplier. Legacy create now writes required tuition semester/year metadata. Edit upserts carry student and period metadata too.

Policy edits derive final tuition components from saved subject rows through `AssessmentCalculationService` after relationship saving, rather than trusting nested callback totals. Aggregate component overrides on policy edits are therefore recalculated; edit final subject fees for intentional per-subject adjustments. Explicit miscellaneous/discount inputs remain supported, and existing finance assessment adjustments are preserved by the established reconciliation service. Legacy aggregate override behavior is unchanged.

## Student inbox notification channel

Pipeline notifications and `enrollment.notify` actions now accept `mail` (existing default) or `database`. Database delivery writes a status-only notification to the student's explicitly linked `User` inbox; it does not attach PDF assessments or render email/custom body templates. Enrollment ID, status, step/outcome, school year, and semester are captured when queued so later workflow changes cannot rewrite queued content.

Student email is not required for database delivery. Recipient must be `student.user_id`, have a student/graduate/SHS-student role, and have no conflicting school association. There is no email-based or legacy Account fallback. Missing/invalid recipient fails the configured action, rolling back a transition; disabled notifications remain skipped. Workflow retries use existing transition idempotency and do not dispatch twice. Direct action invocations retain the existing caller responsibility for idempotency.

Only email and database are exposed in the pipeline channel picker. SMS/broadcast require separate providers and remain unsupported. Queueing occurs after transaction commit. Existing shared inbox ownership checks continue to control reading notifications.

## Cancelling after cashier verification

Staff with `Cancel:StudentEnrollment` can cancel a legacy cashier-verified or policy-completed enrollment directly from its detail-page action menu. A reason is mandatory. Recorded linked payments and authoritative assessment paid amounts require explicit acknowledgement; cancellation does not delete payments, revoke receipts, refund, or reduce tuition balances.

The term receives `terminal_outcome=cancelled`, with prior outcome/status recorded in the audit event. Eligible reserved seats are released; shared seats remain protected. Policy-completed student/account synchronization is retained as history rather than demoting the global student. Legacy cashier status is likewise retained as reporting history. Cancellation is not an institution-wide `StudentStatus::Withdrawn` action: record withdrawal as the reason, then use separate registrar/finance processes for global status and financial resolution.

Undoing cancellation of a policy-completed enrollment restores its prior completed step/outcome without reversing receipts. Released seats are not restored automatically and must be reassigned with capacity checks. Rejected terminal enrollments still require a deliberate reopen before cancellation. Existing pre-completion cancellation reversal behavior is unchanged.
