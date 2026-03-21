<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Env;

class MakeRequestCommand extends BaseCommand
{
    public function name(): string
    {
        return 'make:request';
    }

    public function description(): string
    {
        return 'Scaffold a new form request validation class';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Usage: luany make:request <RequestName>\n");
            fwrite(STDERR, "  Example: luany make:request StoreUserRequest\n\n");
            exit(1);
        }

        // Normalise — append 'Request' suffix if missing
        $segments  = explode('/', str_replace('\\', '/', $name));
        $className = $this->normalizeName(array_pop($segments), 'Request');
        $subPath   = implode('/', $segments);

        $namespace = 'App\\Http\\Requests' . ($subPath ? '\\' . str_replace('/', '\\', $subPath) : '');
        $dir       = Env::basePath() . '/app/Http/Requests' . ($subPath ? '/' . $subPath : '');
        $path      = "{$dir}/{$className}.php";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($path)) {
            echo "\n  \033[33m⚠\033[0m  {$className} already exists.\n\n";
            return;
        }

        file_put_contents($path, $this->stub($className, $namespace));

        $relative = 'app/Http/Requests/' . ($subPath ? $subPath . '/' : '') . "{$className}.php";
        echo "\n  \033[32m✓\033[0m  Request created: {$relative}\n\n";
    }

    private function stub(string $name, string $namespace): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use Luany\Core\Http\Request;
use Luany\Framework\Validation\Validator;

class {$name}
{
    private Validator \$validator;

    public function __construct(private Request \$request)
    {
        \$this->validator = Validator::make(\$this->data(), \$this->rules());
    }

    /**
     * The data to validate (defaults to the full request body).
     * Override to restrict which keys are validated.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return \$this->request->body();
    }

    /**
     * Validation rules.
     * Keys are field names; values are pipe-separated rule strings.
     *
     * Supported rules:
     *   required, string, email, numeric, min:{n}, max:{n},
     *   in:{a},{b}, confirmed, unique:{table},{column}
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            // 'name'  => 'required|string|min:2|max:255',
            // 'email' => 'required|email|unique:users,email',
        ];
    }

    /**
     * Whether validation passed.
     */
    public function passes(): bool
    {
        return \$this->validator->passes();
    }

    /**
     * Whether validation failed.
     */
    public function fails(): bool
    {
        return \$this->validator->fails();
    }

    /**
     * Get validation error messages grouped by field.
     *
     * @return array<string, array<string>>
     */
    public function errors(): array
    {
        return \$this->validator->errors();
    }

    /**
     * Get only the data that passed validation.
     *
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        return \$this->validator->validated();
    }
}
PHP;
    }
}
