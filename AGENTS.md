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
- the comment box on the redesigned detail page is currently UI-only; the page displays existing rows from `reviews` but does not implement review submission

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

## Database model discovered from `data/ssssb.sql`
Primary tables:
- `users`
- `movies`
- `genres`
- `movie_genre`
- `country`
- `watchlist`
- `reviews`

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

## Output expectations for code tasks
When completing a task in this repo, the agent should report:
1. what changed
2. which files were edited
3. any database impact
4. any manual verification steps
5. any risky legacy behavior still left unchanged
