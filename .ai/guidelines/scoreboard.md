# College Football Scoreboard

A small, personal Laravel app that shows **every NCAA Division I FBS game in a week**: scores, live state, kickoff times, TV, records and AP rankings. The full brief is `college-football-scoreboard-spec.md`. Provider findings are in `docs/cfbd-provider-notes.md`.

Before adding anything, ask: *does this make it easier to see what happened in college football this week?* If not, it probably doesn't belong.

## Product rules (non-negotiable)

- Every FBS game matters equally. **Rankings, popularity or "importance" must never decide which games are shown, hidden or ordered.** No Top 25 default view, featured games or editorial prioritization.
- The complete week is the product. `All` is the default. Filters (status, conference, team search) narrow the view but never change the underlying week, and the summary counts always cover the whole week.
- Games are ordered chronologically by kickoff. TBD kickoffs go last on their day, and the provider id breaks ties. Never move ranked games up.
- Show FBS vs. FCS games. Hide games that have no FBS team (`Game::scopeFbs`).
- Team rows are always `Team Name (Record) #Rank ... Score`. The team name is leftmost; the rank comes after the record and only when the team is ranked. Never reserve space for a rank or indent ranked teams.
- The AP Top 25 is the only scoreboard ranking. `#4` implicitly means AP #4. Don't switch to CFP rankings mid-season.
- Keep it deliberately small. Don't add accounts or auth, social features, news, betting, fantasy, ads, notifications, an admin panel, a public API, Redis or elaborate caching unless the developer asks.

## Data flow

```
CFBD API → app/Cfbd/CfbdClient → app/Cfbd/Data DTOs → app/Sync/* → Eloquent → app/Support/WeekScoreboard → Livewire
```

- **Only `App\Cfbd\CfbdClient` talks to CollegeFootballData.** Everything leaving it is a DTO in `app/Cfbd/Data`. Raw CFBD payload keys must never appear in models, sync classes or views. Normalize provider quirks in the DTOs (e.g. outlet aliases in `GameMediaData::ALIASES`, pipe-joined scoreboard TV).
- Don't build a generic multi-provider abstraction.
- **Page views never call CFBD.** The UI reads only from SQLite. Upstream traffic comes solely from the scheduled `cfbd:*` commands in `routes/console.php`. Browser polling (`wire:poll`) only re-reads the local database.
- Syncs must survive outages. A `CfbdException` leaves existing data untouched, an invalid record is skipped and logged, a failed media fetch keeps the stored networks, and an empty rankings response doesn't wipe history.
- Status ownership: `/games` is the schedule of record and confirms finals. `/scoreboard` owns live status, score, period and clock. Never downgrade a live or final game to scheduled from scoreboard data. Final games freeze `CFBD_FREEZE_AFTER_HOURS` after completion.

## Domain rules

- **Rankings belong to a season and week, never to the team.** The week N view uses the latest AP poll numbered ≤ N (CFBD poll N = the ranking going into week N; week 1 = preseason). Postseason views use the last regular-season poll.
- **Records are calculated** from stored final games through the viewed week (`WeekScoreboard::loadRecords`), not from CFBD's `/records`, which only gives current totals. Show the overall record only.
- Game conference and classification are stored per game, because teams realign. Don't read the team's current conference for historical games.
- Week date ranges come from actual FBS kickoffs (`season_weeks.first_game_at`/`last_game_at`, filled by the games sync). CFBD's calendar game-time fields are just the week's boundaries.
- Kickoff times are displayed in `config('app.display_timezone')` (America/New_York by default) and stored in UTC.

## API budget

- The key is currently CFBD Tier 2 (30,000 calls/month, `/scoreboard` included, `CFBD_LIVE_SCOREBOARD=true`). The code must also keep working on the free tier (1,000 calls/month, no scoreboard), which uses the one-call `cfbd:sync-games --current --scores` refresh instead.
- Live polling runs only while `Game::inLiveWindow()` has games. Don't add unconditional frequent polling.
- When changing the schedule, state the call cost. Keep the README's "API budget" section accurate.

## UI

- Utility dashboard, not a sports media site: dark mode first, dense rows, strong score hierarchy, subtle separators, 1/2/3 responsive columns. No giant cards, big logos, gradients, hero sections or unnecessary animation.
- Colors are theme tokens in `resources/css/app.css` (`bg-page`, `text-fg`, `text-muted`, `text-faint`, `border-line`, `text-rank`, `text-live`). Use them instead of raw Tailwind colors.
- Game state must never rely on color alone (live games include "Live" text for screen readers). Controls must be keyboard-accessible, with visible focus.
- Routes stay simple and shareable: `/`, `/{season}/{week}` (`/2026/3`, `/2026/post`). Filters are query strings (`status`, `conference`, `q`) that work without JavaScript. Week links use `wire:navigate` and carry the active filters.
- It must be comfortable on an iPhone. Check narrow widths (down to about 320px) for wrapping or overflow.

## Testing

- Never call CFBD in tests. `fakeCfbd()` in `tests/Pest.php` serves fixtures from `tests/Fixtures/cfbd/`. Hand-written edge-case fixtures sit at the top level, and trimmed real responses from the spike are in `real/` (`RealPayloadTest`).
- Use `cfbdFixture()`, not `fixture()`, which Pest already defines.
- Prioritize normalization, sync behavior (schedule changes, live and final updates, freezing, outages), weekly rankings, records, FBS vs. FCS, week selection and filters. Don't test trivial framework behavior.
- Use `travelTo()` for anything time-dependent (live window, kickoff inference, current week).

## Code quality

- `composer test` runs Rector (dry run), Pint, Larastan (level 5) and Pest. All four must pass. Use `composer refactor` and `composer lint` to apply fixes.
- Pint enforces `declare(strict_types=1)`, `final` classes and strict comparisons. Add `@return BelongsTo<Team, $this>`-style generics to relations, and `@property-read` docblocks for Livewire `#[Computed]` properties.
- Fix Larastan errors at the cause. Don't add a baseline, `@phpstan-ignore` comments or type-widening to silence them. `parseModelCastsMethod: true` is required so Larastan sees `casts()`.

## Phase 2 (not until the developer asks)

Team pages (`/team/georgia`), favorites stored in the browser ("My Teams"), a network filter and a PWA. A "featured games" filter was discussed and deferred because it conflicts with the product rules above.
