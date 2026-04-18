# 💪 Pumpfiction (Tracking Edition)

A fitness challenge tracker with gamification for small friend groups (~10 people). Built to keep each other accountable, motivated, and maybe a little bit ashamed.

**Tagline:** *Motion Lock – Pumpfiction (Tracking Edition)*

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=flat-square) ![MySQL](https://img.shields.io/badge/MySQL-MariaDB-4479A1?style=flat-square) ![License](https://img.shields.io/badge/license-MIT-green?style=flat-square)

---

## What is this?

Pumpfiction started because we wanted something simple: a way to track daily fitness challenges as a group, see who's crushing it, and give each other a hard time when someone slacks off.

There are 10 daily challenges (steps, push-ups, plank, water, clean eating, etc.), a full XP/leveling system, 30 badges to unlock, a streak tracker, and a "Wall of Shame & Fame" that auto-generates trash-talk messages. Think of it as a mix between a habit tracker and a friendly competition board.

The kicker: challenges aren't set in stone. Every month, the group votes democratically on what stays, what goes, and what gets added. Don't like 70 push-ups? Vote for 50. Want to add "Read 30 minutes"? Propose it.

## Features

**Daily Tracking** — Quick-add buttons, additive values throughout the day, yesterday backfill. Two taps to log your stuff.

**Gamification** — XP for everything (daily goals, perfect days, streaks, milestones). 11+ levels from "Couch Potato" to "Unstoppable". 30 badges with creative conditions.

**Streaks** — Consecutive days with all challenges completed. Milestone rewards at 7, 14, 30, 60, 90, 180, and 365 days. Lose your streak and the Wall will let everyone know.

**Democratic Voting** — Monthly cycle. Vote to keep/remove challenges, adjust daily targets (median of all proposals), or propose entirely new challenges. Final phase lock in the last 3 days.

**Wall of Shame & Fame** — Auto-generated feed. Level ups, badge unlocks, and streak milestones go on Fame. Lost streaks, getting overtaken on the leaderboard, and procrastination badges go on Shame. All in good fun.

**Statistics** — Trend charts per challenge (your values vs. group average), GitHub-style activity heatmap, weekday performance breakdown, lifetime counters, success rate donuts.

**Personal Milestones** — Set your own lifetime goals ("5,000 push-ups total") and track progress independently.

**Leaderboard** — Weekly, monthly, yearly, all-time. XP-based ranking with success rate, streak display, and weekly winner highlight.

**Admin Panel** — Hidden at `/theboss.php`. Manage users, override challenges, force voting evaluation, moderate the wall.

## Tech Stack

This runs on the simplest possible stack. No frameworks, no build tools, no package managers.

- **Backend:** PHP 8.x (vanilla, no Composer)
- **Database:** MySQL / MariaDB
- **Frontend:** HTML5, Tailwind CSS (CDN), vanilla JavaScript (ES6+)
- **Charts:** Chart.js (CDN)
- **Font:** Outfit (Google Fonts CDN)
- **Hosting:** Designed for shared hosting (tested on IONOS)

Everything is in a single flat directory. Upload it, run the installer, done.

## Getting Started

### 1. Upload

Upload the `pumpfiction/` folder to your web server.

### 2. Install

Open `https://your-domain.com/pumpfiction/install/install.php` in your browser. Enter your MySQL credentials. The installer creates the database schema, seeds the initial challenges/badges/quotes, and writes the credentials to `includes/db_credentials.php`.

### 3. Register

Go to `https://your-domain.com/pumpfiction/` and create your account (just a nickname, 4-digit PIN, and avatar — no email required).

### 4. Share

Send the link to your friends. That's it.

## Updating

The database credentials live in `includes/db_credentials.php` — a separate file that the installer writes once. When updating, you can overwrite everything *except* that file. No need to re-run the installer.

If you do accidentally overwrite it or need to re-run the installer, the seed data (challenges, badges, quotes) will only be inserted if the tables are empty. No more duplicates.

## Project Structure

```
pumpfiction/
├── index.php                  # Login / Registration
├── dashboard.php              # Main tracking interface
├── leaderboard.php            # Rankings
├── stats.php                  # Charts, heatmap, lifetime stats
├── wall.php                   # Wall of Shame & Fame
├── voting.php                 # Monthly democratic voting
├── profile.php                # Settings, badges, milestones
├── info.php                   # How-to guide (shown on first login)
├── theboss.php                # Admin panel (secret URL)
├── api/                       # AJAX endpoints
│   ├── log_entry.php          # Log/reset challenge values
│   ├── get_stats.php          # Chart data
│   ├── vote.php               # Voting actions
│   ├── propose.php            # New challenge proposals
│   ├── milestones.php         # Personal goals CRUD
│   ├── profile.php            # Profile updates + logout
│   └── admin.php              # Admin stats
├── includes/
│   ├── config.php             # App config, DB connection, constants
│   ├── db_credentials.php     # DB credentials (auto-generated, git-ignored)
│   ├── auth.php               # Authentication, cookies, CSRF
│   ├── functions.php          # Core helpers, data access, formatting
│   ├── gamification.php       # XP, levels, badges, streaks engine
│   ├── wall_events.php        # Auto-generated wall messages
│   ├── voting_logic.php       # Voting system logic
│   └── nav.php                # Navigation component
├── assets/
│   ├── css/style.css          # Custom styles (extends Tailwind)
│   └── js/
│       ├── app.js             # Toasts, confetti, AJAX helpers
│       ├── charts.js          # Chart.js defaults
│       └── animations.js      # Micro-interactions
├── install/
│   ├── install.php            # Setup wizard
│   ├── schema.sql             # Database schema
│   └── seed.sql               # Default data
└── .htaccess                  # Security headers, directory protection
```

## The 10 Default Challenges

| Challenge | Type | Daily Target |
|-----------|------|-------------|
| Steps | Number | 10,000 |
| Push-Ups | Number | 70 |
| Plank | Number | 180 sec |
| Water | Number | 3,000 ml |
| No Alcohol | Yes/No | ✓ |
| Clean Eating | Yes/No | ✓ |
| Meditation/Stretching | Number | 15 min |
| Sleep | Number | 7 hours |
| Intermittent Fasting | Number | 12 hours |
| Cold Shower | Yes/No | ✓ |

All of these can be adjusted, removed, or replaced through monthly voting.

## Design

Dark theme with neutral grays and neon green/electric blue accents. Mobile-first, works fine on desktop too. The UI is built for speed — logging a value is two taps from the dashboard.

## Security

This is a fun project for friends, not a banking app. That said:

- PINs are hashed (`password_hash` / `password_verify`)
- All queries use prepared statements (PDO)
- CSRF tokens on all write operations
- HttpOnly, SameSite cookies
- Output escaping everywhere
- `.htaccess` protects sensitive directories

## Language

The app interface is in **German**. All challenge names, UI text, motivation quotes, and wall messages are in German. The codebase (comments, variable names) is in English.

## Contributing

This was built for a specific friend group, but feel free to fork it and adapt it to your own crew. If you find bugs or have improvements, PRs are welcome.

## License

MIT — do whatever you want with it.
