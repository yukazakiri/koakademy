# Newsletter consent modal design QA

## Visual sources

- Reference: `/home/misadmin/.codex/generated_images/019fc68e-e791-7a12-ae47-69406d9e198c/exec-94537ff5-c8c7-423b-98a0-4c498fd80439.png` (1402 × 1122, 1x)
- Dark implementation: `docs/src/assets/screenshots/newsletter-consent-modal-dark.png` (1402 × 1122, 1x)
- Light implementation: `docs/src/assets/screenshots/newsletter-consent-modal-light.png` (1402 × 1122, 1x)
- Mobile implementation: `docs/src/assets/screenshots/newsletter-consent-modal-mobile.png` (390 × 844, 1x)

The reference and final dark implementation were reviewed together at the same desktop viewport. The implementation preserves the selected centered composition, circular newsletter icon, transactional-message distinction, unsubscribe reassurance, full-width primary and secondary actions, divided permanent-decline action, dark surface, border treatment, and subdued overlay.

## Browser verification

- Browser: containerized Chromium 149 in headless mode against the real Laravel/Inertia student portal.
- Desktop dialog bounds: 900 × 759 at a 1402 × 1122 viewport.
- Mobile dialog bounds: 358 × 645 at a 390 × 844 viewport; content remains inside the viewport and the dialog can scroll if browser chrome reduces the available height.
- Keyboard focus opened on `Subscribe to newsletter` and remained inside the Radix dialog.
- Escape closed and session-snoozed the prompt.
- Overlay click closed and session-snoozed the prompt.
- `Maybe later` closed and stored the browser-session snooze flag.
- `Don't ask me again` posted the permanent server-side decline and closed the prompt.
- Dark and light themes rendered with existing KoAkademy design tokens.
- Loading and disabled states are present for subscribe and permanent-decline requests.
- Dialog semantics included `role="dialog"`, an accessible title, and an accessible description.

## Findings and iteration history

1. The first implementation measured about 596 × 602 on desktop and was visibly denser than the selected direction. Severity: P2. Fixed by increasing the desktop container, icon, type scale, spacing, action height, close target, and permanent-action region while preserving the compact mobile layout.
2. The final side-by-side comparison found no remaining P0, P1, or P2 visual differences. The implementation uses the project's Lucide mail icon rather than inventing an asset.
3. Existing dashboard SVG sizing warnings and background 400/403 data requests were visible in the QA account before and behind the modal. Severity: P3, unrelated to this modal; no newsletter-dialog console error was introduced.

final result: passed

# Program schedule PDF design QA

## Visual sources

- Reference: `/home/misadmin/.codex/attachments/7b858b16-cc47-40d7-bf79-cc02d60b92ff/codex-clipboard-9be9ead9-35e7-4509-8419-491f99bc5f9a.png`
- Rendered implementation: `/tmp/koakademy-schedule-pdf-qa/bsba-sectioned-schedule-page.png`
- Rendered continuation page: `/tmp/koakademy-schedule-pdf-qa/bsba-sectioned-schedule-page-2.png`

The reference and rendered implementation were reviewed together. The implementation preserves the centered institutional/program/academic-year heading, thin bordered compact tables, year bands, separate Section A and Section B tables, and the six requested columns: course code, descriptive title, units, schedule, room, and face-to-face classes.

## PDF verification

- Driver: the configured production Gotenberg service.
- Data: a read-only render of the current 2026-2027 first-semester BSBA ABM schedule.
- Output: A4 portrait, 2 pages, with repeating table headers and natural continuation onto page 2.
- Section A and Section B remain separate and ordered within each year.
- `BFIN 1` renders once per section as `Business Finance` with 3 units.
- `BMGMT 1` also resolves through the matching BSBA curriculum instead of falling back to an empty title.
- The browser-printable PDF retained table borders, neutral year/section bands, and legible page margins.

## Findings and iteration history

1. The first render exposed unresolved `BMGMT 1` titles because equivalent linked subjects had different titles across BSBA and BSHM. Severity: P1. Fixed by preferring linked subjects from the exported program family before accepting an unambiguous cross-program fallback.
2. The final side-by-side comparison found no remaining P0, P1, or P2 visual differences. The implementation is slightly cleaner than the photographed paper because it is generated directly rather than scanned.

final result: passed
