# College Football Scoreboard

Every NCAA Division I FBS game for a week, all on one page: scores, live state, kickoff times, TV, records and AP rankings. Rankings never affect which games are shown or their order. See `college-football-scoreboard-spec.md` for the full brief.

Laravel 13, Livewire 4, Tailwind 4, SQLite, with data from [CollegeFootballData](https://collegefootballdata.com) (CFBD).

## Setup

```sh
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate   # if .env doesn't exist yet
touch database/database.sqlite && php artisan migrate
```

Add your key to `.env`:

```env
CFBD_API_KEY=your-key
```

Then check what your key can do and load the season:

```sh
php artisan cfbd:spike --week=3   # ~9 calls; saves raw responses to storage/app/cfbd-spike
php artisan cfbd:sync             # calendar, teams, full-season games, AP rankings (~7 calls)
php artisan serve
```

To keep data current in production, run the scheduler from cron:

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

## Commands

| Command | CFBD calls | Purpose |
| --- | --- | --- |
| `cfbd:sync [--season=]` | ~7 | Full setup: runs the four commands below |
| `cfbd:sync-calendar` | 1 | Season weeks (for week navigation and the current week) |
| `cfbd:sync-teams` | 1 | FBS/FCS teams |
| `cfbd:sync-games [--week=3\|post] [--current] [--scores] [--force]` | 3 (1 with `--scores`) | Schedule, kickoffs, TBDs, networks, final scores |
| `cfbd:sync-rankings` | 1 | Every week's AP Top 25 for the season |
| `cfbd:sync-scoreboard` | 1 | Live status, score, period and clock |
| `cfbd:spike` | ~9 | Checks provider data and the key's tier |

## Sync schedule

Defined in `routes/console.php`. Times are UTC. Page views read only from SQLite and never call CFBD. While games are live, the current week re-reads the database every 60 seconds.

- Calendar and teams: weekly.
- Full-season games: daily, to pick up kickoff time, TBD and TV changes (3 calls).
- AP rankings: Sunday evening, plus Monday as a backup.
- While a game is live or about to start:
  - **Free tier** (`CFBD_LIVE_SCOREBOARD=false`, the default): a one-call refresh of the current week's FBS games every `CFBD_SCORES_INTERVAL` minutes (default 10). Games show as live after kickoff, and final scores arrive within about 10 minutes. CFBD's `/games` has **no in-progress scores**.
  - **Tier 1+** (`CFBD_LIVE_SCOREBOARD=true`): `/scoreboard` every `CFBD_LIVE_INTERVAL` minutes (default 2) for live scores, period and clock. The full current-week refresh runs every 30 minutes.

A final game becomes read-only `CFBD_FREEZE_AFTER_HOURS` (default 36) hours after it ends. Use `--force` to re-sync frozen games.

### API budget

These are rough figures. Check actual usage with `php artisan cfbd:spike`, which reports calls used and remaining.

- **Free tier (1,000 calls/month):** about 25 calls a week for schedules and rankings, plus one call every 10 minutes while games are on. A typical week has about 14 hours of games on Saturday and a few hours on Thursday and Friday, which is about 135 calls. That's roughly 650–700 calls a month in season.
- **With the scoreboard at a 2-minute interval:** about 30 calls an hour during games, or roughly 700 on a full Saturday. Choose a tier whose monthly limit covers that. You can lengthen `CFBD_LIVE_INTERVAL` to reduce it.

## Routes

- `/`: the current week
- `/2026/3`: regular-season week 3
- `/2026/post`: the postseason
- Filters and team search are query strings (`?status=live&conference=SEC&q=georgia`), so every view can be shared and works without JavaScript.

## Tests and code quality

```sh
composer test            # all of the below, in order
composer test:refactor   # rector --dry-run
composer test:lint       # pint --test (includes Blade formatting via Prettier)
composer test:types      # phpstan / Larastan, level 5
composer test:unit       # pest --parallel

composer refactor        # apply Rector
composer lint            # apply Pint
```

The Pint, Rector and Larastan configs mirror `../aw.codes`. Pint's Blade rule needs the Prettier dev dependencies from `npm install`.

Tests use saved CFBD fixtures in `tests/Fixtures/cfbd` and never call the API. The spike's findings and CFBD's data gaps are in `docs/cfbd-provider-notes.md`.
