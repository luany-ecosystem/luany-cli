<?php

namespace LuanyCli\Output;

/**
 * Message catalog with stable codes (LUANY-<AREA>-<NNN>).
 *
 * The code never changes between languages or versions; the text follows
 * the project's developer language (LUANY_DEV_LANGUAGE = en | pt).
 * Templates use :name placeholders.
 */
final class Messages
{
    /** @var array<string, array<string, string>> */
    private static array $catalogs = [];

    public function __construct(public readonly string $language = 'en')
    {
    }

    /** @param array<string, string|int> $params */
    public function text(string $code, array $params = []): string
    {
        $template = self::catalog($this->language)[$code] ?? self::catalog('en')[$code] ?? $code;

        return self::fill($template, $params);
    }

    /** "Controller created" / "Controlador criado" for a generated kind. */
    public function created(string $kind): string
    {
        return $this->text("kind.{$kind}.created");
    }

    /** @return array<string, string> */
    public static function catalog(string $language): array
    {
        if (!isset(self::$catalogs[$language])) {
            $file = dirname(__DIR__) . "/Resources/messages/{$language}.php";
            /** @var array<string, string> $catalog */
            $catalog = is_file($file) ? require $file : [];
            self::$catalogs[$language] = $catalog;
        }

        return self::$catalogs[$language];
    }

    /** @param array<string, string|int> $params */
    private static function fill(string $template, array $params): string
    {
        if ($params === []) {
            return $template;
        }
        uksort($params, fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($params as $key => $value) {
            $template = str_replace(':' . $key, (string) $value, $template);
        }

        return $template;
    }
}
