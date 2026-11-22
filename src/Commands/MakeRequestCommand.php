<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Output\CommandError;
use LuanyCli\Output\Output;
use LuanyCli\Output\StructuredCommand;

class MakeRequestCommand extends BaseCommand implements StructuredCommand
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
            throw CommandError::usage('make:request <RequestName>', 'make:request StoreUserRequest');
        }

        // Normalise — append 'Request' suffix if missing
        ['subPath' => $subPath, 'className' => $className] = $this->normalizeClassPath($name, 'Request');

        $namespace = 'App\\Http\\Requests' . ($subPath ? '\\' . str_replace('/', '\\', $subPath) : '');
        $relative  = 'app/Http/Requests/' . ($subPath ? $subPath . '/' : '') . "{$className}.php";

        Output::current()->createFile($relative, $this->stub($className, $namespace), 'request', $className);
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
