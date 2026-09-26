# CFBD provider notes

The spike ran on Saturday 2026-09-19 at about 11:45 ET, during week 3, using a free-tier key. Raw responses are saved in `storage/app/cfbd-spike/`. They're gitignored and can be regenerated with `php artisan cfbd:spike`. Trimmed copies used by the tests are in `tests/Fixtures/cfbd/real/`. The hand-written fixtures in `tests/Fixtures/cfbd/*.json` follow the same schema and cover edge cases the real week didn't have: TBD kickoffs, live games and all-star games.

## Tier

| | Free | Tier 2 (current key) |
| --- | --- | --- |
| Monthly calls | 1,000 | 30,000 |
| `/scoreboard` | No (401 "requires … Tier 1 or higher") | Yes |

The tier comes from Patreon, and the Patreon account must be linked to the CFBD account before the key picks it up. This key has `CFBD_LIVE_SCOREBOARD=true`; the code defaults to `false` for free-tier keys.

## Endpoints used

| Need | Endpoint | Findings |
| --- | --- | --- |
| Teams | `/teams?year=` | 138 FBS teams. Filtered locally to FBS and FCS (266). Classifications come back lowercase. |
| Weeks | `/calendar?year=` | 15 regular weeks plus one postseason week. Week 1 includes the "week 0" games. |
| Games | `/games?classification=fbs\|fcs&seasonType=both` | Week 3 had 75 FBS games, 18 of them against FCS teams. `classification=fbs` does include FBS vs. FCS games. |
| TV | `/games/media` | `id` is the game id. Every FBS game had an outlet. 26 of 75 were streaming-only (ESPN+, Peacock, SECN+, ACCNX). |
| AP poll | `/rankings?seasonType=both` | 25 teams per week. The poll list also includes Coaches, FCS, D-II and D-III polls. |
| Live | `/scoreboard?classification=fbs` | Covers the whole current week (Thursday–Sunday, 75 games), not just today. Clocks changed between calls a minute apart. |

## Confirmed

- **Poll week numbering:** AP poll N is the ranking going into week N, and week 1 is the preseason poll. During week 3, polls 1–3 existed and poll 3 was the one released after week 2.
- **TBD kickoffs:** `startTimeTBD=true` comes with a `startDate` of 04:00Z, which is midnight Eastern on the right day. The page shows "Sat TBD".
- **Missing classifications:** a few lower-division opponents have `null` classification. They're stored, and hidden unless an FBS team is playing.

## Provider gaps and how they're handled

- **Calendar `firstGameStart` / `lastGameStart`** just repeat the week's start and end dates (Monday 07:00Z to Monday 06:59Z), so they aren't real kickoff times. The games sync fills `season_weeks.first_game_at` / `last_game_at` from actual FBS kickoffs instead. For example, week 3 shows "September 17–19".
- **Duplicate outlet names:** the same network sometimes appears twice, e.g. `CW` and `The CW Network`, or `ACCNX` and `ACC Extra`. These are collapsed through `GameMediaData::ALIASES`, which also renames `USA Net` to `USA Network`. Radio is ignored, and TV is preferred over streaming.
- **No live data from `/games`:** it has no in-progress status, and 20 minutes into Coastal Carolina at Delaware its points and line scores were still `null`. Without `/scoreboard`, the app marks a game as in progress once kickoff has passed and it isn't complete. It shows "Live" without a score until the game is final.
- **Cancelled or postponed games** have no explicit status. A game still incomplete 12 hours after kickoff goes back to "upcoming". A rescheduled game follows its new `startDate`, and the first announced kickoff is kept in `original_start_at` so the page can show "Was Sat 7:00 PM".
- **Delays** have no status either, and neither endpoint gives a resumption time. With `/scoreboard`, a game it still reports as `scheduled` 15 minutes after kickoff is shown as "Delayed" (`Game::DELAYED_AFTER_MINUTES`). Weather stoppages during a game can't be detected. The free tier has no scoreboard, so it can't detect delays at all.
- **Records:** `/records` only gives current totals, so it can't show historical weeks. Records are calculated from locally stored final games, through the week being viewed. FCS games are synced too, so FCS opponents' records are complete.

## Scoreboard (verified 2026-09-19 16:31 UTC, Tier 2)

- Game `id`s match `/games` (75 of 75).
- `status` is `scheduled`, `in_progress` or `completed`. `clock` is `MM:SS`, e.g. `07:08`.
- Completed games have `period` and `clock` set to `null`, so the page shows "Final", not "Final/OT".
- `tv` joins simulcasts with pipes (`"ESPN | Disney+"`, `"ACC Network | ACCNX"`). Only the first outlet is kept.
- Team `name` includes the mascot ("Delaware Blue Hens"). The app ignores it and uses local team records.
- The payload also includes situation, possession, last play, win probability, weather and betting data. None of it is used.

## Still unverified

- How halftime is represented. The app assumes period 2 with the clock at `0:00`.
- How overtime periods are reported.
