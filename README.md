# CommunityHub

A lightweight PHP/MySQL community incident board with map-based browsing, filters, notifications, and status updates.

## Features
- Interactive map with clustered markers and filters (type, status, severity, search, location).
- Feed and profile views; recent activity auto-loads after login.
- Item status workflow: reported → in_progress → resolved → closed (owner-only updates).
- Notifications panel with nearby updates polling.
- Rewards page (coming soon) with email capture UI (backend endpoint pending).
- Auth flows with login/register, OTP, password reset, and Google OAuth.

## Tech Stack
- PHP 8+, MySQL (PDO), Leaflet for maps, vanilla JS/HTML/CSS.
- Composer vendors: Google API client, firebase/php-jwt, guzzle, psr7, monolog, phpseclib, etc.

## Getting Started (Local)
1) Prereqs: PHP 8+, MySQL 8+, Composer (vendors already in `vendor/`).
2) Copy project into your web root (e.g., XAMPP `htdocs`).
3) Create a database and import `communityhub.sql`.
4) Configure DB/app secrets in `auth/config.php` (host, port, db name, user, pass, JWT/OTP/email keys).
5) Ensure `auth/db.php` uses the same credentials or `.env` if you add one.
6) Serve via Apache/PHP or `php -S localhost:8000` from the project root; open `index.html` or `dashboard.html` after login.

### Deployment Notes
- If your MySQL user lacks `TRIGGER` privilege, move POINT updates into application queries: set `location_point = POINT(location_lng, location_lat)` on inserts/updates where both coords exist.
- Secure `auth/config.php` and any `.env` outside the web root; never commit secrets.
- Enable HTTPS and set proper `session.cookie_secure`/`SameSite` in production.

## Core Endpoints (api/)
- `get-items.php` — returns items with filters (type, status, severity, search, location) and map-friendly data.
- `create-item.php` — creates a new item (requires auth).
- `update-status.php` — owner-only status change for an item.
- `get-feed.php`, `get-items.php` — list items for feed/map.
- `user-data.php`, `check-session.php` — session info helpers.

## Frontend Pages
- `index.html` / `login.html` / `register.html` — auth entry points.
- `dashboard.html` + `dashboard.js` + `dashboard.css` — map, feed, filters, notifications, status actions.
- `profile.html` + `profile.js` + `profile.css` — user profile.
- `rewards.html` + `rewards.css` — rewards teaser page.

## Status & Filters
- Status options: reported, in_progress, resolved, closed.
- Filters: type, status, severity, search term, location radius; search supports chips and map tab search box.

## Security & Hardening Checklist
- Add stricter rate limits on login/OTP endpoints.
- Validate/sanitize all user input server-side; return consistent 401/403 responses.
- Add CSRF tokens for any non-AJAX form posts.
- Consider structured logging (request ID, user ID, endpoint, duration) and a healthcheck endpoint.

## Contributing
- Fork, branch, and open PRs. Avoid committing secrets. Run formatting/linting as applicable.
- Open issues with repro steps and environment details.

## License
Specify your license of choice (MIT/Apache-2.0/etc.).
