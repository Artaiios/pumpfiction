# Changelog

All notable changes to Pumpfiction (Tracking Edition).

## [1.0.1] – 2025-04-18

### Fixed
- **Dashboard reload bug** — Values entered during a session would disappear after page reload. Root cause: date handling was client-side only (JavaScript), so the server always rendered "today" regardless of the date toggle. Now fully server-side via `?date=yesterday` query parameter.
- **Timezone mismatch** — PHP used `Europe/Berlin` (UTC+2 in summer) while MySQL was hardcoded to `+01:00` (UTC+1). MySQL timezone is now synced dynamically from PHP on every connection: `(new DateTime())->format('P')`.
- **Aggressive caching** — Added `Cache-Control: no-cache, no-store, must-revalidate` headers to all API endpoints and the dashboard to prevent stale data on shared hosting.
- **Duplicate seed data** — Re-running `install.php` would insert challenges, badges, and quotes a second time. The installer now checks `SELECT COUNT(*) FROM challenges` before seeding — only inserts if tables are empty.
- **Credential overwrite on update** — Database credentials were stored inline in `config.php`, so deploying a new version would blank them out. Credentials now live in a separate `includes/db_credentials.php` that the installer writes once and app updates never touch.

### Added
- **Value reset** — Orange ↺ button on each challenge card to reset the day's value (with confirmation dialog). Works for both number and yes/no challenges.
- **Info page** (`info.php`) — Onboarding guide shown after first registration, explains challenges, voting system, XP/levels, streaks, badges, and the Wall. Accessible anytime from the profile.

### Changed
- **Color scheme** — Replaced dark purple/navy backgrounds (`#1a1a2e`, `#0f0f13`) with neutral dark grays (`#1c1c1f`, `#111113`, `#141416`). Same neon accents, less "gaming UI", more professional feel.

## [1.0.0] – 2025-04-02

### Initial Release
- 10 default daily challenges (steps, push-ups, plank, water, no alcohol, clean eating, meditation, sleep, intermittent fasting, cold shower)
- XP and leveling system (11+ levels, "Couch Potato" to "Unstoppable")
- 30 badges with varied unlock conditions
- Streak tracking with milestone rewards
- Wall of Shame & Fame with auto-generated messages
- Democratic monthly voting (keep/remove challenges, adjust targets, propose new ones)
- Personal milestone goals
- Leaderboard (weekly, monthly, yearly, all-time)
- Statistics with Chart.js trend lines, heatmap, success rate donuts
- 60 context-aware motivation quotes
- Admin panel at `/theboss.php`
- Cookie-based auth (nickname + 4-digit PIN)
- Mobile-first dark theme with Tailwind CSS
- Installation wizard
