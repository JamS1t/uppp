# uppp — Community Link-Sharing Platform Design Spec

## Context

uppp is a community-driven website where users share and discover digital resources — websites, tools, browser extensions, repos, and more. Users upvote/downvote submissions (logged-in only), leave comments, and the top-ranked submissions of the week are featured on the homepage for exposure. An admin approval workflow ensures quality control.

**Tech stack:** XAMPP (PHP 8+, MySQL, Apache), vanilla HTML/CSS, minimal JavaScript for voting.

## Architecture

**Approach:** Vanilla PHP with page-based routing. Each page is a standalone PHP file. Shared logic lives in `includes/`. No frameworks or Composer dependencies.

**Security:**
- PDO with prepared statements for all queries
- `password_hash()` / `password_verify()` for auth
- `htmlspecialchars()` for output escaping
- CSRF tokens on all forms
- Session-based auth with `session_regenerate_id(true)` on login to prevent session fixation
- Session cookie flags: `httponly: true`, `samesite: 'Lax'`
- Avatar uploads: validate via `getimagesize()` (not just extension), rename to random hash, max 2MB, `.htaccess` in uploads directory to deny PHP execution
- Server-side input length validation matching schema constraints before DB insertion
- LIKE search terms escaped for `%` and `_` wildcards in addition to prepared statement parameterization

**Pagination:** All list views (homepage feed, category pages, search results, profile submissions, all admin lists) use LIMIT/OFFSET pagination with 20 items per page and prev/next navigation.

## Database Schema

### users
| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| username | VARCHAR(50) UNIQUE | |
| email | VARCHAR(255) UNIQUE | |
| password_hash | VARCHAR(255) | bcrypt via password_hash() |
| role | ENUM('user','admin') | default 'user' |
| is_banned | TINYINT(1) | default 0. Banned users cannot login. |
| bio | TEXT | nullable, profile bio |
| avatar_path | VARCHAR(255) | nullable, relative path to uploaded avatar |
| website_url | VARCHAR(255) | nullable |
| created_at | DATETIME | default CURRENT_TIMESTAMP |

### categories
| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| name | VARCHAR(100) UNIQUE | display name |
| slug | VARCHAR(100) UNIQUE | URL-friendly version |
| description | TEXT | nullable |

### submissions
| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| user_id | INT FK -> users.id | submitter |
| category_id | INT FK -> categories.id | |
| title | VARCHAR(255) | |
| url | VARCHAR(500) | the shared link |
| description | TEXT | |
| status | ENUM('pending','approved','rejected') | default 'pending' |
| created_at | DATETIME | default CURRENT_TIMESTAMP |
| | | Duplicate URL check: PHP validates no approved submission with same URL exists before insert. Race condition accepted for MVP (admin approval gate provides a second check). |

### votes
| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| user_id | INT FK -> users.id | |
| submission_id | INT FK -> submissions.id | |
| vote_type | TINYINT | +1 (upvote) or -1 (downvote) |
| created_at | DATETIME | default CURRENT_TIMESTAMP |
| | UNIQUE(user_id, submission_id) | one vote per user per submission |

### comments
| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| user_id | INT FK -> users.id | |
| submission_id | INT FK -> submissions.id | |
| body | TEXT | |
| created_at | DATETIME | default CURRENT_TIMESTAMP |

### reports
| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| user_id | INT FK -> users.id | reporter |
| submission_id | INT FK -> submissions.id | nullable — for reporting submissions |
| comment_id | INT FK -> comments.id | nullable — for reporting comments |
| | CHECK | Exactly one of submission_id or comment_id must be non-NULL (enforced in PHP + DB CHECK constraint) |
| reason | TEXT | |
| status | ENUM('pending','resolved','dismissed') | default 'pending' |
| created_at | DATETIME | default CURRENT_TIMESTAMP |

### FK Behavior
All FKs use ON DELETE CASCADE — deleting a user removes their submissions, votes, comments, and reports. Deleting a submission removes its votes, comments, and reports. **Exception:** `submissions.category_id` uses ON DELETE RESTRICT — a category cannot be deleted while it has submissions. Admin must reassign or remove submissions first.

## File Structure

```
uppp/
├── index.php                  # Homepage — Top of the Week + recent feed
├── login.php                  # Login form + handler
├── register.php               # Registration form + handler
├── logout.php                 # Destroy session, redirect
├── submit.php                 # Submit a new link (auth required)
├── submission.php             # Single submission detail (votes, comments)
├── category.php               # Browse by category
├── search.php                 # Search results page
├── profile.php                # Public user profile
├── edit-profile.php           # Edit own profile (auth required)
├── report.php                 # Report a submission (auth required)
├── vote.php                   # AJAX endpoint for voting (auth required)
├── admin/
│   ├── index.php              # Admin dashboard with stats
│   ├── submissions.php        # Approve/reject pending submissions
│   ├── users.php              # Manage users (view, role change, ban)
│   ├── categories.php         # CRUD categories
│   └── reports.php            # View and resolve reports
├── includes/
│   ├── db.php                 # PDO MySQL connection
│   ├── auth.php               # Session helpers: login(), logout(), is_logged_in(), is_admin(), require_login(), require_admin()
│   ├── functions.php          # Utilities: sanitize(), redirect(), flash(), csrf_token(), verify_csrf()
│   ├── header.php             # HTML head, navbar (with search bar), flash messages
│   └── footer.php             # HTML footer, closing tags
├── assets/
│   ├── css/style.css          # Main stylesheet
│   ├── js/vote.js             # AJAX voting logic
│   └── uploads/avatars/       # User avatar uploads
└── sql/
    └── schema.sql             # Full database creation script
```

## Feature Details

### Authentication
- **Register:** email, username, password. Validates uniqueness. Hashes password with `password_hash()`.
- **Login:** email + password via `password_verify()`. Creates PHP session with user ID and role.
- **Session:** `$_SESSION['user_id']` and `$_SESSION['role']` set on login. Checked by `is_logged_in()` and `is_admin()`.
- **Logout:** `session_destroy()`, redirect to homepage.
- **Login check:** If `is_banned` is true, reject login with a message.
- **Password reset:** Out of scope for MVP. Will be added in a future phase.
- **Error handling:** Validation errors shown inline on forms. Action confirmations (submission created, vote cast, etc.) shown via session-based flash messages rendered in `header.php`.

### Submission Flow
1. Logged-in user navigates to `submit.php`, fills form (title, URL, description, category dropdown)
2. Server validates input, checks URL format, saves with `status = 'pending'`
3. Admin sees pending submissions in `admin/submissions.php`, approves or rejects
4. Approved submissions appear in the public feed and search results

### Voting System
- `vote.php` accepts POST with `submission_id` and `vote_type` (+1 or -1)
- If no existing vote: insert new vote
- If same vote exists: delete it (toggle off)
- If opposite vote exists: update to new vote type
- Returns JSON with new net score for AJAX update
- Only logged-in users can vote; returns 401 otherwise

### Comments
- Flat comments (no threading) displayed on `submission.php`
- Logged-in users see a comment form; posts via standard form POST
- Comments displayed chronologically with username and timestamp
- Users can delete their own comments; admins can delete any comment
- Comments can be reported (uses `reports.comment_id`)

### Top of the Week Dashboard
- Homepage query: approved submissions from the last 7 days
- Ranking formula: `SUM(votes.vote_type) + GREATEST(0, 7 - DATEDIFF(NOW(), submissions.created_at))`
- Recency bonus: newer posts get up to 7 bonus points, decaying by 1 per day. Clamped to 0 minimum (no negative bonus).
- Filter: `WHERE submissions.created_at >= NOW() - INTERVAL 7 DAY AND status = 'approved'`
- Displayed as a featured section at the top of the homepage
- Below: recent approved submissions feed

### Search
- Search bar in the navbar (in `header.php`)
- `search.php` queries `submissions.title`, `description`, `url` using SQL `LIKE %term%`
- Only returns approved submissions
- Results use the same card layout as the main feed

### User Profiles
- `profile.php?id=X` shows public profile: avatar, username, bio, website, member since date
- Lists their approved submissions with vote counts
- `edit-profile.php` lets users update bio, website URL, and upload an avatar
- Avatar upload: validate via `getimagesize()`, max 2MB, jpg/png/gif only, rename to random hash, resize with GD library, store in `assets/uploads/avatars/` (protected by `.htaccess` denying PHP execution)

### Submission Visibility for Submitters
- Users can see their own pending/rejected submissions on their profile page (marked with status badge)
- Only approved submissions are visible to other users

### Category Page
- `category.php?slug=design-tools` displays approved submissions filtered by category
- Same card layout and pagination as the homepage feed
- Category name and description shown at top

### URL Conventions
- Submissions: `submission.php?id=X`
- Categories: `category.php?slug=X`
- Profiles: `profile.php?id=X`
- Search: `search.php?q=term&page=1`

### Report System
- Logged-in users can report a submission via `report.php?submission_id=X` or a comment via `report.php?comment_id=X`
- Report form shows the reported item and a reason text field
- Reports visible in `admin/reports.php` — shows both submission and comment reports
- Admin can view the reported item, read the reason, and mark as resolved or dismissed
- No comment editing for MVP — users can only delete and re-post

### Admin Panel
- **Dashboard:** counts of pending submissions, total users, total submissions, pending reports
- **Submissions:** list pending with approve/reject buttons; filter by status
- **Users:** list all users, change role (user/admin), ban/unban users, view profile
- **Categories:** add, edit, delete categories
- **Reports:** list pending reports, view submission + reason, resolve/dismiss
- All admin pages check `is_admin()` — redirect non-admins

## UI/Styling
- Vanilla CSS (no framework)
- Clean, minimal design
- Card-based layout for submissions (title, URL domain, category badge, vote count, comment count)
- Responsive layout using CSS flexbox/grid
- Navbar: logo, category links, search bar, login/register or username+logout

## Verification Plan
1. Set up XAMPP, create the database using `sql/schema.sql`
2. Register a user, verify login/logout works
3. Submit a link, verify it appears as pending in admin panel
4. Approve the submission, verify it appears on homepage
5. Upvote/downvote — verify toggle and score update
6. Post a comment — verify it appears on submission page
7. Test search — verify it finds approved submissions
8. Edit user profile — verify bio/avatar update
9. Report a submission — verify it appears in admin reports
10. Test Top of the Week ranking with multiple submissions and votes
11. Test all admin CRUD operations (categories, users, submissions, reports)
12. Verify non-logged-in users cannot vote, comment, submit, or report
13. Verify non-admin users cannot access admin pages
14. Test pagination on all list views
15. Test banning a user — verify they cannot log in
16. Test duplicate URL submission — verify it's rejected if an approved submission with same URL exists
17. Test comment deletion — own comments and admin deleting any comment
18. Test adversarial inputs: empty forms, malformed URLs, oversized avatar uploads, XSS in comment body
