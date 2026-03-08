# luany/cli

> Official CLI for the Luany Framework.

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

## Usage
```bash
luany <command> [arguments]
```

## Commands

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
| `list` | List all available commands |
| `about` | Display information about the current project |

## make:view examples
```bash
luany make:view pages.about                    # page view (extends layout)
luany make:view components.card component      # self-contained component
luany make:view layouts.admin layout           # base layout
```

## Requirements

- PHP 8.1+
- Composer 2.0+

## License

MIT — see [LICENSE](LICENSE) for details.