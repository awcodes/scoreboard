# College Football Scoreboard

## Project Goal

Build a small, focused Laravel application for viewing the complete
weekly NCAA Division I FBS football schedule and scores.

The motivation is simple: existing sports sites heavily prioritize Top
25 teams, featured games, news, betting information, and
engagement-driven content. It is unnecessarily difficult to see **every
FBS game played during a particular week**.

The primary question the application answers is:

> What happened in college football this week, and what's coming up
> next?

This is primarily a personal utility used by the developer and
potentially a few other people. Public accessibility is acceptable, but
public adoption, monetization, and business-scale architecture are not
design requirements.

## Core Product Principle

Every FBS game matters equally. National rankings provide context, but
must never determine which games are displayed, prioritized, or hidden.

There should be no default Top 25 scoreboard, featured-game system, or
editorial prioritization.

**The complete weekly schedule is the product.**

## Technology

-   Laravel 13
-   PHP 8.4+
-   Livewire 4
-   Tailwind CSS
-   SQLite initially
-   CollegeFootballData (CFBD) as the upstream provider

Prefer conventional Laravel and Livewire patterns. Do not introduce
React, Vue, Inertia, or a separate frontend/API architecture without a
concrete requirement.

The interface should feel SPA-like when navigating weeks and filters
while retaining simple, shareable Laravel routes.

## Bootstrap Expectations

This is a new application. The implementing agent should bootstrap the
Laravel application rather than assume an existing project.

The developer should only need to supply the CFBD API key.

The agent should:

1.  Bootstrap Laravel 13.
2.  Install and configure Livewire 4.
3.  Configure SQLite.
4.  Add CFBD configuration.
5.  Perform the provider spike described below.
6.  Create the local data model from verified provider payloads.
7.  Build synchronization commands/jobs.
8.  Build the UI.
9.  Add focused tests.

The API key should be supplied through the environment:

``` env
CFBD_API_KEY=
```

Never commit or expose the API key.

## Scope Philosophy

Keep the application deliberately small. Do not prematurely build:

-   user accounts or authentication
-   social features or comments
-   news or articles
-   betting or fantasy functionality
-   advertising or monetization
-   recommendation systems
-   notifications
-   complex administration
-   multi-tenancy
-   a public API
-   elaborate caching or scaling infrastructure

Favor boring Laravel code over abstractions intended for hypothetical
future requirements.

## Data Provider Decision

Use CollegeFootballData as the upstream provider. Do not evaluate
alternative providers during implementation.

The acceptable API budget is approximately \$20/month, but begin with
the least expensive CFBD tier that satisfies the actual requirements and
request volume. Use a paid tier when live scoreboard access requires it.

Do not optimize the architecture merely to avoid a small monthly API
cost. Prioritize completeness, reliability, simplicity, reasonable
request volume, then cost.

## CFBD Scope

Use only the capabilities needed for:

-   teams
-   schedules and games
-   kickoff dates/times
-   game status
-   live and final scores
-   TV/network assignments
-   team records
-   AP Top 25 rankings
-   historical weekly ranking context where available

Ignore unrelated analytics, betting, recruiting, advanced metrics,
play-by-play, GraphQL, and player analytics unless a future requirement
explicitly needs them.

## Provider Integration Boundary

Keep CFBD-specific communication isolated behind a small client and
normalization layer:

``` text
CFBD API
    ↓
CFBD Client
    ↓
DTOs / Normalization
    ↓
Eloquent
    ↓
Livewire
```

Do not build a generic multi-provider framework. The goal is simply to
prevent raw CFBD payload structures from leaking throughout models and
UI code.

## Provider Spike

Before finalizing migrations, inspect current CFBD responses with a real
API key.

Verify:

-   team data
-   weekly schedules/games
-   scoreboard data
-   live/final statuses
-   TV/network data
-   AP rankings
-   team records, or how records should be derived
-   FBS vs. FCS games
-   TBD kickoff times
-   TBD network assignments

Save representative responses as test fixtures.

Design the local schema from actual current payloads rather than
assumptions in this document. Document any provider gaps.

## Primary User Experience

The primary interface is a **week view**.

``` text
‹ Week 2          WEEK 3          Week 4 ›

September 10–12, 2026
```

The selected week displays every relevant FBS game.

Past weeks naturally function as scoreboards. Future weeks naturally
function as schedules. The current week contains upcoming, live, and
completed games.

Do not create separate Scores and Schedule applications unless real
usage demonstrates a need.

## Default View and Routing

The root should resolve to the current college football week.

Example routes:

``` text
/
/2026/3
/2026/4
```

URLs should remain simple, human-readable, shareable, and navigable
without JavaScript. Use Livewire navigation where appropriate for fast
week-to-week transitions.

## Game Display Principles

Information density and scanning are primary goals.

The team name must always be the leftmost element in each team row.

Canonical row:

``` text
Team Name (Record) #Rank                 Score
```

Unranked:

``` text
Team Name (Record)                       Score
```

Do not place ranking before the team name, indent ranked teams
differently, or reserve empty ranking space for unranked teams.

Ranking is contextual metadata, not the visual anchor. Scores remain
independently right-aligned.

### Examples

Completed ranked matchup:

``` text
FINAL                                  ABC

Georgia (3-0) #4                        34
Tennessee (2-1) #17                     20
```

Completed unranked matchup:

``` text
FINAL                                ESPN+

Georgia Southern (2-1)                 31
Appalachian State (1-2)                24
```

Upcoming:

``` text
7:30 PM                                ESPN

Georgia (3-0) #4
Tennessee (2-1) #17
```

Live:

``` text
3rd · 4:32                             ABC

Georgia (3-0) #4                        21
Tennessee (2-1) #17                     17
```

**Scoreboard density is a feature.**

## Visual Hierarchy

Within each team row:

-   team name: primary
-   record: secondary/muted
-   AP ranking: secondary but slightly emphasized
-   score: primary/bold and right-aligned

Rankings should never visually dominate team identity.

## Responsive Layout

Use approximately:

-   one column on mobile
-   two columns on medium screens
-   three columns on sufficiently large screens

Do not stretch individual games unnecessarily across a desktop viewport.

## Teams

Maintain local team records. An initial shape may include:

``` text
id
provider_id
name
abbreviation
conference
classification
logo_url
```

Store only useful fields. Do not mirror the entire provider schema.

The primary scope is FBS, but FBS-vs-FCS games must still be
represented.

## Games

Maintain games locally. An illustrative shape:

``` text
id
provider_id
season
week
season_type
home_team_id
away_team_id
home_score
away_score
start_at
status
period
clock
network
venue
completed_at
provider_updated_at
```

This is not a mandatory migration design. Finalize it only after the
provider spike.

External opponents outside the normal synchronized FBS team set must
still be representable.

## Team Records

Show the overall season record inline:

``` text
Georgia (3-0)
Georgia Southern (2-1)
```

Records do not affect visibility or ordering.

Only overall record is required for V1. Conference records/standings can
be considered later.

## Rankings

Use the AP Top 25 as the canonical scoreboard ranking.

``` text
Georgia (3-0) #4
```

Ranking belongs to a particular season/week and should not be stored
directly on the team.

A possible historical structure:

``` text
team_rankings
id
team_id
season
week
poll
rank
```

Historical week views should prefer the ranking appropriate to that week
rather than today's ranking.

On the scoreboard, `#4` implicitly means AP #4.

## CFP Rankings

CFP rankings are not required for V1. The data model should not
unnecessarily prevent additional ranking sources later.

Do not switch the scoreboard's ranking semantics from AP to CFP
mid-season.

## TV / Network

Show the broadcast outlet directly with each game whenever available,
including networks/services such as ABC, CBS, FOX, ESPN, ESPN2, ESPNU,
FS1, NBC, ACC Network, SEC Network, Big Ten Network, ESPN+, or Peacock.

If unknown:

``` text
TV TBD
```

Future schedules require periodic synchronization because kickoff times
and broadcast assignments can change.

## Filters

Keep V1 filtering small:

``` text
All
Live
Final
Upcoming
```

Conference filtering is also useful.

`All` remains the natural default. Filters must not change the
underlying complete weekly dataset.

## Ordering

Default to chronological kickoff time with deterministic secondary
ordering for games sharing a kickoff.

Do not move Top 25 games above other games. Do not create popularity,
importance, or featured-game ordering.

Reevaluate grouping only after real-world use.

## Current Week Summary

A compact status summary may show:

``` text
12 live · 37 final · 16 upcoming
```

Keep this informational and compact.

## Synchronization

All provider data should synchronize into the local database. The UI
reads local data rather than querying CFBD per page request.

Use Laravel scheduled commands/jobs.

### Teams

Synchronize at season setup and occasionally thereafter.

### Future Games

Refresh schedules and network assignments periodically, initially
perhaps once or twice daily.

### Current Week

Refresh more frequently around actual game windows. Base cadence on the
CFBD tier and measured request volume. Do not blindly poll around the
clock.

### Completed Games

Once final and confirmed, treat a game as effectively immutable and stop
frequent refreshes.

## Live Score Strategy

When live scoreboard access is enabled, use CFBD for:

-   in-progress status
-   current score
-   period
-   game clock
-   completed status

Frontend refreshes must never translate directly into upstream CFBD
requests. Upstream traffic is controlled by scheduled synchronization.

## API Usage

Centralize CFBD communication with Laravel's HTTP client.

Configuration should resemble:

``` php
'cfbd' => [
    'key' => env('CFBD_API_KEY'),
],
```

Handle provider downtime, rate limiting, invalid/incomplete responses,
TBD times, and TBD networks sensibly.

Previously synchronized data should remain usable during an upstream
outage.

## Caching

Do not over-engineer caching. The local database already protects the
upstream API.

Use Laravel caching only if actual profiling demonstrates value. Do not
introduce Redis solely for this project.

## Team Detail --- Phase 2

A future team page might be:

``` text
/team/georgia
```

with:

``` text
GEORGIA

#4 AP
3-0 Overall
1-0 SEC

Week 1   Clemson          W 31–17
Week 2   Austin Peay      W 48–7
Week 3   Tennessee        W 34–20
Week 4   Alabama          3:30 PM · ABC
```

Keep it utilitarian rather than turning it into a sports portal.

## Favorites --- Phase 2

Favorites may be stored locally in the browser without accounts.

``` text
All Games     My Teams
```

Favorites must never undermine easy access to the complete schedule.

## Network Filtering --- Phase 2

A future network filter can answer:

> What can I watch right now?

Implement only after the basic scoreboard is proven useful.

## PWA --- Possible Phase 2

The app is a reasonable candidate for home-screen installation. Later
consider a manifest, icons, standalone display metadata, and minimal
service-worker support.

Do not block V1 on PWA functionality.

## Administration

Do not build an administration panel initially.

Use Artisan commands, the Laravel scheduler, and logs. If manual
synchronization later becomes annoying, consider a tiny authenticated
utility or Filament interface.

Do not introduce Filament merely because it is familiar.

## Visual Direction

The application should feel like a utility/dashboard, not a sports media
site.

Prioritize:

-   dark mode
-   typography
-   dense information
-   strong score hierarchy
-   clear game state
-   subtle separators
-   responsive columns
-   fast scanning

Avoid:

-   giant game cards
-   oversized team logos
-   decorative gradients
-   news-style hero sections
-   advertisements
-   betting UI
-   unnecessary imagery
-   excessive animation

Team colors may be used subtly later if they do not compromise
readability.

## Accessibility

Ensure sufficient contrast, semantic markup, keyboard-accessible
controls, visible focus states, and that game state is not communicated
through color alone.

## Testing

Prioritize tests for:

-   provider normalization
-   game synchronization
-   schedule updates
-   score updates
-   correct weekly rankings
-   records
-   FBS-vs-FCS games
-   completed-game handling
-   week selection
-   filters

Use saved provider fixtures/fakes instead of calling CFBD in automated
tests. Avoid testing trivial framework behavior.

## Implementation Milestones

### Milestone 1 --- Bootstrap + Provider Spike

1.  Bootstrap Laravel 13.
2.  Install/configure Livewire 4.
3.  Configure SQLite.
4.  Add `CFBD_API_KEY` configuration.
5.  Inspect current CFBD endpoints with a real key.
6.  Verify teams.
7.  Verify weekly schedule/games.
8.  Verify TV/network data.
9.  Verify rankings.
10. Verify records or determine how to derive them.
11. Verify FBS-vs-FCS games.
12. Verify scoreboard/live-data availability.
13. Save representative responses as test fixtures.
14. Document provider gaps.

Do not finalize migrations before this spike.

### Milestone 2 --- Data Layer

Implement teams, games, rankings, the CFBD client, normalization/DTOs,
synchronization commands, scheduler configuration, and fixture-based
tests.

Verify through Artisan/Tinker that complete weekly data can be retrieved
locally.

### Milestone 3 --- Basic Scoreboard

Build the simplest usable week view with:

-   week navigation
-   all games
-   kickoff time
-   game state
-   scores
-   team records
-   AP rankings
-   TV/network

At this point, the app should already solve the original problem.

### Milestone 4 --- Current Week Behavior

Add live/final/upcoming states, current-week summary, sensible refresh
cadence, and Livewire navigation.

### Milestone 5 --- Filtering + Responsive Polish

Add status filters, conference filtering, responsive multi-column
layout, typography/spacing polish, and an accessibility pass.

### Milestone 6 --- Stop and Use It

Deploy it and use it during several real college football weekends.

Do not automatically proceed into speculative feature development.
Capture real friction and use it to determine whether Phase 2 features
are necessary.

## Deployment

Keep deployment conventional. The app should require approximately:

``` text
PHP 8.4+
Laravel 13
SQLite
cron / Laravel scheduler
CFBD API key
```

It should run comfortably on a very small server. Revisit infrastructure
only if actual public usage requires it.

## Definition of Done for V1

V1 is complete when the developer can open the application on a college
football Saturday and:

1.  Immediately see the current FBS week.
2.  See every FBS game without requesting more results.
3.  See completed scores.
4.  See live state/scores where provider data permits.
5.  See upcoming games.
6.  See kickoff times.
7.  See broadcast networks when known.
8.  See each team's current record.
9.  See AP ranking after the team name and record when applicable.
10. See team names aligned consistently on the left regardless of
    ranking.
11. Navigate backward to previous weeks.
12. Navigate forward to upcoming weeks.
13. Filter games without Top 25 status influencing visibility.
14. Use the interface comfortably from an iPhone.
15. Scan a large number of games quickly.

## Guiding Rule

When deciding whether to add architecture or functionality, ask:

> Does this make it easier to see what happened in college football this
> week?

If the answer is no, it probably does not belong in V1.
