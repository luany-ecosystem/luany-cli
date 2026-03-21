<?php

namespace LuanyCli\Support;

/**
 * FieldParser
 *
 * Converts a list of field definitions (name + type) into
 * the various code fragments needed by the scaffold stubs:
 * migration columns, fillable, casts, form inputs, show fields,
 * index table headers and rows.
 */
class FieldParser
{
    /** Valid types the developer can choose from. */
    public const TYPES = [
        'string', 'text', 'integer', 'boolean', 'email', 'date', 'decimal',
    ];

    // â”€â”€ Migration â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * @param array<array{name: string, type: string}> $fields
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

    private static function migrationColumn(string $name, string $type): string
    {
        return match ($type) {
            'text'    => "`{$name}` TEXT",
            'integer' => "`{$name}` INT NOT NULL DEFAULT 0",
            'boolean' => "`{$name}` TINYINT(1) NOT NULL DEFAULT 0",
            'email'   => "`{$name}` VARCHAR(150) NOT NULL",
            'date'    => "`{$name}` DATE",
            'decimal' => "`{$name}` DECIMAL(10, 2) UNSIGNED NOT NULL DEFAULT 0.00",
            default   => "`{$name}` VARCHAR(255) NOT NULL",
        };
    }

    // â”€â”€ Model â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * @param array<array{name: string, type: string}> $fields
     */
    public static function toFillable(array $fields): string
    {
        if (empty($fields)) {
            return '        //';
        }

        return implode("\n", array_map(
            fn($f) => "        '{$f['name']}',",
            $fields
        ));
    }

    /**
     * @param array<array{name: string, type: string}> $fields
     */
    public static function toCasts(array $fields): string
    {
        $castMap = ['boolean' => 'bool', 'integer' => 'int', 'decimal' => 'float'];
        $casts   = [];

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

    // â”€â”€ Views â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * @param array<array{name: string, type: string}> $fields
     */
    // Actualizar toFormFields â€” adicionar class="form-control" e checkbox-group
    public static function toFormFields(array $fields): string
    {
        if (empty($fields)) {
            return '                {{-- Add form fields here --}}';
        }

        $html = [];
        foreach ($fields as $field) {
            $label = ucwords(str_replace('_', ' ', $field['name']));

            if ($field['type'] === 'text') {
                $html[] = "                <div class=\"form-group\">\n"
                    . "                    <label for=\"{$field['name']}\">{$label}</label>\n"
                    . "                    <textarea name=\"{$field['name']}\" id=\"{$field['name']}\" class=\"form-control\" placeholder=\"Enter {$label}\" required></textarea>\n"
                    . "                </div>";
            } elseif ($field['type'] === 'boolean') {
                $html[] = "                <div class=\"form-group\">\n"
                    . "                    <div class=\"checkbox-group\">\n"
                    . "                        <input type=\"checkbox\" name=\"{$field['name']}\" id=\"{$field['name']}\" value=\"1\">\n"
                    . "                        <label for=\"{$field['name']}\">{$label}</label>\n"
                    . "                    </div>\n"
                    . "                </div>";
            } else {
                $inputMap = ['string' => 'text', 'email' => 'email', 'integer' => 'number', 'decimal' => 'number', 'date' => 'date'];
                $inputType = $inputMap[$field['type']] ?? 'text';
                $step = $field['type'] === 'decimal' ? ' step="0.01"' : '';
                $html[] = "                <div class=\"form-group\">\n"
                    . "                    <label for=\"{$field['name']}\">{$label}</label>\n"
                    . "                    <input type=\"{$inputType}\"{$step} name=\"{$field['name']}\" id=\"{$field['name']}\" class=\"form-control\" placeholder=\"Enter {$label}\" required>\n"
                    . "                </div>";
            }
        }

        return implode("\n", $html);
    }

    // Novo â€” toEditFields com values preenchidos
    /** @param array<int, array{name: string, type: string}> $fields */
    public static function toEditFields(array $fields, string $item): string
    {
        if (empty($fields)) {
            return '                {{-- Add form fields here --}}';
        }

        $html = [];
        foreach ($fields as $field) {
            $label = ucwords(str_replace('_', ' ', $field['name']));

            if ($field['type'] === 'text') {
                $html[] = "                <div class=\"form-group\">\n"
                    . "                    <label for=\"{$field['name']}\">{$label}</label>\n"
                    . "                    <textarea name=\"{$field['name']}\" id=\"{$field['name']}\" class=\"form-control\" placeholder=\"Enter {$label}\">{{ \${$item}->{$field['name']} }}</textarea>\n"
                    . "                </div>";
            } elseif ($field['type'] === 'boolean') {
                $html[] = "                <div class=\"form-group\">\n"
                    . "                    <div class=\"checkbox-group\">\n"
                    . "                        <input type=\"checkbox\" name=\"{$field['name']}\" id=\"{$field['name']}\" value=\"1\" {{ \${$item}->{$field['name']} ? 'checked' : '' }}>\n"
                    . "                        <label for=\"{$field['name']}\">{$label}</label>\n"
                    . "                    </div>\n"
                    . "                </div>";
            } else {
                $inputMap = ['string' => 'text', 'email' => 'email', 'integer' => 'number', 'decimal' => 'number', 'date' => 'date'];
                $inputType = $inputMap[$field['type']] ?? 'text';
                $step = $field['type'] === 'decimal' ? ' step="0.01"' : '';
                $html[] = "                <div class=\"form-group\">\n"
                    . "                    <label for=\"{$field['name']}\">{$label}</label>\n"
                    . "                    <input type=\"{$inputType}\"{$step} name=\"{$field['name']}\" id=\"{$field['name']}\" class=\"form-control\" placeholder=\"Enter {$label}\" value=\"{{ \${$item}->{$field['name']} }}\">\n"
                    . "                </div>";
            }
        }

        return implode("\n", $html);
    }

    // Novo â€” toValidationRules
    /** @param array<int, array{name: string, type: string}> $fields */
    public static function toValidationRules(array $fields): string
    {
        if (empty($fields)) {
            return '';
        }

        $ruleMap = [
            'string'  => "'required', 'string', 'max:255'",
            'text'    => "'required', 'string'",
            'email'   => "'required', 'email', 'max:150'",
            'integer' => "'required', 'integer'",
            'decimal' => "'required', 'numeric'",
            'boolean' => "'boolean'",
            'date'    => "'required', 'date'",
        ];

        $lines = [];
        foreach ($fields as $field) {
            $rules = $ruleMap[$field['type']] ?? "'required'";
            $lines[] = "            '{$field['name']}' => [{$rules}],";
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<array{name: string, type: string}> $fields
     */
    public static function toShowFields(array $fields, string $item): string
    {
        if (empty($fields)) {
            return '        {{-- Add fields here --}}';
        }

        return implode("\n", array_map(function ($field) use ($item) {
            $label = ucwords(str_replace('_', ' ', $field['name']));
            return "        <div class=\"field\">\n"
                . "            <label>{$label}</label>\n"
                . "            <span>{{ \${$item}->{$field['name']} }}</span>\n"
                . "        </div>";
        }, $fields));
    }

    /**
     * @param array<array{name: string, type: string}> $fields
     */
    public static function toIndexHeaders(array $fields): string
    {
        if (empty($fields)) {
            return '';
        }

        return implode("\n", array_map(function ($field) {
            $label = ucwords(str_replace('_', ' ', $field['name']));
            return "                    <th>{$label}</th>";
        }, $fields));
    }

    /**
     * @param array<array{name: string, type: string}> $fields
     */
    public static function toIndexRows(array $fields, string $item): string
    {
        if (empty($fields)) {
            return '';
        }

        return implode("\n", array_map(
            fn($f) => "                        <td>{{ \${$item}->{$f['name']} }}</td>",
            $fields
        ));
    }

    /** @param array<int, array{name: string, type: string}> $fields */
    public static function toFillableKeys(array $fields): string
    {
        if (empty($fields)) return '';
        return implode(', ', array_map(fn($f) => "'{$f['name']}'", $fields));
    }
}