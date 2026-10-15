# Profile Settings Revamp Plan

Scope: `resources/js/pages/profile.tsx` and the surrounding
`resources/js/components/profile/*` modules. The goal is to make the Profile
Settings page useful, coherent, and trustworthy for **every role** in KoAkademy
(students, graduate/SHS students, faculty, administrators, and "other" signed-in
users), while retiring or repairing the parts that currently look like product
surface but do not actually work (Integrations in particular).

---

## 0. Product goal

> One profile page, four jobs:
> 1. **Identity** - who am I in KoAkademy and how others see me.
> 2. **Completion** - what is missing and the shortest path to fix it.
> 3. **Security** - how my account is protected and what is connected.
> 4. **Preferences** - how KoAkademy behaves for me.

Everything else (admin-only finance workspaces, experimental toggles, digital
ID) should be discoverable but should not pollute the common path.

---

## 1. Current-state audit of `profile.tsx`

### Strengths worth keeping

- Role-aware data loading (`isStudent`, `isFaculty`, admin via
  `feature_flags`/`can_*` props).
- Strong student completion model (`student_profile_completion`) with guided
  field-by-field completion and section tabs.
- Digitial ID tab and Digital ID card generation.
- Avatar upload with preview and `hasChanges` tracking.
- Existing building blocks: security, browser sessions, connected accounts,
  personalization, experimental features.

### Pain points to address

1. **Monolith size.** `profile.tsx` is ~1,300 lines and mixes role detection,
   three unrelated forms, guided-completion DOM manipulation, and tab layout.
2. **Ambiguous save UX.** `hasChanges` is global but submit handlers are per-form.
   `handleSaveClick()` only scrolls to a form; users still have to find the right
   submit button. There is no dirty guard on tab/page navigation.
3. **Hidden/dead integrations.** `IntegrationsTab` renders Outlook and Zoom
   switches that only update local React state (`setIntegrations`) and never call
   an API. Microsoft/Zoom "connect" flows do not exist. This is the most visible
   "useless" surface and must be fixed or removed.
4. **Overlapping tabs.** `ConnectionsTab` (social login) and `IntegrationsTab`
   (productivity tools/newsletter) both use provider toggles and both say
   "connect"; users cannot tell the difference.
5. **Incomplete coverage for non-students.** Completion tracking and guided
   completion only exist for students. Faculty/admin users get a weak
   `accountProfileCompletion` based on 7 hand-picked fields and no guided flow.
6. **Performance.** `JSON.stringify(studentForm.data)` on every render for dirty
   detection; DOM queries for guided targets on every blur.
7. **Mobile navigation.** The sidebar tabs collapse into a 2/3-column grid on
   small screens with truncated labels, making labels like "Accounts & Security"
   unreadable.
8. **Accessibility gaps.** Tab panels do not announce completion state; guided
   focus uses manual `scrollIntoView` with timers; no `beforeunload` dirty guard.

---

## 2. Roles and what each needs

| Role | Primary needs on Profile |
|---|---|
| Student / SHS / Graduate | Basic identity, personal/family/education/reporting details, completion score, digital ID, social connections, calendar sync. |
| Faculty | Academic identity, office hours, education, courses taught, biography, expertise, digital ID, security. |
| Administrator | Everything faculty needs plus newsletter settings, finance workspace prefs, tuition adjustment workspace, API tokens, developer-mode features, integration admin. |
| Other signed-in | Basic profile + security + personalization only. |

Design principle: the **default tabs** should be Profile, Security, and
Personalization for everyone. Everything else is gated and labeled by category.

---

## 3. Proposed information architecture

Replace the current 7 tabs with 6 clearly named tabs, grouping overlapping
concepts.

| New tab | Replaces | Visible to |
|---|---|---|
| **Profile** | `profile` | all |
| **Digital ID** | `id-card` | students and faculty with issued card |
| **Security & Devices** | `accounts` + browser sessions | all |
| **Connected accounts** | `connections` + successful parts of `integrations` | all (sections gated) |
| **Preferences** | `personalization` | all (admin subsections gated) |
| **Labs** | `experimental` | users with available features only |

Key changes:

- Drop the separate "Integrations" tab as a peer. Merge it into "Connected
  accounts" with two sub-sections: **Sign-in providers** and **Connected
  services**.
- Rename "Accounts & Security" to "Security & Devices" and default-open the
  security section.
- Move newsletter / finance / tuition workspace cards out of a normal user's
  "Integrations" page and into an **Administrator tools** subsection of
  Preferences (only when `can_view_newsletter_settings` /
  `can_configure_*_workspace`).

---

## 4. Functional revamps

### 4.1 Integrations: repair or remove (highest priority)

Current dead code to resolve in `resources/js/components/profile/integrations-tab.tsx`:

- `handleGoogleCalendarToggle(false)` calls a real disconnect route, but the
  toggle state is React-local and desyncs from the server.
- Microsoft Outlook and Zoom switches only call `setIntegrations`; there is no
  OAuth route, token storage, or sync endpoint.
- `googleCalendar` is initialized from `connectedAccounts.providers["google"]`,
  but Google is also used for social sign-in. The two uses (social login vs
  calendar scope) are conflated.

#### Recommended plan

**Short term (1-2 weeks): honest UI**

1. Add a backend capability flag, e.g. `integration_capabilities`, listing which
   providers actually have working OAuth + sync:
   - `google_calendar`: connected + sync + unsync.
   - `microsoft_outlook`: not implemented.
   - `zoom`: not implemented.
2. In `IntegrationsTab`, render:
   - working providers as interactive cards;
   - unimplemented providers as "Coming soon" with a "Request" button that
     records interest (or just hide them behind a config flag).
3. Remove local-only switches; derive connected state from `connected_accounts`
   and refresh after connect/disconnect. Use `router.reload({ only: [...] })`
   instead of mutating local state.
4. Separate Google social-login scope from Google Calendar scope if both are
   needed; store scopes on `connected_accounts` and display them ("Sign-in only",
   "Calendar read/write").

**Medium term (4-6 weeks): real integrations hub**

Target integrations that match an academic ERP:

- **Calendar**: Google Calendar (existing), Microsoft 365/Outlook (real OAuth)
  for faculty/admin scheduling and student class/exam schedules.
- **Video classrooms**: Zoom/Google Meet/MS Teams link generation for classes
  and consultations, tied to class scheduling.
- **Email/newsletter**: Resend/Mailgun? KoAkademy already has newsletter
  settings; expose subscription status and preferences to the user.
- **File storage**: Google Drive/OneDrive for submission uploads? Maybe lower
  priority.
- **LMS/SSO**: institutional SSO (SAML/OIDC) for university accounts.

Minimum viable integration card fields:
- provider name + logo
- status badge: Connected / Needs re-auth / Not connected / Error
- account list (email + avatar)
- scopes
- last sync + next sync
- actions: Connect, Reconnect, Disconnect, Sync now, View logs
- error surface with retry

Backend additions needed:
- table `integration_connections` (or extend `connected_accounts`) with
  `provider`, `scopes`, `token_expires_at`, `refresh_token`, `status`,
  `last_synced_at`, `last_error`.
- queued sync jobs with retries and per-user rate limits.
- webhook handlers for provider revocation.
- admin view of integration health (counts, failures).

### 4.2 Security & Devices (all users)

- Elevate passkeys to first-class (already routed via `paths.passkeys`).
- Add **login history** (IP, device, result) beyond browser sessions.
- Add **email change verification** and **security notifications**.
- Recovery codes: downloadable, printable, one-time reveal, rotate.
- Developer/API keys: only show to users with developer mode; add expiry and
  usage stats (already partially).
- "End all other sessions" should require confirmation and show count before.
- Add a small "Security score" or checklist:
  - password set/changed recently
  - 2FA enabled
  - recovery codes saved
  - passkey registered
  - no stale sessions.

### 4.3 Preferences (all users)

Move from "personalization = theme + two finance grids" to a fuller preference
center:

- Display: theme (light/dark/system), color theme, density, reduced motion,
  font size, sidebar collapsed.
- Locale: language, date/time/timezone format, first day of week.
- Notifications: channel (email/in-app), categories, digest frequency,
  quiet hours.
- Academic: preferred calendar view (week/month), default landing page,
  accessibility aids.
- Admin widgets: only when capabilities allow.

Store preferences consistently (`user.preferences` JSON or a `user_preferences`
table) and apply globally.

### 4.4 Public profile and directory (faculty/admin mostly)

Add to the Profile tab:

- **Visibility controls**: who can see email, phone, office hours, biography.
- **Directory opt-in**: allow students/faculty to find each other.
- **Public profile preview** (drawer/modal) showing exactly what others see.
- **Pronouns** and **preferred name** (useful for students too).

### 4.5 Notifications center integration

- Link profile notification preferences to the existing
  `NotificationCenter` page rather than duplicating.
- Provide deep links (`/profile?tab=preferences#notifications`).

### 4.6 Completion for all roles (generalize student flow)

- Extend `StudentProfileCompletionService` concept to
  `ProfileCompletionService` with role-specific required fields.
- Faculty: name, email, department, office hours, biography, at least one
  course/education item.
- Admin: name, email, department/office, avatar.
- Show completion ring in `ProfileHeader` for all roles.
- Generalize the guided completion flow from `student-*` to any profile section
  (field IDs + tab routing) so faculty can also be guided through missing data.

### 4.7 Digital ID (students/faculty)

- Add download (PNG/PDF), print, and "Add to Wallet" groundwork (Apple/Google
  Wallet pass later).
- Show issuance/expiry status and request reissue when data changes.
- Regenerate QR only when identity changes; cache.

---

## 5. Engineering refactor plan

### 5.1 Split the page

Create:

```
resources/js/pages/profile/
  index.tsx                      # thin orchestrator + tab shell
  hooks/
    use-profile-forms.ts         # forms, dirty tracking, submit handlers
    use-profile-guidance.ts      # guided completion (role-agnostic)
  tabs/
    profile-tab.tsx
    digital-id-tab.tsx
    security-tab.tsx
    connected-accounts-tab.tsx
    preferences-tab.tsx
    labs-tab.tsx
```

Move leaf components already in `resources/js/components/profile` and import
from there; do not duplicate.

### 5.2 Dirty state and save UX

- Use Inertia form `isDirty` (or a single `useDirtyState` hook) instead of
  `JSON.stringify`.
- Add a **sticky save bar** that appears when any form is dirty:
  "You have unsaved changes - Save profile" plus per-section indicators.
- Add `beforeunload` guard and Inertia `router.on('before')` guard.
- Per-form submit buttons remain, but the sticky bar scrolls/focuses the
  relevant form.

### 5.3 Routing and deep links

- Make tab state controlled (`activeTab`) from URL search param `?tab=` plus
  hash for section, so links and back button work:
  `/profile?tab=connected-accounts#google-calendar`.
- Keep existing hashes (`#student-*`) as aliases for backward compatibility.

### 5.4 Data loading

- Lazy-load tab content with `React.lazy` + `Suspense` so the initial profile
  paint is fast.
- Pass only data needed for the active tab; consider Inertia partial reloads on
  tab change to reduce payload.
- Ensure backend returns `api_tokens` to the security tab without forcing it
  into the profile props type in the page (already loaded by security section via
  axios).

### 5.5 Validation and errors

- Standardize server validation messages and map them to fields.
- Add client-side schema validation (Zod) for immediate feedback on email,
  phone, URL, year ranges.
- Show success/dirty toasts consistently.

### 5.6 State management for integrations

- No local-only toggles. Source of truth = `connected_accounts` + integration
  status from backend.
- After connect/disconnect/sync, `router.reload({ only: ['connected_accounts'] })`
  or update Inertia shared state.

### 5.7 Tests

- Unit tests for `tabForStudentFormErrors`, `guidedFieldElementId`,
  `isGuidedFieldComplete`, and generalized guidance.
- Component tests for integrations status rendering (connected, needs reauth,
  coming soon).
- E2E (Playwright/Pest browser) for:
  - profile save for student/faculty/admin
  - guided completion completes all missing fields
  - integration connect/disconnect flow with mocked provider
  - dirty-state guard
- Accessibility scan (axe) on each tab.

---

## 6. UX and accessibility checklist

- Mobile: use a top segmented control or accordion instead of the 2/3-col grid.
- All tabs keyboard navigable; `aria-current` on active tab.
- Focus returns to the triggered element when dialogs close.
- `prefers-reduced-motion` disables framer-motion transitions.
- Toasts have `role="status"`/`aria-live`.
- Error states shown inline, not only as toasts.
- Minimum tap targets 44px; nothing below `text-xs`.

---

## 7. Phased roadmap

### Phase 0 - Stop the bleeding (1 week)

- Mark Outlook/Zoom as "Coming soon" or remove.
- Make Google Calendar state server-derived and refresh correctly.
- Add dirty guard (`beforeunload`).
- Mobile tab control cleanup.
- **Deliverable:** no dead toggles; honest integration UI.

### Phase 1 - Architecture split (2 weeks)

- Move guided-completion logic to `use-profile-guidance.ts`.
- Split `profile.tsx` into tab components and orchestrator.
- Introduce controlled tabs from URL params.
- **Deliverable:** `profile.tsx` under ~250 lines; tests pass.

### Phase 2 - Security & Preferences pass (2 weeks)

- Passkey-first security tab, security checklist, login history.
- Preference center v1 (theme, density, locale, notifications link).
- Completion ring for faculty/admin.
- **Deliverable:** security score and preferences for all users.

### Phase 3 - Integrations v2 (4-6 weeks)

- `integration_connections` schema + status.
- Real Microsoft 365 + keep Google.
- Queued sync, reauth, error states, admin health.
- Service-specific cards (calendar events preview).
- **Deliverable:** at least two working productivity integrations with real
  OAuth and sync.

### Phase 4 - Public profile & completion (2 weeks)

- Visibility controls, directory opt-in, public preview.
- Generalized guided completion for faculty/admin.
- **Deliverable:** profile completion for all roles + privacy controls.

### Phase 5 - Quality and metrics (ongoing)

- E2E + accessibility suite.
- Analytics events and funnel for completion/integration adoption.
- Performance budget (initial JS for profile route).

---

## 8. Success metrics

- **Integration adoption:** % of active faculty/admins with at least one working
  integration connected (target: 30% within 60 days).
- **Profile completion:** median completion % by role (target: student >= 85%).
- **Dead UI complaints:** zero toggles that only update local state.
- **Task success:** profile update without support tickets; measured by error
  toast rates.
- **Performance:** profile route initial JS reduced by ~40% after code split.

---

## 9. Risks and mitigation

- **Provider API approvals** (Google/Microsoft verification) can take weeks.
  Mitigation: ship honest "Coming soon" UI first; request verification early.
- **Over-engineering**. Mitigation: keep admin tools behind capabilities;
  common path stays 3 tabs.
- **Regression in student guided completion**. Mitigation: keep existing helper
  tests and port them unchanged; add E2E before refactor.
- **Token security**. Never send refresh tokens to frontend; only status,
  expiry, and scopes.

---

## 10. Immediate next steps

1. Remove/flag dead Outlook + Zoom toggles in `integrations-tab.tsx`.
2. Make Google Calendar state server-derived and refresh on toggle.
3. Add a dirty-state guard and mobile tab control.
4. Write unit tests for current completion helpers before refactoring.
5. Start Phase 1 folder split, keeping behavior identical.

---

## Appendix A - provider capability table (initial)

| Provider | Sign-in | Calendar sync | Meeting links | Scope model |
|---|---|---|---|---|
| Google | yes | yes (exists) | partial | `calendar` |
| Microsoft 365 | yes (Socialite) | needs build | needs build | `Calendars.ReadWrite` |
| Zoom | no | n/a | needs build | meeting create |
| GitHub | sign-in only | no | no | profile:read |
| Facebook/X/LinkedIn | sign-in only | no | no | profile |

## Appendix B - primary files to touch

- `resources/js/pages/profile.tsx`
- `resources/js/components/profile/integrations-tab.tsx`
- `resources/js/components/profile/connections-tab.tsx`
- `resources/js/components/profile/personalization-tab.tsx`
- `resources/js/components/profile/security-section.tsx`
- `resources/js/components/profile/profile-header.tsx`
- `app/Http/Controllers/ProfileController.php`
- `app/Http/Controllers/GoogleCalendarController.php`
- (new) integration connection model/migration/controller
- (new) profile completion service interface
