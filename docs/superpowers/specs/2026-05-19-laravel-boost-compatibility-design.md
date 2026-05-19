# Laravel Boost compatibility — Design

## Goal

Make `masterix21/laravel-bookings` discoverable by Laravel Boost so that AI
agents working in host applications receive accurate, package-specific context.

## Background

Laravel Boost auto-discovers, during `boost:install`, two kinds of assets that
packages ship under `resources/boost/`:

- **Guidelines** — `resources/boost/guidelines/core.blade.php`, always loaded
  and concatenated into the project's AI guidelines file.
- **Agent Skills** — `resources/boost/skills/{skill-name}/SKILL.md`, installed
  on user request, richer and loaded on-demand.

No `composer.json`, PHP code, or config change is required: discovery is by
convention.

## Deliverables

### 1. `resources/boost/guidelines/core.blade.php`

Concise overview, loaded automatically. Sections:

- Overview of the package.
- Core models: `BookableResource`, `Booking`, `BookedPeriod`,
  `BookablePlanning`, `BookableRelation`.
- Essential traits/contracts: `IsBookable`/`Bookable`, `HasBookings`,
  `IsBookablePlanningSource`/`BookablePlanningSource`, `SyncBookableResource`,
  `SyncBookablePlanning`.
- Action pattern: `BookResource::run(...)` and `CheckBookingOverlaps::run(...)`
  with `<code-snippet>` examples wrapped in `@verbatim`.
- Events and exceptions list for error handling.
- Practical rules: use `spatie/period` for periods, resolve models via
  `config('bookings.models.*')`, use Carbon for dates.

### 2. `resources/boost/skills/laravel-bookings/SKILL.md`

On-demand skill with `name`/`description` frontmatter, a "When to use" section,
and extended how-tos: create a bookable resource, book/update/cancel, planning
sources, overlap checks, custom booking-code generator. Links to `docs/` for
detail.

## Out of scope

- Versioned guidelines (`{package}/{version}/`): the package exposes a single
  stable API.
- MCP tools: the package provides no runtime tooling for Boost's MCP server.

## Verification

- `php artisan view:lint` style is not applicable; the `.blade.php` file is
  rendered by Boost, not Laravel views. Verify the Blade compiles by ensuring
  all `<code-snippet>` blocks are inside `@verbatim`/`@endverbatim`.
- Manual: run `boost:install` in a host app and confirm the guidelines appear.
