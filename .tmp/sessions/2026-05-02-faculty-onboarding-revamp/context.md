# Task Context: Faculty Onboarding Fix + Visual Revamp

Session ID: 2026-05-02-faculty-onboarding-revamp
Created: 2026-05-02T00:00:00Z
Status: in_progress

## Current Request
Analyze the faculty dashboard onboarding behavior because the onboarding modal keeps showing after login. It should only show once for new users. Also revamp the onboarding to be more visual and graphical (SVG), and align with top-tier SaaS onboarding patterns: Aha moment, interactive walkthroughs, checklists, segmented personalization, and measurable progress.

## Context Files (Standards to Follow)
- /home/admin/.opencode/context/core/standards/code-quality.md
- /home/admin/.opencode/context/ui/web/react-patterns.md
- /home/admin/.opencode/context/ui/web/animation-components.md
- /home/admin/.opencode/context/ui/web/ui-styling-standards.md
- /home/admin/.opencode/context/ui/web/design-systems.md
- /home/admin/.opencode/context/ui/web/animation-basics.md
- /home/admin/.opencode/context/ui/web/animation-advanced.md

## Reference Files (Source Material to Look At)
- /home/admin/KoAkademy/routes/web/faculty-portal.php
- /home/admin/KoAkademy/resources/js/pages/faculty/dashboard.tsx
- /home/admin/KoAkademy/resources/js/components/onboarding-experience.tsx
- /home/admin/KoAkademy/app/Http/Middleware/HandleInertiaRequests.php
- /home/admin/KoAkademy/app/Services/OnboardingShareService.php
- /home/admin/KoAkademy/config/onboarding.php

## External Docs Fetched
- Inertia shared data and remembering state guidance (v2 docs via Laravel Boost)
- Pennant scope and feature behavior guidance

## Components
- Onboarding trigger logic (new-user only + one-time behavior)
- Faculty dashboard onboarding data wiring
- Onboarding modal UX/UI redesign with SVG visuals
- Onboarding checklist and progress experience

## Constraints
- Follow existing Laravel + Inertia + React conventions
- Keep onboarding dismiss logic compatible with existing backend endpoint
- Avoid dependency changes unless explicitly approved
- Preserve accessibility and responsive behavior

## Exit Criteria
- [ ] Faculty onboarding no longer re-opens repeatedly after dismissal
- [ ] Onboarding appears only for new faculty users (or active undismissed feature onboarding)
- [ ] Onboarding UI is visually enhanced with SVG-based explanatory graphics
- [ ] Checklist/progress-driven onboarding flow is implemented
- [ ] Affected tests pass
