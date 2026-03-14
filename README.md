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

| Context | Behaviour |
|---|---|
| Outside any project | CLI runs normally, no warnings |
| Luany project — dependencies installed | Full CLI with framework autoload |
| Luany project — `composer install` not run | Warning shown, scaffolding commands blocked |
| Project command run outside project | Clear error message, no fatal errors |

## Usage
```bash
luany <command> [arguments]
```

## Commands

### Global — run anywhere
| Command | Description |
|---|---|
| `new <project-name>` | Create a new Luany project |
| `doctor` | Check the Luany environment and project health |
| `about` | Display information about the current project |
| `list` | List all available commands |

### Project — require a valid Luany project
| Command | Description |
|---|---|
| `serve` | Start the built-in PHP development server |
| `make:controller <Name>` | Scaffold a new controller |
| `make:model <Name>` | Scaffold a new model |
| `make:migration <name>` | Generate a timestamped migration file |
| `make:middleware <Name>` | Scaffold a new middleware |
| `make:provider <Name>` | Scaffold a new service provider |
| `make:view <name> [page\|component\|layout]` | Create a new LTE view |
| `migrate` | Run all pending migrations |
| `migrate:rollback` | Rollback the last migration batch |
| `migrate:status` | Show the status of all migrations |
| `migrate:fresh` | Drop all tables and re-run all migrations |
| `key:generate` | Generate and set APP_KEY in .env |
| `cache:clear` | Clear compiled view cache |

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
✓  database connection           ok
✓  _migrations table             found
```

## Subdirectory support

`make:controller` and `make:middleware` support subdirectory notation:
```bash
luany make:controller Auth/LoginController    # → app/Controllers/Auth/LoginController.php
luany make:middleware Auth/JwtMiddleware       # → app/Http/Middleware/Auth/JwtMiddleware.php
```

## make:view examples
```bash
luany make:view pages.about                    # page view (extends layout)
luany make:view components.card component      # self-contained component
luany make:view layouts.admin layout           # base layout
luany make:view pages.products.index           # nested subdirectory
```

## Requirements

- PHP 8.1+
- Composer 2.0+

## Testing
```bash
composer install
vendor/bin/phpunit --testdox
```
```
OK (79 tests, 95 assertions)
```

## License

MIT — see [LICENSE](LICENSE) for details.