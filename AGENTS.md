# AGENTS.md

## Project overview
This repository is a **plain PHP + MySQL/MariaDB movie review/watch website**.
It is **not** a framework-based app (no Laravel, no Composer, no npm build).

The app is split into 2 major areas:
- `users/`: public/user-facing site
- `movies_admin/`: admin management area
- `data/ssssb.sql`: SQL dump for the local database named `review`

The project appears to be designed for local development with:
- PHP running via Apache/XAMPP or PHP built-in server
- MySQL/MariaDB
- phpMyAdmin used during development/export

## Runtime assumptions
Database connection is hardcoded in both:
- `users/cauhinh.php`
- `movies_admin/cauhinh.php`

Current defaults in source:
- host: `localhost`
- user: `root`
- password: empty string
- database: `review`

The SQL dump explicitly references database `review`.

## How to run locally
### Database setup
1. Create a MySQL/MariaDB database named `review`.
2. Import `data/ssssb.sql` into that database.
3. Ensure PHP can connect using the credentials in `cauhinh.php`.

### App setup options
#### Option A: XAMPP / Apache
Place the project under the web root and open:
- `/WebsiteReviewPhim/users/index.php`
- `/WebsiteReviewPhim/movies_admin/index.php`

#### Option B: PHP built-in server
From the project root, run:
```bash
php -S localhost:8000
```
Then open:
- `http://localhost:8000/users/index.php`
- `http://localhost:8000/movies_admin/index.php`

## Architecture and routing
This is a multi-page PHP application using `include`-based routing.

### User site
Main entry:
- `users/index.php`

Routing helper:
- `users/doPage.php`

It loads pages via:
```php
$do = isset($_GET['page_layout']) ? $_GET['page_layout'] : "home";
include $do . ".php";
```
Important consequence:
- many user pages are selected through `page_layout`
- changing filenames or page names can easily break navigation
- validate any routing-related change carefully

Common user pages include:
- `home.php`
- `theloai.php`
- `timkiemphim.php`
- `chitietphim.php`
- `xemphim.php`
- `reviewphim.php`
- `danhsachxemsau.php`
- `nguoidung.php`
- `login.php`
- `register.php`

### Current user homepage UI
The user-facing homepage has been redesigned around:
- `users/index.php`: dark global shell, responsive navbar, search form, user/login actions, footer
- `users/home.php`: landing content after login/default route, including hero banner, genre chips, trending movies, latest movies, ranking sidebar, and optional watchlist section
- `users/stylehome.css`: main stylesheet for the new dark homepage visual system

Important consequence:
- `users/index.php` still wraps all user pages through `users/doPage.php`, so broad changes in `stylehome.css` can affect non-home user pages too
- preserve the existing `page_layout` query-string routing contract when modifying navigation/search links
- homepage movie images still read from `../movies_admin/hinhanhphim/` and DB field `movies.img`
- homepage movie cards now use hover expansion via `.movie-card`, `.movie-hover-details`, `.movie-hover-actions`, and `.movie-hover-badges`; keep these classes together when changing card markup or hover behavior
- the homepage hover "Thich" action links to `page_layout=xemsau&id={movie_id}&iduser={user_id}` when logged in, otherwise it sends the user to `login.php`

### Current movie detail UI
The movie detail page has been redesigned around:
- `users/chitietphim.php`: partial page rendered through `users/index.php?page_layout=chitietphim&id={movie_id}`, with hero backdrop, left poster/info sidebar, action bar, episode tabs, comments panel, related movies, and weekly ranking
- `users/style_detailphim.css`: scoped detail styling under `.movie-detail-page`, plus compatibility rules for legacy pages that still load this stylesheet (`xemphim`, `reviewphim`, `danhsachxemsau`)
- `users/index.php`: conditionally loads `style_detailphim.css` in the document head for `chitietphim`, `xemphim`, `reviewphim`, and `danhsachxemsau`

Important consequence:
- `users/chitietphim.php` now casts `$_GET['id']` to integer before querying and should remain a partial, not a full HTML document, because it is included by `users/doPage.php`
- detail images still use `../movies_admin/hinhanhphim/{movies.img}`
- the detail "Yeu thich" and "Them vao" actions use the existing `page_layout=xemsau&id={movie_id}&iduser={user_id}` flow when logged in, otherwise redirect to `login.php`
- the detail comment form now submits to `users/xuly_review.php` with hidden field `return_page=chitietphim`, so create/update review actions redirect back to the detail page instead of the watch page
- each logged-in user currently has one effective review per movie at the code level: if a matching `movie_id + user_id` review exists, the handler updates it instead of inserting a duplicate
- detail review UI uses the existing `reviews.rating`, `reviews.comment`, and `reviews.review_date` fields with a 1-5 star scale
- public review stats/list on the detail page only include rows where `reviews.is_hidden = 0`; a logged-in user can still load their own latest review into the form even if admin has hidden it

### Current user watch movie UI
The user watch page has been redesigned around:
- `users/xemphim.php`: partial page rendered through `users/index.php?page_layout=xemphim&id={movie_id}`, with breadcrumb/back row, large player card, dark action bar, comment area on the left, right recommendation/info sidebar, and mobile mini bottom nav
- `users/style_detailphim.css`: watch styles are scoped under `.watch-page`, plus `body.page-xemphim .site-footer` for the shared footer treatment
- `users/index.php`: still wraps the page via `users/doPage.php`, now adds `page-{page_layout}` to `<body>` so watch-page-specific shell styling can target the shared footer safely

Important consequence:
- `users/xemphim.php` now casts `$_GET['id']` to integer and uses prepared statements for the touched movie/genre/review/watchlist/recommendation queries
- the route/query string stays `users/index.php?page_layout=xemphim&id={movie_id}`
- video source is resolved from `movies.link1` then `movies.link2`; valid YouTube ids/URLs render as `<iframe>`, direct `.mp4/.webm/.ogg` links render as `<video controls>`, invalid placeholder values fall back to a poster placeholder state
- poster/fallback artwork still reads from `../movies_admin/hinhanhphim/{movies.img}`
- watchlist actions still use the existing `page_layout=xemsau&id={movie_id}&iduser={user_id}` flow when logged in, otherwise send the user to `login.php`
- the page preserves the old login gate behavior by storing `$_SESSION['redirect']` for the watch URL; unauthenticated users see a styled locked-player state instead of the raw legacy message
- comments display real rows from `reviews` joined with `users`, and the comment/rating form now submits to `users/xuly_review.php`
- the watch review form currently posts `movie_id`, `rating`, and `comment`; the shared handler creates or updates the existing review for the logged-in `user_id`
- public review stats/list on the watch page only include rows where `reviews.is_hidden = 0`; hidden reviews stay editable by their owner through the shared review form but do not render publicly
- recommendation sidebar prefers movies from the first matched genre via `movie_genre`, excludes the current movie, and falls back to highest-view/latest movies if no genre-based matches are found
- the lightweight inline JS on `users/xemphim.php` only handles comment character counting, share/copy-link behavior, HTML5 skip-intro behavior, and the existing delayed view-count update call to `users/update_view_count.php`
- the sidebar "Dien vien" area is currently an information placeholder because the schema has no actor table; real movie metadata from `movies`, `country`, and `genres` is shown instead

### Current auth UI
The auth pages have been redesigned around:
- `users/login.php`: standalone login page with poster-wall background, dark glass panel, preserved `action="xuly_login.php"` login form, register link, and forgot-password link
- `users/register.php`: standalone register page using the same poster-wall / dark panel visual system while preserving existing field names and submit target `action="xuly_register.php"`
- `users/style_users.css`: shared auth stylesheet controlling backdrop collage, overlay, centered form panel, register grid layout, radio card, and responsive behavior

Important consequence:
- `users/login.php` is a standalone full HTML document and does not use `users/index.php`
- `users/register.php` is also standalone and does not use `users/index.php`
- the login form still submits only `email` and `password` to `users/xuly_login.php`; UI changes should not alter those field names unless the handler is updated too
- the register form still submits `fullname`, `username`, `password`, `passwordxacnhan`, `email`, and `myRadio` to `users/xuly_register.php`; those names must stay aligned with the current backend and `users/javascript_register.js`
- the current forgot-password link still points to `users/forgot_password.php`, but the backend handler remains incomplete as noted below

### Admin site
Main entry:
- `movies_admin/index.php`

Routing helper:
- `movies_admin/doPage.php`

Admin features include:
- movie management
- genre management
- movie-genre mapping
- user management
- country management

### Current admin genre UI
The admin genre management screen has been redesigned around:
- `movies_admin/list_theloai.php`: main dark admin genre page with sidebar, search, KPI cards, modern table, and right-side add/edit drawer
- `movies_admin/style_admin.css`: shared admin styling plus scoped genre classes such as `.genre-page`, `.genre-layout`, `.genre-table`, and `.genre-drawer`
- inline JS inside `movies_admin/list_theloai.php`: slug preview generation, color swatch selection, and delete confirmation

Important consequence:
- search/filter uses `index.php?page_layout=list_theloai&keyword=...`
- drawer state uses query params, primarily `drawer=add` and `drawer=edit&id={theloai_id}`
- legacy routes `movies_admin/themtheloai.php` and `movies_admin/capnhattheloai.php` now redirect into the new drawer-based UI instead of rendering standalone forms
- add still submits `txtTenQuocGia` to `movies_admin/xuly_themtheloai.php`, edit still submits `txthoten` to `movies_admin/xulycapnhattheloai.php`, so old handler field names remain intact
- slug, color, and description are UI preview only; they are not stored in the database because `genres` currently has only `theloai_id` and `ten_theloai`
- per-genre movie counts and KPI metrics are derived from `movie_genre`
- deleting a genre now checks `movie_genre` first in `movies_admin/xuly_xoatheloai.php`; if mappings exist, deletion is blocked and the admin must remove mappings before deleting the genre

### Current admin movie-genre mapping UI
The admin movie-genre mapping screen has been redesigned around:
- `movies_admin/list_theloai_phim.php`: main dark admin mapping page with sidebar, topbar, movie search, left movie picker, selected movie preview, genre chip area, genre grid, and inline POST save handler
- `movies_admin/style_admin.css`: shared admin styling plus mapping classes such as `.mapping-page`, `.mapping-layout`, `.movie-picker-card`, `.movie-picker-item`, `.selected-movie-card`, `.genre-assignment-card`, `.selected-genre-chip`, `.genre-grid`, and `.mapping-save-bar`
- lightweight inline JS inside `movies_admin/list_theloai_phim.php`: keeps selected genre chips/count in sync, removes chips, and confirms when saving an empty genre selection

Important consequence:
- the screen is routed by `movies_admin/index.php?page_layout=list_theloai_phim`
- movie selection uses `movie_id` in the query string; if absent, the first movie in the current filtered list is selected
- movie search uses `keyword`, optional country filter uses `country_id`, and pagination uses `page`
- the save form posts back to `movies_admin/list_theloai_phim.php` itself with `mapping_action=save_movie_genres`
- mapping is stored in table `movie_genre` using columns `movie_id` and `theloai_id`
- saving validates the target movie exists in `movies`, validates every posted genre id exists in `genres`, deletes old rows in `movie_genre` for that `movie_id`, then inserts the new set without duplicates
- legacy routes `movies_admin/themtheloai_phim.php` and `movies_admin/capnhattheloai_phim.php` now redirect into the redesigned mapping screen instead of rendering old standalone forms

### Current admin account/user UI
The admin account management screen has been redesigned around:
- `movies_admin/list_user.php`: main dark admin user page with sidebar, topbar, KPI cards, role/status filters, paginated account table, floating add button, and links to the existing add/edit/delete flows
- `movies_admin/style_admin.css`: shared admin styling plus account classes such as `.account-page`, `.account-overview-grid`, `.account-filter-bar`, `.account-table`, `.user-avatar`, `.role-badge`, `.status-dot`, `.security-button`, and `.floating-add-button`
- no extra JS file is required; delete still uses inline confirm on the action link

Important consequence:
- search uses query param `keyword`
- role filter uses query param `role` with values `all`, `1`, or `0`
- pagination uses query param `page`
- the status dropdown uses query param `status`, but it is currently UI-only because table `users` has no status column
- role mapping remains `1 = admin`, `0 = user`
- the account table must never render `users.password`; only user id, profile fields, role, and aggregate counts are shown
- reviews count is derived from table `reviews` by `user_id`, and watchlist count is derived from table `watchlist` by `user_id`
- add/edit/delete links still follow the legacy routes `page_layout=them_user`, `page_layout=capnhattaikhoan&id={user_id}`, and `page_layout=xuly_xoauser&id={user_id}`
- add/edit forms still rely on input names `fullname`, `username`, `password`, `passwordxacnhan`, `email`, and `myRadio`; update now allows blank password fields to keep the current stored password unchanged
- deleting a user now removes related rows in `reviews` and `watchlist` before deleting the `users` row to avoid orphan data in the MyISAM schema

### Current admin add/edit account UI
The admin add/edit account forms are now redesigned around:
- `movies_admin/them_user.php`: dark admin add-account screen with shared sidebar/topbar, two-column form layout, role selector cards, security note, and sticky action bar
- `movies_admin/capnhattaikhoan.php`: dark admin edit-account screen with the same layout, prefilled profile fields, blank-only password change section, account metadata panel, and empty-state fallback when the user id is invalid
- `movies_admin/style_admin.css`: shared admin styling plus form classes such as `.account-form-page`, `.account-form-layout`, `.account-form-card`, `.role-option-card`, `.security-note`, `.account-info-list`, and `.sticky-action-bar`

Important consequence:
- the add form still submits to `movies_admin/xuly_themuser.php`
- the edit form still submits to `movies_admin/xuly_capnhatuser.php?id={user_id}`
- input names that must stay aligned with the handlers are `fullname`, `username`, `password`, `passwordxacnhan`, `email`, `myRadio`, and submit name `btndangky`
- role mapping remains `1 = admin`, `0 = user`
- the existing plaintext `users.password` model is unchanged in this task; no hashing migration was introduced
- old passwords must never be prefilled or rendered into HTML on the edit screen
- `movies_admin/xuly_capnhatuser.php` now keeps the current stored password unchanged when both password inputs are blank, and only updates `users.password` when a new password is provided
- `movies_admin/xuly_themuser.php` and `movies_admin/xuly_capnhatuser.php` were updated to validate email, validate role values, and enforce username/email uniqueness with prepared statements
- no database schema changes were made

### Current admin review management UI
The admin review management screen is currently organized around:
- `movies_admin/list_review.php`: main dark admin review page with sidebar, topbar search, heading actions, KPI cards, filter bar, review table, and pagination
- `movies_admin/style_admin.css`: shared admin styling plus review classes such as `.review-page`, `.review-stats-grid`, `.review-filter-bar`, `.review-table`, `.review-status-badge`, and `.review-pagination`
- lightweight inline JS inside `movies_admin/list_review.php`: only toggles the filter panel open/collapsed state

Important consequence:
- the screen is routed by `movies_admin/index.php?page_layout=list_review`
- search uses query param `keyword`, rating filter uses `rating`, visibility filter uses `status` with values `hien` or `an`, movie filter uses `movie_id`, and pagination uses `page`
- review rows are loaded from `reviews` joined with `movies`, `users`, and `country`; movie genres are derived by `movie_genre -> genres`
- KPI totals use `COUNT(*)` from `reviews`, `COUNT(*) WHERE reviews.is_hidden = 0`, `COUNT(*) WHERE reviews.is_hidden = 1`, and `AVG(reviews.rating)` over visible rows for the public-facing average score
- review visibility is stored for real in `reviews.is_hidden`; approve/duyet and violation-report logic do not exist in the current schema and are not rendered as fake actions
- admin can hide/show a review through `movies_admin/xuly_anreview.php` via `page_layout=xuly_anreview&id={review_id}&visibility={an|hien}`, and hidden reviews are removed from public review lists/stats on the user site
- delete review is real and handled by `movies_admin/xuly_xoareview.php` via `page_layout=xuly_xoareview&id={review_id}` with confirm + redirect back to the list
- fields that must stay aligned with user-side review flows are `reviews.review_id`, `reviews.movie_id`, `reviews.user_id`, `reviews.rating`, `reviews.comment`, `reviews.review_date`, and `reviews.is_hidden`

### Current admin country UI
The admin country management screen has been redesigned around:
- `movies_admin/themquocgia.php`: main dark admin country page with search, KPI summary, modern country table, pagination, and centered add/edit modal rendered on the same route
- `movies_admin/style_admin.css`: shared admin styling plus country classes such as `.country-page`, `.country-table`, `.country-avatar`, `.country-count-pill`, `.country-modal-overlay`, `.country-modal`, and `.country-upload-box`
- lightweight inline JS inside `movies_admin/themquocgia.php`: only handles toast auto-hide; modal open/close is driven by query params

Important consequence:
- search uses query param `keyword`
- pagination uses query param `page`
- add/edit modal state uses query param `modal` (`add` or `edit`) and edit record selection uses `id`
- add, edit, and delete all submit back to `movies_admin/themquocgia.php` with POST field `country_action`
- the only field stored in table `country` is `txtTenQuocGia`, which maps to column `country_name`
- ISO code and flag upload are currently UI placeholders only; they are not stored because table `country` only has `country_id` and `country_name`
- movie count per country is derived from table `movies` by `country_id`
- deleting a country first checks `movies.country_id`; if any movie still references that country, deletion is blocked
- the legacy helper route `movies_admin/xuly_themquocgia.php` now redirects back to the redesigned `page_layout=themquocgia` screen

## Database model discovered from `data/ssssb.sql`
Primary tables:
- `users`
- `movies`
- `genres`
- `movie_genre`
- `country`
- `watchlist`
- `reviews`

Current moderation field used by review management:
- `reviews.is_hidden`: `0 = hien`, `1 = an`

### Important relationships
- `movies.country_id -> country.country_id`
- `movie_genre.movie_id -> movies.movie_id`
- `movie_genre.theloai_id -> genres.theloai_id`
- `watchlist.movie_id -> movies.movie_id`
- `watchlist.user_id -> users.user_id`
- `reviews.movie_id -> movies.movie_id`
- `reviews.user_id -> users.user_id`

### Current table-engine reality
The SQL dump uses **MyISAM** for tables, not InnoDB.
That means:
- no foreign key enforcement by default
- deletes can leave orphan records if not handled manually
- be careful when deleting movies, users, genres, or countries

## Authentication and authorization
### Sessions
The app uses PHP sessions.
Common session keys observed:
- `username`
- `user_id`
- `email`
- `role`
- `fullname`
- optionally `redirect`

### Roles
Role values inferred from source/data:
- `0` = normal user
- `1` = admin

Admin protection is handled in:
- `movies_admin/checkpermission.php`

If session is missing or role is `0`, admin pages redirect to `../users/login.php`.

## Key business behaviors already present
### User-side
- browse movies
- filter by genre
- search movies by title/content
- view movie details
- watch movie pages
- add/remove watchlist entries
- increment view counts
- register/login/logout
- change password from user profile

### Admin-side
- create/update/delete movies
- create/update/delete genres
- map movies to genres
- create/update/delete users
- create countries

## Files with high change risk
Be careful when editing these because they affect many flows:
- `users/index.php`
- `users/doPage.php`
- `users/cauhinh.php`
- `users/xuly_login.php`
- `users/xuly_register.php`
- `movies_admin/index.php`
- `movies_admin/doPage.php`
- `movies_admin/checkpermission.php`
- `movies_admin/cauhinh.php`
- `movies_admin/xuly_themfilm.php`
- `movies_admin/xuly_capnhatfilm.php`

## Known code and security issues
The agent should assume this codebase is **legacy/student-style PHP** and improve it carefully without breaking behavior.

### 1. Raw SQL string concatenation is used widely
Examples exist across both `users/` and `movies_admin/`.
This means the app is currently vulnerable to SQL injection in many places.

**Rule:**
- Prefer `mysqli` prepared statements for any touched query.
- If refactoring one query, do not silently change unrelated business logic.

### 2. Passwords are stored and compared in plaintext
Observed in:
- `users/xuly_login.php`
- `users/xuly_register.php`
- `movies_admin/xuly_themuser.php`
- `movies_admin/xuly_capnhatuser.php`
- database dump sample data

**Rule:**
- Do not partially migrate only one login/register path unless all relevant flows are updated consistently.
- A proper migration should use `password_hash()` and `password_verify()` and consider existing plaintext users.

### 3. Dynamic file inclusion exists
Observed in:
- `users/doPage.php`
- `movies_admin/doPage.php`

**Rule:**
- Do not expand this mechanism casually.
- Prefer allowlists if this area is refactored.
- Avoid filename changes unless all links are updated.

### 4. Missing/unfinished forgot-password backend
`users/forgot_password.php` posts to:
- `xuly_forgot_password.php`

That handler was **not found** in this repository.

**Rule:**
- If implementing forgot-password, do not fake success.
- Add a real handler and email/reset-token flow, or clearly disable/remove the broken entry point.

### 5. Deletes may leave orphaned rows
Because the schema uses MyISAM and there are manual deletes like:
- deleting movies
- deleting users
- deleting genres

**Rule:**
- When deleting parent entities, check related tables manually:
  - `movie_genre`
  - `watchlist`
  - `reviews`
- Avoid introducing data inconsistency.

### 6. File/image handling is simplistic
Movie image values are often treated as string filenames. The admin area also contains image folders.

**Rule:**
- Before changing upload logic, verify whether the app expects:
  - plain filename strings in DB
  - existing files under `movies_admin/hinhanhphim/`
  - static references from user pages

## Coding conventions to preserve
This codebase has informal naming and Vietnamese labels. Preserve consistency unless explicitly asked to modernize broadly.

### Preserve these patterns unless refactoring intentionally
- procedural PHP style
- page-per-feature structure
- `include_once "cauhinh.php"`
- redirect via `header("Location: ...")`
- user-visible alerts via inline `<script>alert(...)</script>`
- variable names in Vietnamese mixed with English

### When making improvements
- keep changes minimal and local
- avoid introducing frameworks
- avoid adding Composer dependencies unless explicitly requested
- prefer incremental hardening over full rewrites

## Safe refactoring priorities
If asked to improve the project, the safest order is:
1. fix obvious bugs without changing UI flow
2. add input validation
3. convert touched queries to prepared statements
4. centralize repeated DB/session helpers
5. improve password handling with a backward-compatible migration plan
6. only then consider larger structural cleanup

## Testing checklist
This project has no automated tests. Use manual verification.

After any meaningful change, check at least:
- homepage loads
- genre links still work
- search still returns results
- movie detail page still loads
- watch page still loads
- login works for user role
- admin login works for admin role
- admin movie list loads
- add/edit/delete movie still works
- add/edit/delete genre still works
- watchlist add/remove still works

If authentication or user updates were changed, also verify:
- registration
- logout
- password change
- redirection after login

## Important implementation notes for future edits
### Movie links
Movie records contain fields like:
- `link1`
- `link2`
- `img`

Do not rename/remove these without updating all pages that render movie details or playback.

### Search and page layout
The user index routes search using `page_layout=timkiemphim`.
Do not break this query-string contract.

### Session-dependent pages
Some features depend on `$_SESSION['user_id']` and role checks.
Do not change session key names unless all usages are updated.

## What the agent should do when asked to change code
### Preferred workflow
1. Inspect the exact page + handler + SQL involved.
2. Keep existing redirects and user messages unless asked otherwise.
3. If touching DB logic, review the corresponding schema in `data/ssssb.sql`.
4. If touching auth, review both user-facing and admin-facing flows.
5. Summarize changed files and any manual setup steps.

### When unsure
Check both locations before assuming duplication is intentional:
- `users/cauhinh.php`
- `movies_admin/cauhinh.php`

Many behaviors are duplicated between public and admin areas.

## Recommended modernization path (only when explicitly requested)
A larger cleanup could move toward:
- shared config file for DB/session helpers
- prepared statements everywhere
- password hashing
- CSRF protection for forms
- upload validation and safer file handling
- route allowlist instead of unrestricted include
- InnoDB + foreign keys
- consistent folder naming and coding style

Do not do all of this at once unless the task explicitly asks for a broad refactor.

## Installed Codex skill
### Impeccable
The Codex skill `impeccable` has been installed globally for frontend/UI work.

Use it when the task is about:
- redesigning or polishing frontend pages/components
- critiquing UX or visual hierarchy
- improving typography, spacing, layout, color, motion, responsiveness, or UX copy
- auditing bland or inconsistent UI in files such as `users/style_users.css`, `users/stylehome.css`, `users/style_detailphim.css`, `users/index.php`, `users/home.php`, and `users/chitietphim.php`

Invocation pattern:
- `/impeccable`
- `/impeccable audit <target>`
- `/impeccable critique <target>`
- `/impeccable polish <target>`
- `/impeccable layout <target>`
- `/impeccable typeset <target>`
- `/impeccable colorize <target>`
- `/impeccable adapt <target>`

Important usage notes:
- restart Codex after installation so the skill is loaded
- best results require `PRODUCT.md` at project root
- `DESIGN.md` is optional but strongly recommended for more on-brand output
- for this repo, use `impeccable` as a design aid while still preserving existing PHP routing, form field names, session keys, and established page flow unless the task explicitly asks for a broader redesign
- when applying `impeccable` suggestions here, keep compatibility with the current homepage/detail/auth visual systems already documented above

## Output expectations for code tasks
When completing a task in this repo, the agent should report:
1. what changed
2. which files were edited
3. any database impact
4. any manual verification steps
5. any risky legacy behavior still left unchanged
