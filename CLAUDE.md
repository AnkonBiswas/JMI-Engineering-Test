# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

Wind4Life is a **Laravel 11 port of a Django/DRF backend**. Almost every controller, model, and filter file carries a docblock pointing to the Django class it mirrors (e.g. `Port of wind_for_life/apps/anemometers/api/views.py::ReadingViewSet`). When something looks unusual for a Laravel app (DRF-shaped pagination envelope, UUID PKs, `recorded_at` global scope, polymorphic `taggables` pivot mimicking `django-taggit`), it is almost always a deliberate parity choice — preserve the behavior rather than "fix" it to be more idiomatic.

The repo is also a candidate exercise (see [README.md](README.md)). Two gaps in [routes/api.php](routes/api.php) are intentionally left for the candidate to implement:
- `GET /api/readings/export` (CSV/JSON export — the Part-1 feature)
- `?anemometer=` filter on `/api/readings`

Don't "discover and fix" these as bugs.

## Commands

Local dev is orchestrated with **Laravel Sail (Docker)**, wrapped by **go-task** ([taskfile.yml](taskfile.yml)). The stack is PostgreSQL + Redis + PHP 8.3.

| Task | What it does |
|---|---|
| `task install-deps` | Composer install via one-shot container, copies `.env`, generates `APP_KEY`. Run once. |
| `task init-app` | Build image, bring stack up detached, reset DB, seed admin + 50 anemometers × 100 readings. **Wipes data.** |
| `task up` / `task down` / `task logs` / `task bash` | Stack lifecycle + shell into app container. |
| `task tests` | Runs `sail artisan test` (Pest). Append args: `task tests -- --filter=ReadingTest`. |
| `task pest -- <args>` | Run Pest directly. |
| `task pint` | Format (Laravel Pint). |
| `task manage -- <cmd>` | `artisan` passthrough (e.g. `task manage -- route:list`). |
| `task reset-db` | `migrate:fresh --force`. |
| `task create_anemometers -- 50 100 2` | Seed N anemometers × N readings × N tags. |

Seeded login: `admin` / `admin`. App at <http://localhost:8000>.

## Test environment

[phpunit.xml](phpunit.xml) overrides DB to **SQLite in-memory** for tests (production uses Postgres). This matters: any SQL you write must work on both. The existing `ReadingFilter::filterTagsExact` deliberately avoids `HAVING` for this reason — see the comment in [app/Http/Filters/ReadingFilter.php](app/Http/Filters/ReadingFilter.php). Feature + Unit suites both use `RefreshDatabase` per test ([tests/Pest.php](tests/Pest.php)). Authenticate in tests with the `actingAsUser()` helper.

## Architecture notes that span multiple files

**UUID primary keys everywhere.** All domain models extend [app/Models/BaseUuidModel.php](app/Models/BaseUuidModel.php) (`HasUuids`, non-incrementing string keys) to match Django's `uuid4` IDs. New models should extend this base, not `Model`.

**DRF-shaped pagination envelope.** List endpoints don't return Laravel's native paginator JSON. They go through [app/Http/Responses/DrfPagination.php](app/Http/Responses/DrfPagination.php) which reshapes into `{ count, next, previous, results }`. The tests assert this shape — use it on any new list endpoint.

**Reading has a global ordering scope.** [app/Models/Reading.php](app/Models/Reading.php) installs `orderByRecordedAtDesc` as a global scope and stamps `recorded_at = now()` on `creating`, mirroring Django's `ordering = ["-recorded_at"]` and `auto_now_add=True`. When you need insertion-order or any other order, call `withoutGlobalScopes()` (see `AnemometerController::recentReadings` for the pattern).

**Tags are polymorphic via a `taggables` pivot**, not a flat many-to-many. [app/Models/Reading.php](app/Models/Reading.php) uses `morphToMany(Tag::class, 'taggable')`. Tag creation is by-name (`firstOrCreate`) inside `ReadingController::syncTags`, mimicking `django-taggit`'s add-by-name semantics. Reuse `syncTags` rather than rolling new tag-sync logic.

**Tag filtering uses a deliberate two-pass approach.** `tags_exact` is implemented as SQL prune + in-PHP set equality ([app/Http/Filters/ReadingFilter.php](app/Http/Filters/ReadingFilter.php)) so it works on both Postgres and the SQLite test DB. Don't "optimize" it back to a single `GROUP BY ... HAVING` query without also changing the test DB driver.

**Repository pattern in use for the Anemometer domain.** [app/Repositories/AbstractRepository.php](app/Repositories/AbstractRepository.php) sets the Cosmic Python-style convention; [app/Repositories/AnemometerRepository.php](app/Repositories/AnemometerRepository.php) is the first (and currently only) concrete implementation. `AnemometerController` injects it via the constructor and never touches Eloquent directly — that's the pattern to follow when extending anemometer-related work. Other domains (Reading, Tag) still call Eloquent directly from their controllers; convert them only when you have a real reason (shared query, testability need), not pre-emptively.

**FormRequest layer owns input validation**, models only declare casts. Anemometer lat/long range checks live in `StoreAnemometerRequest`/`UpdateAnemometerRequest`, not the model — see the comment at the top of [app/Models/Anemometer.php](app/Models/Anemometer.php).

**Routes are auth-gated by Sanctum** ([routes/api.php](routes/api.php)). The `anemometers/recent-readings` custom action is intentionally registered **before** `apiResource('anemometers', ...)` so the `{id}` wildcard doesn't swallow it — preserve that ordering if you add more custom actions.
