# Changelog — luany/cli

All notable changes to this package are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [1.1.0] — 2026-04-11

### Added

- `db:seed` — run database seeders. Defaults to `DatabaseSeeder` as entry point. Supports `--class=SeederName` (both `--class=Name` and `--class Name` forms) to run a specific seeder. Fails gracefully if `database/seeders/` does not exist.
- `make:seeder <Name>` — scaffold a new seeder class in `database/seeders/`. Appends `Seeder` suffix if missing. Guards against overwriting existing files.
- `migrate:fresh --seed` — after dropping all tables and re-running migrations, automatically runs `DatabaseSeeder`. Skips seeding gracefully if `database/seeders/` does not exist. No additional confirmation prompt — `fresh` is already a destructive operation the developer invokes consciously.
- `tests/Commands/MakeSeederCommandTest.php` — 7 tests: file creation, suffix normalisation, no duplicate suffix, `extends Seeder`, `run()` method present, command name, `requiresProject()`.

### Fixed

- `make:migration` — generated migration stub used hardcoded table name `example` instead of deriving it from the migration name. Now extracts the table name via `create_{table}_table` pattern. Falls back to the full migration name for non-standard patterns (e.g. `add_status_to_orders`).
- `MigrateFreshCommand` — missing `use LuanyCli\Env` import caused fatal error when `--seed` flag was used.
- `DbSeedCommand` — missing `use LuanyCli\Env` import caused fatal error on execution.
- `phpstan.neon` — added `Luany\\Database\\Seeder\\SeederRunner` to `ignoreErrors` (same runtime dependency pattern as `MigrationRunner` — resolved via project's own `vendor/autoload.php` at runtime).

### Changed

- `tests/Commands/MakeMigrationCommandTest.php` — added two tests: `test_table_name_derived_from_migration_name` and `test_table_name_for_non_standard_name`.

**Tests: 176 → 185. Assertions: 236 → 246. All green.**

## [1.0.3] - 2026-03-29

### Fixed
- Fix LDE client script resolution for global CLI installations by exposing `LDE_CLIENT_PATH` via environment variables.

## [1.0.2] — 2026-03-28

### Added
- `dev` — start the Luany Dev Engine (LDE v1): PHP built-in server + Node.js file watcher with WebSocket live reload. Replaces BrowserSync entirely.
  - `src/Commands/DevCommand.php` — `luany dev` command. Validates `APP_ENV=development`, prints banner with correct WebSocket port, delegates to `ProcessManager`.
  - `src/Dev/NodeRunner.php` — spawns and validates the Node.js watcher process. Verifies `node` binary, `chokidar`, `ws` packages, and `watcher.js` script.
  - `src/Dev/ProcessManager.php` — orchestrates PHP server + Node watcher via `proc_open()`. Tick loop detects unexpected child exits. Clean shutdown on `SIGINT`/`SIGTERM` with cascade kill (SIGTERM → SIGKILL).
  - `src/Resources/dev/watcher.js` — Chokidar watcher + WebSocket server (port 35729). Debounce 40ms. Strategy: CSS → `inject-css`, `.lte`/`.php`/`.js`/`routes/`/`config/` → `reload`. Ignores `node_modules`, `vendor`, `storage/cache`, `storage/logs`.
  - `src/Resources/dev/client.js` — browser WebSocket client. Injected via `DevMiddleware`. CSS inject updates `<link>` href with cache-buster. Full reload for PHP/LTE/JS changes. Exponential back-off reconnect. Reads WebSocket port from `window.__LDE_WS_PORT__`.

### Changed
- Development architecture: removed BrowserSync proxy layer. Browser connects directly to PHP server; WebSocket carries only reload signals — eliminates request loops, port conflicts, and session state corruption.

### Fixed
- `NodeRunner::spawn()` — command passed as array instead of string, preventing failures on paths with spaces or special characters.
- `NodeRunner::spawn()` — environment variable propagation to Node child process now uses `getenv()` as fallback when `$_ENV` is empty (common with restrictive `variables_order` in `php.ini`).
- `NodeRunner::spawn()` — removed redundant `is_array()` guard on `$pipes`; with inherited STDIN/STDOUT/STDERR descriptors `$pipes` is always `[]`.
- `ProcessManager::kill()` — fresh `proc_get_status()` call after `usleep()` before SIGKILL, preventing stale status from blocking force-termination.
- `ProcessManager::kill()` — added `@param-out null $process` annotation to satisfy PHPStan by-ref type narrowing.
- `ProcessManager::isAlive()` and `kill()` — removed `!== false` checks on `proc_get_status()` return value; PHPStan level 6 confirms the function always returns array.
- `DevCommand::printBanner()` — WebSocket port in banner now reflects the actual `$wsPort` argument instead of always showing `35729`.
- `phpstan.neon` — added `reportUnmatchedIgnoredErrors: false` to prevent CI failures when `pcntl_*` ignore patterns are unmatched on Windows environments.

## [1.0.1] — 2026-03-23

### Changed
fix: update view stubs to use consistent @section inline syntax

## [1.0.0] — 2026-03-23

### Added
- `make:request` — scaffold a form request validation class in `app/Http/Requests/`. Generated class wraps `Validator::make()` with `rules()`, `data()`, `passes()`, `fails()`, `validated()`, `errors()`. Supports subdirectory notation (`Auth/LoginRequest`).
- `make:test` — scaffold a PHPUnit test class in `tests/`. Generated class extends `TestCase` with `setUp()`, `tearDown()`, and a placeholder `test_example()`. Supports subdirectory notation (`Feature/UserControllerTest`).
- `route:list` — display all registered application routes in a colour-coded table (GET=green, POST=yellow, PUT/PATCH=blue, DELETE=red). Loads `vendor/autoload.php` and `routes/http.php` in isolation.

### Changed
- `MakeFeatureCommand` — generates a dedicated `routes/{slug}.php` file per feature instead of appending to `routes/http.php`. Works with `Kernel` route auto-discovery. Each feature is isolated in its own routes file.
- `MakeFeatureCommand` — controller stub now uses `validate()` helper (zero boilerplate) and `abort(404)` for not-found guards. Requires `luany/framework >= 0.4`.
- `MakeFeatureCommand` — passes `validation_rules` variable to the controller stub renderer, generating correct rules per field type.
- `MakeControllerCommand` — inline stub now imports `Response` alongside `Request`.
- `MakeModelCommand` — inline stub now includes relation hints in comments (`hasMany`, `hasOne`, `belongsTo`).
- `Application` — registers `MakeRequestCommand`, `MakeTestCommand`, `RouteListCommand`.

### Breaking Changes
- None. All changes are additive or affect generated code only.

---

## [0.2.1] — Phase 5 / Phase 6 baseline

### Added
- `make:feature` — interactive CRUD scaffold. Generates model, controller, migration, 4 LTE views (index, show, create, edit), and route registration. Supports inline field definitions (`name:string price:decimal`) to skip prompts.
- `FieldParser` — converts field definitions into migration columns, fillable arrays, casts, form inputs (with `required` + `placeholder`), edit fields (with `placeholder`, no `required`), show fields, index headers/rows, and validation rules.
- `StubRenderer` — renders stub files by replacing `{{placeholder}}` variables.
- `EnvParser` — robust `.env` file parser (handles quotes, base64 values, `=` in values).
- `ProjectFinder` — detects Luany project root by scanning `composer.json` for `luany/framework` or `luany/core` dependency.
- `DoctorCommand` — environment health check. PHP version, required extensions, Composer version, project health (`.env`, `vendor`, migrations directory, `APP_KEY`, database connection).

### Fixed
- `FieldParser::toFormFields()` — applies `required` attribute and `placeholder` to all non-boolean inputs.
- `FieldParser::toEditFields()` — applies `placeholder` without `required` (pre-filled edit forms).

---

## [0.2.0] — Initial implementation

### Added
- `make:controller` — scaffold controller in `app/Controllers/`. Appends `Controller` suffix, supports subdirectory.
- `make:model` — scaffold model in `app/Models/`. Auto-derives snake_case table name from class name.
- `make:migration` — generate timestamped migration file in `database/migrations/`.
- `make:middleware` — scaffold middleware implementing `MiddlewareInterface` in `app/Http/Middleware/`.
- `make:provider` — scaffold service provider extending `ServiceProvider` in `app/Providers/`.
- `make:view` — generate LTE view file. Types: `page` (extends layout), `component` (standalone with `@style`), `layout` (yields `content`). Dot notation maps to directory structure.
- `migrate` — run all pending migrations via `MigrationRunner`.
- `migrate:rollback` — roll back the last migration batch.
- `migrate:fresh` — drop all tables and re-run all migrations.
- `migrate:status` — show migration status and batch numbers.
- `key:generate` — generate `APP_KEY` (base64-encoded 32 bytes) and write to `.env`. Creates `.env` from `.env.example` if missing.
- `cache:clear` — delete all compiled view files from `storage/cache/views/`.
- `serve` — start PHP built-in development server (`php -S host:port -t public`).
- `about` — display PHP version, framework version, app name, environment, debug mode.
- `list` — display all available commands with descriptions.
- `new` — create a new Luany project via `composer create-project`.
- Project detection — CLI auto-detects Luany projects by reading `composer.json`. No `vendor/` required for detection.
- Global vs project commands — commands that require a project fail gracefully with a clear message when run outside one.