# User management interface

Administrators work in three places: the directory, an account detail view, and the account form. All three use the ReUI component set already installed in this project (data grid, badge, icon tile, timeline) on top of the shadcn base.

## Directory

`/administrators/users` is an operations console:

- A left rail lists review queues with live counts: all accounts, recently online, MFA needs review, suspended, and deleted accounts. All, active, and unverified email act as quick views.
- The main panel holds the ReUI data grid. Search matches name and email; the filter cluster narrows system role, account status, email verification, and MFA state; the column control hides or restores columns; sorting and pagination come from the grid.
- Account names and a per-row View control open the account detail page. The row menu keeps edit, impersonate, suspend or reactivate, revoke sessions and tokens, verify email, send password reset, and delete.
- Live presence indicators refresh every 30 seconds. "Recently online" means observed activity within 15 minutes, not a guaranteed live connection.

The deleted-accounts queue requests archived accounts from the server and shows a banner with a way back. The rest of the queues filter the loaded directory in the browser.

## Account detail

The detail page answers four questions in order: who is this, what state is the account in, what protection does it have, and what happened recently.

- A header carries identity, status, and role, plus edit, suspend or reactivate, and revoke access actions.
- A row of signal tiles surfaces last sign-in, last active, email verification, configured login challenge methods, passkey count, and creation date. Missing history reads "Not recorded".
- Security history renders as a ReUI timeline with event, outcome, device, actor, and administrator reason. Filters narrow by event and outcome; paging is server driven.
- Access, browser sessions, and API tokens sit in a side column. Revoking a single credential is scoped to that credential; revoke all covers every session and token, including entries not listed.

## Account form

Create and edit share one form. Sections are Identity, Credentials, Placement, and Access. A sticky summary rail shows the initials avatar, name, email, system role, school, department, and custom bundle count, and holds the save actions so the primary action stays within reach.

System role is a single selector; custom permission bundles are the only entries under Additional access. The form posts the same fields as before, so backend validation and permission rules are unchanged.

## Data flow

The directory loads the full account set once and filters in the browser, matching the previous client-side table. Filtering by school, department, role, email verification, or MFA state is now part of the grid filter cluster rather than a server round trip. Sorting is client side; the server still accepts `trashed` for the deleted-accounts queue.

## Implementation notes

- The directory grid uses the ReUI data grid on `@tanstack/react-table-v9` with the `dataGridFeatures` bundle. Column filters take predicate functions: this v9 release registers no named filter functions, so string filter names do not resolve and the option type rejects them.
- `DataGridColumnFilter` and `DataGridColumnVisibility` read grid context, so they render inside the `DataGrid` provider, together with the table and pagination.
- Default column visibility hides last sign-in and joined; both return through the Columns control. Sorting is by name on load.
- ReUI pieces in use: `data-grid` (directory), `badge` (status, role, outcome), `icon-tile` (queue and signal icons), `timeline` (security history).
- Browser coverage lives in `tests/Browser/user-directory.mjs` with fixtures under `tests/Fixtures`. It exercises the directory grid, queue filtering, the detail page, and the account form, in both light and dark themes at desktop and mobile widths.
