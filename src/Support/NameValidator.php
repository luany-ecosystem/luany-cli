<?php

namespace LuanyCli\Support;

/**
 * NameValidator
 *
 * Single source of truth for validating every user-supplied name that ends up
 * inside generated PHP/SQL/LTE code or inside a filesystem path.
 *
 * Every method either returns a safe, normalised value or throws
 * InvalidNameException with a message suitable for the terminal.
 */
final class NameValidator
{
    /** Valid PHP class name produced by the generators (StudlyCase). */
    public const CLASS_PATTERN = '/^[A-Z][A-Za-z0-9]*$/';

    /** Valid database column / field name (snake_case). */
    public const COLUMN_PATTERN = '/^[a-z_][a-z0-9_]*$/';

    /** Valid migration name (snake_case, must start with a letter). */
    public const MIGRATION_PATTERN = '/^[a-z][a-z0-9_]*$/';

    /** Valid view path segment (between dots), e.g. pages.blog-post.index. */
    public const VIEW_SEGMENT_PATTERN = '/^[A-Za-z0-9_][A-Za-z0-9_-]*$/';

    /**
     * Input accepted for conversion to StudlyCase: letters/digits, optionally
     * separated by single underscores or hyphens (snake_case / kebab-case).
     */
    private const CONVERTIBLE_PATTERN = '/^[A-Za-z][A-Za-z0-9]*(?:[_-][A-Za-z0-9]+)*$/';

    /** Columns that every generated migration already defines. */
    public const RESERVED_COLUMNS = ['id', 'created_at', 'updated_at'];

    /**
     * Validate a class name, converting snake_case / kebab-case / camelCase
     * input to StudlyCase. Anything else is rejected.
     *
     *   post       → Post
     *   blog_post  → BlogPost
     *   blog-post  → BlogPost
     *   ../x, Foo Bar, 1Foo, x'];// → InvalidNameException
     */
    public static function className(string $input, string $label = 'class name'): string
    {
        $input = trim($input);

        if ($input === '' || !preg_match(self::CONVERTIBLE_PATTERN, $input)) {
            throw new InvalidNameException(
                "Invalid {$label} [{$input}] — use StudlyCase letters and digits only (e.g. BlogPost, blog_post or blog-post)."
            );
        }

        $studly = implode('', array_map('ucfirst', preg_split('/[_-]/', $input) ?: []));

        if (!preg_match(self::CLASS_PATTERN, $studly)) {
            throw new InvalidNameException("Invalid {$label} [{$input}].");
        }

        return $studly;
    }

    /**
     * Validate a "Sub/Dir/Name" style class path (used by make:controller,
     * make:middleware, make:request, make:test). Each directory segment must
     * be a valid namespace segment — no '..', empty segments or separators.
     *
     * @return array{segments: array<int, string>, class: string}
     */
    public static function classPath(string $input, string $label = 'class name'): array
    {
        $normalised = str_replace('\\', '/', trim($input));
        $parts      = explode('/', $normalised);
        $class      = (string) array_pop($parts);

        $segments = [];
        foreach ($parts as $segment) {
            $segments[] = self::pathSegment($segment);
        }

        return [
            'segments' => $segments,
            'class'    => self::className($class, $label),
        ];
    }

    /**
     * Validate a single directory / namespace segment.
     * Rejects '', '.', '..' and anything containing separators or
     * characters that are not valid in a PHP namespace.
     */
    public static function pathSegment(string $segment): string
    {
        if ($segment === '' || $segment === '.' || $segment === '..'
            || str_contains($segment, '/') || str_contains($segment, '\\')
        ) {
            throw new InvalidNameException("Invalid path segment [{$segment}] — '..', empty segments and separators are not allowed.");
        }

        return self::className($segment, 'path segment');
    }

    /**
     * Validate a database column / field name (snake_case).
     */
    public static function column(string $name): string
    {
        if (!preg_match(self::COLUMN_PATTERN, $name)) {
            throw new InvalidNameException(
                "Invalid field name [{$name}] — use lowercase letters, digits and underscores only (e.g. first_name)."
            );
        }

        if (in_array($name, self::RESERVED_COLUMNS, true)) {
            throw new InvalidNameException(
                "Field name [{$name}] is reserved — it is generated automatically."
            );
        }

        return $name;
    }

    /**
     * Validate a field type against FieldParser::TYPES.
     */
    public static function fieldType(string $type, string $field = ''): string
    {
        if (!in_array($type, FieldParser::TYPES, true)) {
            $for = $field !== '' ? " for field [{$field}]" : '';
            throw new InvalidNameException(
                "Unknown field type [{$type}]{$for} — valid types: " . implode(', ', FieldParser::TYPES) . '.'
            );
        }

        return $type;
    }

    /**
     * Validate a list of field definitions (name + type).
     *
     * @param array<int, array{name: string, type: string}> $fields
     * @return array<int, array{name: string, type: string}>
     */
    public static function fields(array $fields): array
    {
        $seen = [];

        foreach ($fields as $field) {
            self::column($field['name']);
            self::fieldType($field['type'], $field['name']);

            if ($field['type'] === 'foreign' && !preg_match('/^[a-z][a-z0-9_]*_id$/', $field['name'])) {
                throw new InvalidNameException(
                    "Foreign field [{$field['name']}] must end in _id (e.g. category_id:foreign)."
                );
            }

            if (isset($seen[$field['name']])) {
                throw new InvalidNameException("Duplicate field name [{$field['name']}].");
            }
            $seen[$field['name']] = true;
        }

        return $fields;
    }

    /**
     * Validate a migration name (snake_case, e.g. create_users_table).
     */
    public static function migrationName(string $name): string
    {
        if (!preg_match(self::MIGRATION_PATTERN, $name)) {
            throw new InvalidNameException(
                "Invalid migration name [{$name}] — use snake_case (e.g. create_users_table)."
            );
        }

        return $name;
    }

    /**
     * Validate a dotted view name (e.g. pages.about, components.card).
     *
     * @return array<int, string> The validated segments.
     */
    public static function viewName(string $name): array
    {
        $segments = explode('.', $name);

        foreach ($segments as $segment) {
            if (!preg_match(self::VIEW_SEGMENT_PATTERN, $segment)) {
                throw new InvalidNameException(
                    "Invalid view name [{$name}] — use dot-separated segments of letters, digits, '_' or '-' (e.g. pages.about)."
                );
            }
        }

        return $segments;
    }
}
