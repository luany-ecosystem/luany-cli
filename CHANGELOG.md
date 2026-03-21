# Changelog — luany/cli

All notable changes to this package are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [Unreleased] — next/v1

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

## [0.2.2] — Phase 5 / Phase 6 baseline

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