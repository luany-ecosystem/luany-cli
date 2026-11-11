<?php

namespace LuanyCli\Support;

/**
 * FieldParser
 *
 * Converts a list of field definitions (name + type) into the code fragments
 * used by the make:feature stubs: migration columns and constraints, model
 * fillable / casts / relations, validation rules, form inputs, show fields,
 * index headers and rows.
 *
 * Field names and types are validated by NameValidator before they get here.
 *
 * Types (schema builder column → form input → cast):
 *   string    string()                 text input
 *   text      text()                   textarea
 *   integer   integer()                number input        cast int
 *   decimal   decimal(10, 2)           number input        cast decimal:2
 *   boolean   boolean()                checkbox            cast bool
 *   email     string(150)->unique()    email input         unique rule
 *   date      date()->nullable()       date input          cast date
 *   datetime  dateTime()->nullable()   datetime-local      cast datetime
 *   foreign   foreignId()->constrained() number input      cast int, exists rule,
 *             (name must end in _id)                      belongsTo relation
 *
 * A trailing "?" makes a field optional: bio:text? → ->nullable(), "nullable" rule.
 */
class FieldParser
{
    /** Valid types the developer can choose from. */
    public const TYPES = [
        'string', 'text', 'integer', 'decimal', 'boolean', 'email', 'date', 'datetime', 'foreign',
    ];

    /** Short descriptions for interactive prompts. */
    public const TYPE_DESCRIPTIONS = [
        'string'   => 'short text (255)',
        'text'     => 'long text',
        'integer'  => 'whole number',
        'decimal'  => 'money / decimal (10,2)',
        'boolean'  => 'yes / no (checkbox)',
        'email'    => 'unique email address',
        'date'     => 'date',
        'datetime' => 'date and time',
        'foreign'  => 'link to another table (name must end in _id)',
    ];

    private const INPUT_TYPES = [
        'string'   => 'text',
        'email'    => 'email',
        'integer'  => 'number',
        'decimal'  => 'number',
        'date'     => 'date',
        'datetime' => 'datetime-local',
        'foreign'  => 'number',
    ];

    // ── Migration (schema builder) ────────────────────────────────────────────

    /**
     * Blueprint calls for the create-table migration (portable across
     * MySQL, PostgreSQL and SQLite).
     *
     * @param array<int, array{name: string, type: string, nullable?: bool}> $fields
     */
    public static function toSchemaColumns(array $fields): string
    {
        if (empty($fields)) {
            return '';
        }

        $lines = [];
        foreach ($fields as $field) {
            $lines[] = '            ' . self::schemaColumn($field) . ';';
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array{name: string, type: string, nullable?: bool} $field
     */
    private static function schemaColumn(array $field): string
    {
        $name     = $field['name'];
        $nullable = !empty($field['nullable']);

        $column = match ($field['type']) {
            'text'     => "\$table->text('{$name}')",
            'integer'  => "\$table->integer('{$name}')" . ($nullable ? '' : '->default(0)'),
            'decimal'  => "\$table->decimal('{$name}', 10, 2)" . ($nullable ? '' : '->default(0)'),
            'boolean'  => "\$table->boolean('{$name}')->default(false)",
            'email'    => "\$table->string('{$name}', 150)->unique()",
            'date'     => "\$table->date('{$name}')",
            'datetime' => "\$table->dateTime('{$name}')",
            'foreign'  => "\$table->foreignId('{$name}')",
            default    => "\$table->string('{$name}')",
        };

        // date / datetime are optional by default (empty form inputs).
        if ($nullable || in_array($field['type'], ['date', 'datetime'], true)) {
            $column .= '->nullable()';
        }

        if ($field['type'] === 'foreign') {
            $column .= "->constrained('" . self::referencedTable($name) . "')";
        }

        return $column;
    }

    /**
     * Table referenced by a foreign-key field: category_id → categories.
     */
    public static function referencedTable(string $field): string
    {
        return self::pluralize(preg_replace('/_id$/', '', $field) ?? $field);
    }

    /**
     * Foreign fields whose referenced table has no migration yet.
     *
     * @param array<int, array{name: string, type: string, nullable?: bool}> $fields
     * @param array<int, string>                            $existingTables
     * @return array<int, string>
     */
    public static function missingReferences(array $fields, array $existingTables): array
    {
        $missing = [];
        foreach ($fields as $field) {
            if ($field['type'] === 'foreign' && !in_array(self::referencedTable($field['name']), $existingTables, true)) {
                $missing[] = self::referencedTable($field['name']);
            }
        }
        return array_values(array_unique($missing));
    }

    // ── Model ─────────────────────────────────────────────────────────────────

    /**
     * @param array<int, array{name: string, type: string, nullable?: bool}> $fields
     */
    public static function toFillable(array $fields): string
    {
        if (empty($fields)) {
            return '        //';
        }

        return implode("\n", array_map(
            fn ($f) => "        '{$f['name']}',",
            $fields
        ));
    }

    /**
     * @param array<int, array{name: string, type: string, nullable?: bool}> $fields
     */
    public static function toCasts(array $fields): string
    {
        $castMap = [
            'boolean'  => 'bool',
            'integer'  => 'int',
            'decimal'  => 'decimal:2',
            'date'     => 'date',
            'datetime' => 'datetime',
            'foreign'  => 'int',
        ];

        $casts = [];
        foreach ($fields as $field) {
            if (isset($castMap[$field['type']])) {
                $casts[] = "        '{$field['name']}' => '{$castMap[$field['type']]}',";
            }
        }

        if (empty($casts)) {
            return '';
        }

        return "\n    protected array \$casts = [\n" . implode("\n", $casts) . "\n    ];\n";
    }

    /**
     * belongsTo() methods for foreign fields: category_id → category().
     *
     * @param array<int, array{name: string, type: string, nullable?: bool}> $fields
     */
    public static function toRelations(array $fields): string
    {
        $methods = [];

        foreach ($fields as $field) {
            if ($field['type'] !== 'foreign') {
                continue;
            }

            $base   = preg_replace('/_id$/', '', $field['name']) ?? $field['name'];
            $method = lcfirst(self::studly($base));
            $class  = self::studly($base);

            $methods[] = "\n    public function {$method}(): BelongsTo\n"
                . "    {\n"
                . "        return \$this->belongsTo({$class}::class, '{$field['name']}');\n"
                . "    }\n";
        }

        return implode('', $methods);
    }

    /**
     * use statements needed by the generated model.
     *
     * @param array<int, array{name: string, type: string, nullable?: bool}> $fields
     */
    public static function toModelImports(array $fields): string
    {
        foreach ($fields as $field) {
            if ($field['type'] === 'foreign') {
                return "use Luany\\Database\\Relations\\BelongsTo;\n";
            }
        }

        return '';
    }

    // ── Validation ────────────────────────────────────────────────────────────

    /**
     * Rules for store() (no $updating) or update() ($updating: unique rules
     * ignore the record being edited, whose id is in $id).
     *
     * @param array<int, array{name: string, type: string, nullable?: bool}> $fields
     */
    public static function toValidationRules(array $fields, string $table = '', bool $updating = false): string
    {
        if (empty($fields)) {
            return '';
        }

        $lines = [];
        foreach ($fields as $field) {
            $name  = $field['name'];
            $rules = match ($field['type']) {
                'string'   => "'required', 'string', 'max:255'",
                'text'     => "'required', 'string'",
                'email'    => "'required', 'email', 'max:150'" . ($table === '' ? '' : (
                    $updating
                        ? ", 'unique:{$table},{$name},' . \$id"
                        : ", 'unique:{$table},{$name}'"
                )),
                'integer'  => "'required', 'integer'",
                'decimal'  => "'required', 'numeric'",
                'boolean'  => "'boolean'",
                'date'     => "'nullable', 'date'",
                'datetime' => "'nullable', 'date'",
                'foreign'  => "'required', 'integer', 'exists:" . self::referencedTable($name) . ",id'",
                default    => "'required'",
            };
            if (!empty($field['nullable'])) {
                $rules = str_starts_with($rules, "'required'")
                    ? "'nullable'" . substr($rules, strlen("'required'"))
                    : (str_starts_with($rules, "'nullable'") || $field['type'] === 'boolean' ? $rules : "'nullable', " . $rules);
            }
            $lines[] = "            '{$name}' => [{$rules}],";
        }

        return implode("\n", $lines);
    }

    // ── Views ─────────────────────────────────────────────────────────────────

    /**
     * @param array<int, array{name: string, type: string, nullable?: bool}> $fields
     */
    public static function toFormFields(array $fields): string
    {
        if (empty($fields)) {
            return '                {{-- Add form fields here --}}';
        }

        return implode("\n", array_map(fn ($f) => self::formField($f, null), $fields));
    }

    /**
     * Form fields pre-filled with the current values of $item.
     *
     * @param array<int, array{name: string, type: string, nullable?: bool}> $fields
     */
    public static function toEditFields(array $fields, string $item): string
    {
        if (empty($fields)) {
            return '                {{-- Add form fields here --}}';
        }

        return implode("\n", array_map(fn ($f) => self::formField($f, $item), $fields));
    }

    /**
     * @param array<int, array{name: string, type: string, nullable?: bool}> $fields
     */
    public static function toShowFields(array $fields, string $item): string
    {
        if (empty($fields)) {
            return '        {{-- Add fields here --}}';
        }

        return implode("\n", array_map(function ($field) use ($item) {
            return "        <div class=\"field\">\n"
                . "            <label>" . self::label($field['name']) . "</label>\n"
                . "            <span>" . self::displayValue($field, $item) . "</span>\n"
                . "        </div>";
        }, $fields));
    }

    /**
     * @param array<int, array{name: string, type: string, nullable?: bool}> $fields
     */
    public static function toIndexHeaders(array $fields): string
    {
        if (empty($fields)) {
            return '';
        }

        return implode("\n", array_map(
            fn ($field) => '                    <th>' . self::label($field['name']) . '</th>',
            $fields
        ));
    }

    /**
     * @param array<int, array{name: string, type: string, nullable?: bool}> $fields
     */
    public static function toIndexRows(array $fields, string $item): string
    {
        if (empty($fields)) {
            return '';
        }

        return implode("\n", array_map(
            fn ($f) => '                        <td>' . self::displayValue($f, $item) . '</td>',
            $fields
        ));
    }

    /** @param array<int, array{name: string, type: string}> $fields */
    public static function toFillableKeys(array $fields): string
    {
        if (empty($fields)) {
            return '';
        }

        return implode(', ', array_map(fn ($f) => "'{$f['name']}'", $fields));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * @param array{name: string, type: string, nullable?: bool} $field
     */
    private static function formField(array $field, ?string $item): string
    {
        $name  = $field['name'];
        $label = self::label($name);

        if ($field['type'] === 'text') {
            $value = $item === null ? '' : "{{ \${$item}->{$name} }}";
            $req   = $item === null && empty($field['nullable']) ? ' required' : '';
            return "                <div class=\"form-group\">\n"
                . "                    <label for=\"{$name}\">{$label}</label>\n"
                . "                    <textarea name=\"{$name}\" id=\"{$name}\" class=\"form-control\" placeholder=\"Enter {$label}\"{$req}>{$value}</textarea>\n"
                . "                </div>";
        }

        if ($field['type'] === 'boolean') {
            $checked = $item === null ? '' : " {{ \${$item}->{$name} ? 'checked' : '' }}";
            return "                <div class=\"form-group\">\n"
                . "                    <div class=\"checkbox-group\">\n"
                . "                        <input type=\"checkbox\" name=\"{$name}\" id=\"{$name}\" value=\"1\"{$checked}>\n"
                . "                        <label for=\"{$name}\">{$label}</label>\n"
                . "                    </div>\n"
                . "                </div>";
        }

        $inputType = self::INPUT_TYPES[$field['type']] ?? 'text';
        $extra     = match ($field['type']) {
            'decimal' => ' step="0.01"',
            'foreign' => ' min="1"',
            default   => '',
        };
        // Create forms mark fields required; edit forms leave it to server validation.
        $required = $item === null && empty($field['nullable']) && !in_array($field['type'], ['date', 'datetime'], true) ? ' required' : '';

        $value = '';
        if ($item !== null) {
            $value = ' value="' . match ($field['type']) {
                'date'     => "{{ \${$item}->{$name}?->format('Y-m-d') }}",
                'datetime' => "{{ \${$item}->{$name}?->format('Y-m-d\\TH:i') }}",
                default    => "{{ \${$item}->{$name} }}",
            } . '"';
        }

        return "                <div class=\"form-group\">\n"
            . "                    <label for=\"{$name}\">{$label}</label>\n"
            . "                    <input type=\"{$inputType}\"{$extra} name=\"{$name}\" id=\"{$name}\" class=\"form-control\" placeholder=\"Enter {$label}\"{$value}{$required}>\n"
            . "                </div>";
    }

    /**
     * Template expression that prints a field (dates formatted, booleans as Yes/No).
     *
     * @param array{name: string, type: string, nullable?: bool} $field
     */
    private static function displayValue(array $field, string $item): string
    {
        $name = $field['name'];

        return match ($field['type']) {
            'boolean'  => "{{ \${$item}->{$name} ? 'Yes' : 'No' }}",
            'date'     => "{{ \${$item}->{$name}?->format('Y-m-d') }}",
            'datetime' => "{{ \${$item}->{$name}?->format('Y-m-d H:i') }}",
            default    => "{{ \${$item}->{$name} }}",
        };
    }

    private static function label(string $name): string
    {
        return ucwords(str_replace('_', ' ', preg_replace('/_id$/', '', $name) ?? $name));
    }

    private static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $value)));
    }

    /**
     * Small English pluralizer for table names (category → categories).
     */
    public static function pluralize(string $word): string
    {
        return match (true) {
            (bool) preg_match('/[^aeiou]y$/', $word)          => substr($word, 0, -1) . 'ies',
            (bool) preg_match('/(s|x|z|ch|sh)$/', $word)      => $word . 'es',
            default                                           => $word . 's',
        };
    }
}
