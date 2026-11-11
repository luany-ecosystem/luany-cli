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
 * Types:
 *   string    VARCHAR(255)            text input
 *   text      TEXT                    textarea
 *   integer   INT                     number input        cast int
 *   decimal   DECIMAL(10,2)           number input        cast decimal:2
 *   boolean   TINYINT(1)              checkbox            cast bool
 *   email     VARCHAR(150) + unique   email input         unique rule
 *   date      DATE                    date input          cast date
 *   datetime  DATETIME                datetime-local      cast datetime
 *   foreign   INT UNSIGNED + FK       number input        cast int, exists rule,
 *             (name must end in _id)                      belongsTo relation
 */
class FieldParser
{
    /** Valid types the developer can choose from. */
    public const TYPES = [
        'string', 'text', 'integer', 'decimal', 'boolean', 'email', 'date', 'datetime', 'foreign',
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

    // ── Migration ─────────────────────────────────────────────────────────────

    /**
     * @param array<int, array{name: string, type: string}> $fields
     */
    public static function toMigrationColumns(array $fields): string
    {
        if (empty($fields)) {
            return '';
        }

        $lines = [];
        foreach ($fields as $field) {
            $lines[] = '                ' . self::migrationColumn($field['name'], $field['type']) . ',';
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Indexes and foreign keys, appended after the last column (each line
     * starts with a comma).
     *
     * @param array<int, array{name: string, type: string}> $fields
     */
    public static function toMigrationConstraints(array $fields, string $table): string
    {
        $lines = [];

        foreach ($fields as $field) {
            if ($field['type'] === 'email') {
                $lines[] = "                UNIQUE KEY `{$table}_{$field['name']}_unique` (`{$field['name']}`)";
            }

            if ($field['type'] === 'foreign') {
                $references = self::referencedTable($field['name']);
                $lines[] = "                CONSTRAINT `{$table}_{$field['name']}_foreign` FOREIGN KEY (`{$field['name']}`)"
                    . " REFERENCES `{$references}` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE";
            }
        }

        return $lines === [] ? '' : ",\n" . implode(",\n", $lines);
    }

    private static function migrationColumn(string $name, string $type): string
    {
        return match ($type) {
            'text'     => "`{$name}` TEXT",
            'integer'  => "`{$name}` INT NOT NULL DEFAULT 0",
            'boolean'  => "`{$name}` TINYINT(1) NOT NULL DEFAULT 0",
            'email'    => "`{$name}` VARCHAR(150) NOT NULL",
            'date'     => "`{$name}` DATE NULL",
            'datetime' => "`{$name}` DATETIME NULL",
            'decimal'  => "`{$name}` DECIMAL(10, 2) NOT NULL DEFAULT 0.00",
            'foreign'  => "`{$name}` INT UNSIGNED NOT NULL",
            default    => "`{$name}` VARCHAR(255) NOT NULL",
        };
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
     * @param array<int, array{name: string, type: string}> $fields
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
     * @param array<int, array{name: string, type: string}> $fields
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
     * @param array<int, array{name: string, type: string}> $fields
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
     * @param array<int, array{name: string, type: string}> $fields
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
     * @param array<int, array{name: string, type: string}> $fields
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
     * @param array<int, array{name: string, type: string}> $fields
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
            $lines[] = "            '{$name}' => [{$rules}],";
        }

        return implode("\n", $lines);
    }

    // ── Views ─────────────────────────────────────────────────────────────────

    /**
     * @param array<int, array{name: string, type: string}> $fields
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
     * @param array<int, array{name: string, type: string}> $fields
     */
    public static function toEditFields(array $fields, string $item): string
    {
        if (empty($fields)) {
            return '                {{-- Add form fields here --}}';
        }

        return implode("\n", array_map(fn ($f) => self::formField($f, $item), $fields));
    }

    /**
     * @param array<int, array{name: string, type: string}> $fields
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
     * @param array<int, array{name: string, type: string}> $fields
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
     * @param array<int, array{name: string, type: string}> $fields
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
     * @param array{name: string, type: string} $field
     */
    private static function formField(array $field, ?string $item): string
    {
        $name  = $field['name'];
        $label = self::label($name);

        if ($field['type'] === 'text') {
            $value = $item === null ? '' : "{{ \${$item}->{$name} }}";
            $req   = $item === null ? ' required' : '';
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
        $required = $item === null && !in_array($field['type'], ['date', 'datetime'], true) ? ' required' : '';

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
     * @param array{name: string, type: string} $field
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
