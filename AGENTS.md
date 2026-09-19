<laravel-boost-guidelines>
=== .ai/scoreboard rules ===

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

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>
