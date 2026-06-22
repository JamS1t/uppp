# Admin: View Deleted Items + Users Page Overhaul — Design

Date: 2026-06-22
Status: Approved (pending spec review)

## Goal

1. Give admins a reliable way to **view and restore soft-deleted rows** on every
   admin list (per-page Active/Deleted filtering).
2. **Overhaul the Users admin page** for clarity and intuitiveness: search &
   filter, decluttered per-row actions, better warnings UX, and visual polish.

No database schema changes. Everything relies on existing columns
(`deleted_at`, `is_banned`, `role`, `warnings`).

## Constraints / conventions to follow

- Plain PHP + PDO, no build step. JS is intentionally minimal (one file:
  `assets/js/vote.js`). New interactive UI uses native `<details>/<summary>` —
  **no new JavaScript**.
- Soft delete already exists: `soft_delete()`, `restore_row()` in
  `includes/functions.php`, whitelist `SOFT_DELETE_TABLES`.
- All POST actions are CSRF-guarded via `verify_csrf()` / `csrf_field()`.
- Styling tokens live in `assets/css/style.css` `:root`; admin styles live under
  the `/* ===== Admin ===== */` section. New CSS goes there.
- Reuse existing classes: `.badge`, `.badge-approved/-rejected/-primary`,
  `.btn`, `.btn-sm`, `.filter-tabs`, `.admin-table`, `.admin-actions`.

## Part A — View & restore deleted items (per-page)

Applies to five lists: **Users, Submissions, Categories, Reports**, and a
**new Comments** admin page. Comments are soft-deletable (the
`migration_soft_delete_warnings.sql` migration adds `deleted_at` to `comments`;
`comments` is already in `SOFT_DELETE_TABLES`), but there is no admin surface for
them today, so one is added.

### Shared helper (`includes/functions.php`)

Add one helper for the tab badge counts:

```php
function deleted_count(PDO $pdo, string $table): int {
    if (!in_array($table, SOFT_DELETE_TABLES, true)) {
        throw new InvalidArgumentException("Table '$table' is not soft-deletable.");
    }
    $stmt = $pdo->query("SELECT COUNT(*) FROM `$table` WHERE deleted_at IS NOT NULL");
    return (int)$stmt->fetchColumn();
}
```

(`$table` is from the trusted whitelist only — never user input.)

### Submissions (`admin/submissions.php`)

- Add a 4th filter tab **Deleted** after Pending/Approved/Rejected.
- When `?status=deleted`:
  - Count + list query use `WHERE s.deleted_at IS NOT NULL` (status ignored).
  - Show a **Restore** button instead of Approve/Reject/Delete; restore posts
    `action=restore` → `restore_row($pdo, 'submissions', $id)`.
- **Bug fix:** the existing Pending/Approved/Rejected count query filters
  `deleted_at IS NULL` but verify the list query does too (it does). Keep as-is.
- Validation: `$statusFilter` whitelist becomes
  `['pending','approved','rejected','deleted']`.
- POST handler: add `restore` case calling `restore_row`.

### Users (`admin/users.php`)

- Currently the list query has **no `deleted_at` filter**, so deleted users leak
  into the main list. Replace with an explicit **Active / Deleted** toggle
  (`.filter-tabs`, `?view=active|deleted`, default `active`).
  - `active`: `WHERE u.deleted_at IS NULL`
  - `deleted`: `WHERE u.deleted_at IS NOT NULL`
- The count query for `paginate()` gets the matching WHERE clause.
- Deleted view shows username/email/role + "Deleted <time> ago" + **Restore**
  (existing `restore` action already implemented — keep it).
- Active view shows the new Manage menu (Part B).

### Categories (`admin/categories.php`)

- Add Active/Deleted toggle (`?view=active|deleted`, default `active`).
- `active`: existing query (`WHERE c.deleted_at IS NULL`).
- `deleted`: `WHERE c.deleted_at IS NOT NULL`; row actions become a single
  **Restore** button (`action=restore` → `restore_row($pdo,'categories',$id)`).
- POST handler: add `restore` case. Hide the Add/Edit form when viewing Deleted.

### Reports (`admin/reports.php`)

- Reports currently have **no delete action**. Add one so the Deleted tab is
  reachable: a **Delete** button on each report row (`action=delete` →
  `soft_delete($pdo,'reports',$id)`), with a confirm.
- Add a **Deleted** tab after Pending/Resolved/Dismissed (`?status=deleted`).
  - When active: list `WHERE r.deleted_at IS NOT NULL`; action is **Restore**.
- `$statusFilter` whitelist becomes
  `['pending','resolved','dismissed','deleted']`.

### Comments — NEW page (`admin/comments.php`)

A new admin list page, mirroring the structure of `submissions.php`.

- **Sidebar** (`admin/_sidebar.php`): add a `Comments` link between Users and
  Categories (or after Reports — placement: after Users, grouping content
  moderation together: Submissions, Comments, then Users... keep simple →
  add after `submissions.php`). Add the `active` highlight for `comments.php`.
- **Active / Deleted toggle** via `.filter-tabs` (`?view=active|deleted`,
  default `active`).
- **List query** joins author and submission for context:
  ```sql
  SELECT cm.*, u.username, s.title AS submission_title
  FROM comments cm
  JOIN users u ON cm.user_id = u.id
  JOIN submissions s ON cm.submission_id = s.id
  WHERE cm.deleted_at IS NULL   -- or IS NOT NULL for the Deleted view
  ORDER BY cm.created_at DESC
  LIMIT ... OFFSET ...
  ```
  Use a `paginate()` count query with the matching WHERE.
- **Columns:** Body (excerpt via `mb_substr`, linked to the parent submission
  `../submission.php?id=<submission_id>`), Author (link to profile), On
  (submission title link), Date (`time_ago`), Actions.
- **Actions:**
  - Active view → **Delete** (`action=delete` → `soft_delete($pdo,'comments',$id)`,
    confirm).
  - Deleted view → **Restore** (`action=restore` → `restore_row($pdo,'comments',$id)`).
- POST handler guarded by `verify_csrf()`, `(int)` cast on `comment_id`.

## Part B — Users page UI overhaul (`admin/users.php` + CSS)

### 1. Search & filter bar (top of page)

A GET form above the table:

- `q` — text, matches username OR email using `LIKE` with `escape_like()`.
- `role` — All / Admin / User.
- `status` — All / Active / Banned (applies within the Active view; note
  Active/Deleted is the separate `view` toggle).

The list query builds a parameterized WHERE from whichever filters are set.
Filter state is preserved in pagination links (extend `pagination_html` base URL
with the current query string).

### 2. Decluttered per-row actions — "Manage ▾" menu

Replace the always-visible warn textbox + row of buttons with one native
`<details class="row-menu">` per user:

```
<details class="row-menu">
  <summary class="btn btn-outline btn-sm">Manage ▾</summary>
  <div class="row-menu-panel">
    <!-- Promote/Revoke form -->
    <!-- Warn form (textarea + reason) -->
    <!-- Ban/Unban form -->
    <!-- Delete form (confirm) -->
  </div>
</details>
```

- Each item is its own small POST form (current actions unchanged server-side).
- Destructive actions (Ban, Delete) keep their `confirm()` onsubmit.
- The "(You)" guard for the current admin's own row stays.

### 3. Better warnings UX — "Warnings (N) ▾"

Replace the hover-only `title` tooltip with a native `<details>`:

```
<details class="warnings-cell">
  <summary><span class="badge badge-rejected">Warnings: N</span></summary>
  <ul class="warning-list">
    <li>reason — by <issuer> · <time ago></li>
    ...
  </ul>
</details>
```

- Uses existing `get_warnings($pdo, $userId)` (already returns reason, issuer
  name, created_at). When N = 0, render a plain neutral badge, no disclosure.
- Avoids the N+1: `get_warnings` is only called for users with `warning_count > 0`
  (already the case today).

### 4. Visual polish

- **Initials avatar**: a small circular chip with the first 1–2 letters of the
  username (derived in PHP, no schema/storage). Class `.user-avatar-sm`.
- **Role & status pills**: reuse `.badge` variants — admin → `badge-primary`,
  active → `badge-approved`, banned/deleted → `badge-rejected`.
- **Row hover state** on `.admin-table tbody tr:hover`.
- Username cell becomes avatar + name stacked with email muted beneath (lets us
  drop the separate Email column width pressure on small screens — keep Email
  column but the avatar improves scannability).

### CSS additions (`assets/css/style.css`, Admin section)

- `.users-toolbar` — flex bar for search/filter inputs.
- `.row-menu` / `.row-menu-panel` — `<details>` styled as a dropdown popover
  (absolute-positioned panel, surface bg, shadow, radius; `summary` list-style
  reset). Panel forms stack vertically with small gaps.
- `.warnings-cell` summary reset + `.warning-list` styling.
- `.user-avatar-sm` — circular initials chip (e.g. 28px, primary tint bg).
- `.admin-table tbody tr:hover { background: rgba(0,0,0,0.02); }`

## Error handling / edge cases

- `view`/`status` params validated against explicit whitelists; invalid →
  default.
- `restore` / `delete` actions guarded by `verify_csrf()` and `(int)` casting,
  same as existing handlers.
- Restoring/deleting still blocked on the admin's own row (`$id !== current_user_id()`).
- Search query escaped for `LIKE` via `escape_like()`; bound as parameters.
- `<details>` menus degrade gracefully without JS/CSS (still usable, just
  unstyled).

## Testing (manual, XAMPP)

1. Soft-delete a user/submission/category/report/comment → it disappears from the
   default list and appears under that list's Deleted tab with a Restore button.
   For comments: delete one from the new admin Comments page, confirm it leaves
   the Active view and the public submission page, and shows under Deleted.
2. Restore → it returns to the active list.
3. Deleted counts on tabs match.
4. Users search by username/email, filter by role and status — results and
   pagination links preserve filters.
5. Manage menu: promote/revoke, warn (reason < 5 chars rejected), ban/unban,
   delete all still work and remain CSRF-guarded.
6. Comments admin page: sidebar link works, active list shows comments with
   author/submission context, Delete moves a comment to Deleted, Restore returns
   it, pagination works.
7. Warnings disclosure shows reasons/issuer/time; N=0 shows plain badge.
8. Own-row still shows "(You)" with no destructive actions.

## Out of scope (YAGNI)

- Permanent (hard) delete / purge.
- Bulk actions / multi-select.
- A unified central "Trash" page (explicitly chose per-page filters).
- Audit log of who deleted/restored what.
