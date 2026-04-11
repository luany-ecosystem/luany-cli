# luany/cli

> Official CLI for the Luany Framework — global developer tool with intelligent project detection.

## Installation

### Global (recommended)

```bash
composer global require luany/cli
```

Ensure Composer's global bin directory is in your `PATH`:

**macOS / Linux:**

```bash
export PATH="$PATH:$HOME/.composer/vendor/bin"
```

**Windows:**

```
%USERPROFILE%\AppData\Roaming\Composer\vendor\bin
```

Then use from any Luany project:

```bash
luany make:controller Home
luany serve
```

## Project Detection

The CLI automatically detects whether you are inside a Luany project by reading
`composer.json` and verifying that `luany/framework` or `luany/core` is declared
as a dependency — no `vendor/` directory required.

| Context                                    | Behaviour                                   |
| ------------------------------------------ | ------------------------------------------- |
| Outside any project                        | CLI runs normally, no warnings              |
| Luany project — dependencies installed     | Full CLI with framework autoload            |
| Luany project — `composer install` not run | Warning shown, scaffolding commands blocked |
| Project command run outside project        | Clear error message, no fatal errors        |

## Usage

```bash
luany <command> [arguments]
```

## Commands

### Global — run anywhere

| Command              | Description                                    |
| -------------------- | ---------------------------------------------- |
| `new <project-name>` | Create a new Luany project                     |
| `doctor`             | Check the Luany environment and project health |
| `about`              | Display information about the current project  |
| `list`               | List all available commands                    |

### Project — require a valid Luany project

| Command                                      | Description                                                               |
| -------------------------------------------- | ------------------------------------------------------------------------- |
| `serve`                                      | Start the built-in PHP development server                                 |
| `dev`                                        | Start the Luany Dev Engine (LDE) with live reload                         |
| `make:controller <Name>`                     | Scaffold a new controller                                                 |
| `make:model <Name>`                          | Scaffold a new model                                                      |
| `make:migration <name>`                      | Generate a timestamped migration file                                     |
| `make:middleware <Name>`                     | Scaffold a new middleware                                                 |
| `make:provider <Name>`                       | Scaffold a new service provider                                           |
| `make:view <name> [page\|component\|layout]` | Create a new LTE view                                                     |
| `make:request <Name>`                        | Scaffold a form request validation class                                  |
| `make:test <Name>`                           | Scaffold a PHPUnit test class                                             |
| `make:feature <Name>`                        | Scaffold a complete feature (model, controller, migration, views, routes) |
| `migrate`                                    | Run all pending migrations                                                |
| `migrate:rollback`                           | Rollback the last migration batch                                         |
| `migrate:status`                             | Show the status of all migrations                                         |
| `migrate:fresh`                              | Drop all tables and re-run all migrations                                 |
| `route:list`                                 | Display all registered routes in a table                                  |
| `key:generate`                               | Generate and set APP_KEY in .env                                          |
| `cache:clear`                                | Clear compiled view cache                                                 |

## luany new

```bash
luany new my-app
```

Creates a new Luany project in a `my-app/` directory. Equivalent to
`composer create-project luany/luany my-app` but with a guided experience.

## luany doctor

```bash
luany doctor
```

Outside a project — checks the global environment:

```
Luany Environment Check
──────────────────────────────────────────────────
✓  PHP version                   8.4.6
✓  ext/pdo                       loaded
✓  ext/pdo_mysql                 loaded
✓  ext/mbstring                  loaded
✓  ext/openssl                   loaded
✓  ext/json                      loaded
✓  Composer                      2.8.5
✓  luany/cli                     v0.2.0
```

Inside a project — additionally checks project health:

```
Project Health
──────────────────────────────────────────────────
✓  .env                          found
✓  vendor                        found
✓  vendor/luany/framework        found
✓  database/migrations           found
✓  APP_KEY                       configured
✓  bootstrap/app.php             found
✓  public/index.php              found
✓  config/app.php                found
✓  storage/cache/views           writable
✓  storage/logs                  writable
✓  database connection           ok
✓  _migrations table             found
```

## luany dev

```bash
luany dev
luany dev localhost 8080          # custom host/port
luany dev localhost 8000 35730    # custom WebSocket port
```

Starts the **Luany Dev Engine (LDE)** — the integrated development server with live reload.

**Requirements:**
- PHP 8.2+
- Node.js installed and available in PATH
- `npm install` run in project root
- `APP_ENV=development` in `.env`

**What it does:**
- Starts PHP built-in server (default: 8000)
- Starts Node.js watcher + WebSocket server (default: 35729)
- Injects a lightweight client into HTML responses (via DevMiddleware)
- Watches project files and triggers browser updates

**Reload strategy:**
- `.css` → injected live (no full reload)
- `.lte`, `.php`, `.js`, `routes/`, `config/` → full page reload

**Ignored paths:**
- `node_modules/`
- `vendor/`
- `storage/cache/`
- `storage/logs/`

**Architecture:**
- No proxy layer (unlike BrowserSync)
- Browser connects directly to PHP server
- WebSocket is used only for reload signals

## How LDE works

The Luany Dev Engine (LDE) is composed of two processes:

1. **PHP Server**
   - Serves the application on `http://localhost:8000`
   - Injects the live-reload client into HTML responses via `DevMiddleware`

2. **Node.js Watcher**
   - Watches filesystem changes using `chokidar`
   - Runs a WebSocket server (default: 35729)
   - Broadcasts reload events to connected browsers

**Flow:**
file change → watcher detects → debounce (40ms)
→ broadcast via WebSocket → browser reloads

**Browser client:**
- Auto-reconnect with exponential backoff
- CSS hot injection (cache-busting)
- Full reload fallback

**Note:** `luany serve` continues to work as a plain PHP server without live reload. Use `luany dev` for active development.

## Subdirectory support

`make:controller`, `make:middleware`, `make:request` and `make:test` support subdirectory notation:

```bash
luany make:controller Auth/LoginController    # → app/Controllers/Auth/LoginController.php
luany make:middleware Auth/JwtMiddleware       # → app/Http/Middleware/Auth/JwtMiddleware.php
luany make:request Auth/LoginRequest          # → app/Http/Requests/Auth/LoginRequest.php
luany make:test Feature/UserControllerTest    # → tests/Feature/UserControllerTest.php
```

## make:view examples

```bash
luany make:view pages.about                    # page view (extends layout)
luany make:view components.card component      # self-contained component
luany make:view layouts.admin layout           # base layout
luany make:view pages.products.index           # nested subdirectory
```

## luany make:request

Generates a form request validation class with the full `Validator::make()` API.

```bash
luany make:request StoreUserRequest
luany make:request Auth/LoginRequest
```

Creates `app/Http/Requests/StoreUserRequest.php`:

```php
public function rules(): array
{
    return [
        // 'name'  => 'required|string|min:2|max:255',
        // 'email' => 'required|email|unique:users,email',
    ];
}
```

The generated class exposes `passes()`, `fails()`, `validated()` and `errors()`.

You can use it directly in a controller:

```php
$form = new StoreUserRequest($request);
if ($form->fails()) {
    session()->flash('errors', $form->errors());
    return redirect('/users/create');
}
User::create($form->validated());
```

Or use the `validate()` helper (recommended — no boilerplate):

```php
$data = validate($request->body(), [
    'name'  => 'required|string|min:2|max:255',
    'email' => 'required|email',
], '/users/create');

User::create($data);
```

## luany make:test

Generates a PHPUnit test class with `setUp`, `tearDown` and a placeholder test method.

```bash
luany make:test UserTest
luany make:test Feature/UserControllerTest    # → tests/Feature/UserControllerTest.php
```

## luany route:list

Displays all registered routes in a colour-coded table.

```bash
luany route:list
```

```
  Method    URI                      Action                  Name
  ────────  ───────────────────────  ──────────────────────  ──────
  GET       /                        HomeController@index    home
  GET       /users                   UserController@index
  POST      /users                   UserController@store
  GET       /users/{id}              UserController@show
  GET       /users/{id}/edit         UserController@edit
  PUT       /users/{id}              UserController@update
  DELETE    /users/{id}              UserController@destroy

  7 route(s) registered.
```

Colours: GET = green, POST = yellow, PUT/PATCH = blue, DELETE = red.

## luany make:feature

```bash
# Interactive mode
luany make:feature Product

# Inline mode (no prompts)
luany make:feature Product name:string price:decimal active:boolean
```

Scaffolds a complete feature interactively or via inline fields:

```
  Using layout: layouts.main

  Add fields? [yes/no]: yes

  Field name (or 'done' to finish): name
  Type [string/text/integer/boolean/email/date/decimal]: string

  Field name (or 'done' to finish): price
  Type [string/text/integer/boolean/email/date/decimal]: decimal

  Field name (or 'done' to finish): done

  →  Creating feature [Product]...

  ✓  Model created:      app/Models/Product.php
  ✓  Controller created: app/Controllers/ProductController.php
  ✓  Migration created:  database/migrations/TIMESTAMP_create_products_table.php
  ✓  View created:       views/pages/products/index.lte
  ✓  View created:       views/pages/products/show.lte
  ✓  View created:       views/pages/products/create.lte
  ✓  View created:       views/pages/products/edit.lte
  ✓  Routes appended:    routes/http.php

  Routes available:
    GET     /products                → index
    GET     /products/create         → create
    POST    /products                → store
    GET     /products/{id}           → show
    GET     /products/{id}/edit      → edit
    PUT     /products/{id}           → update
    DELETE  /products/{id}           → destroy
```

Generates in one command: model with `$fillable` and `$casts`, controller with full CRUD using `validate()` and `abort()`, migration with all columns, four LTE views with design tokens, and a `Route::resource` entry in `routes/http.php`.

| Field type | Migration       | Form input           | Cast    | Behavior                                  |
| ---------- | --------------- | -------------------- | ------- | ----------------------------------------- |
| `string`   | `VARCHAR(255)`  | `text`               | —       | `required`, `placeholder="Enter {Label}"` |
| `text`     | `TEXT`          | `textarea`           | —       | `required`, `placeholder="Enter {Label}"` |
| `integer`  | `INT`           | `number`             | `int`   | `required`, `placeholder="Enter {Label}"` |
| `boolean`  | `TINYINT(1)`    | `checkbox`           | `bool`  | no `required`/placeholder                 |
| `email`    | `VARCHAR(150)`  | `email`              | —       | `required`, `placeholder="Enter {Label}"` |
| `date`     | `DATE`          | `date`               | —       | `required`, `placeholder="Enter {Label}"` |
| `decimal`  | `DECIMAL(10,2)` | `number step="0.01"` | `float` | `required`, `placeholder="Enter {Label}"` |

## Requirements

- PHP 8.2+
- Composer 2.0+

## Testing

```bash
composer install
vendor/bin/phpunit --testdox
```

```
OK, but some tests were skipped!
Tests: 185, Assertions: 246, Skipped: 1.
```

## Notes on recent improvements

**Phase 6 (next/v1):**

- Added `make:request` — generates form request classes with full `Validator::make()` integration (`rules()`, `passes()`, `fails()`, `validated()`, `errors()`). Supports subdirectory notation.
- Added `make:test` — generates PHPUnit test classes with `setUp`/`tearDown`/placeholder. Supports subdirectory notation.
- Added `route:list` — displays registered routes in a colour-coded table (GET=green, POST=yellow, PUT/PATCH=blue, DELETE=red).
- `feature-controller.stub` updated: uses `validate()` helper (zero boilerplate) and `abort(404)` for not-found guards. Requires `luany/framework` >= 0.4.
- `MakeControllerCommand` stub: added `Response` import.
- `MakeModelCommand` stub: added relation hints in comments.
- `MakeFeatureCommand`: passes `validation_rules` variable to the controller stub renderer.

**Previous (v0.2.x):**

- `FieldParser` applies `required` + `placeholder` to generated form fields (`toFormFields`) and `placeholder` to edit fields (`toEditFields`, without required).
- `.env` parsing centralized via `Support\EnvParser`, used by `MigrateBaseCommand` and `DoctorCommand` for reliability with quoted values, base64, and `=` in values.

## Common Issues

### Command not found (`luany`)

- Ensure Composer global bin directory is in your `PATH`
- Restart your terminal after installation

### Project not detected

- Ensure `composer.json` includes `luany/framework` or `luany/core`
- Run `composer install` if dependencies are missing

### Migrations not running

- Check database configuration in `.env`
- Ensure database server is running
- Run `luany doctor` to verify connection

### Live reload not working

- `APP_ENV=development` in `.env`
- Node.js installed (`node -v`)
- Run `npm install`
- WebSocket port (default `35729`) not in use

## License

MIT — see [LICENSE](LICENSE) for details.
