<?php

namespace LuanyCli\Output;

use LuanyCli\Env;

/**
 * Output — how structured commands report.
 *
 * Human mode prints lines as they happen. With --json nothing is printed
 * until the end, when the luany.cli/1 envelope is written:
 *
 *   {"schema":"luany.cli/1","ok":true,"command":"make:model","cli_version":"1.11.0",
 *    "dry_run":false,"data":{},"plan":[{"op":"create","path":"app/Models/Post.php",
 *    "applied":true,"diff":"..."}],"messages":[{"code":"LUANY-MAKE-003","level":"success","text":"..."}]}
 *
 * "schema" is the format version, independent of the CLI version: new fields
 * keep it, removing or changing a field moves it to luany.cli/2.
 *
 * With --dry-run, files are planned (with a unified diff) but never written.
 */
final class Output
{
    public const SCHEMA = 'luany.cli/1';

    private static ?self $current = null;

    /** @var list<array<string, mixed>> */
    private array $plan = [];

    /** @var list<array{code: string, level: string, text: string}> */
    private array $messages = [];

    /** @var array<string, mixed> */
    private array $data = [];

    public readonly Messages $text;

    public function __construct(
        public readonly string $command = '',
        public readonly bool $json = false,
        public readonly bool $dryRun = false,
        string $language = 'en',
    ) {
        $this->text = new Messages($language);
    }

    /** The output of the running command (a plain human one in tests). */
    public static function current(): self
    {
        return self::$current ??= new self();
    }

    public static function begin(self $output): self
    {
        return self::$current = $output;
    }

    public static function reset(): void
    {
        self::$current = null;
    }

    // ── Reporting ───────────────────────────────────────────────────────────

    /**
     * @param 'success'|'info'|'warning'|'error' $level
     * @param array<string, string|int>          $params
     */
    public function say(string $code, array $params = [], string $level = 'success', ?string $text = null): void
    {
        $text ??= $this->text->text($code, $params);
        $this->messages[] = ['code' => $code, 'level' => $level, 'text' => $text];

        if (!$this->json) {
            $icon = ['success' => "\033[32m✓\033[0m", 'info' => "\033[36m→\033[0m", 'warning' => "\033[33m⚠\033[0m", 'error' => "\033[31m✗\033[0m"][$level];
            $line = "\n  {$icon}  {$text}\n\n";
            $level === 'error' ? fwrite(STDERR, $line) : print($line);
        }
    }

    /** @param array<string, mixed> $data */
    public function data(array $data): void
    {
        $this->data = array_merge($this->data, $data);
    }

    // ── Files ───────────────────────────────────────────────────────────────

    /**
     * Create a file under the project (path relative to the base path).
     * With --dry-run it is only added to the plan. Returns false (and warns)
     * when the file already exists.
     *
     * @param string $kind  Message kind: controller, model, view, … ("Controller created")
     * @param string $label Name shown in the "already exists" warning
     */
    public function createFile(string $relative, string $contents, string $kind, string $label, bool $existsIsError = false): bool
    {
        $path = Env::basePath() . '/' . $relative;

        if (file_exists($path)) {
            if ($existsIsError) {
                throw new CommandError('LUANY-MAKE-002', ['name' => $label, 'path' => $relative]);
            }
            $this->say('LUANY-MAKE-002', ['name' => $label, 'path' => $relative], 'warning');
            return false;
        }

        $this->plan[] = ['op' => 'create', 'path' => $relative, 'applied' => !$this->dryRun, 'diff' => self::diff($relative, $contents)];

        if ($this->dryRun) {
            $this->say('LUANY-MAKE-004', ['path' => $relative], 'info');
            if (!$this->json) {
                echo self::colorDiff(self::diff($relative, $contents)) . "\n";
            }
            return true;
        }

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        if (file_put_contents($path, $contents) === false) {
            throw new CommandError('LUANY-MAKE-005', ['path' => $relative]);
        }

        $this->say('LUANY-MAKE-003', ['created' => $this->text->created($kind), 'path' => $relative]);
        return true;
    }

    // ── Envelope ────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function envelope(string $cliVersion, ?CommandError $error = null): array
    {
        $envelope = [
            'schema'      => self::SCHEMA,
            'ok'          => $error === null,
            'command'     => $this->command,
            'cli_version' => $cliVersion,
            'dry_run'     => $this->dryRun,
            'data'        => (object) $this->data,
            'plan'        => $this->plan,
            'messages'    => $this->messages,
        ];

        if ($error !== null) {
            $envelope['error'] = [
                'code'    => $error->messageCode,
                'message' => $this->text->text($error->messageCode, $error->params),
                'hint'    => $error->hint,
            ];
        }

        return $envelope;
    }

    /** @return list<array<string, mixed>> */
    public function plan(): array
    {
        return $this->plan;
    }

    /** @return list<array{code: string, level: string, text: string}> */
    public function messages(): array
    {
        return $this->messages;
    }

    /** Unified diff of a new file. */
    public static function diff(string $relative, string $contents): string
    {
        $lines = $contents === '' ? [] : explode("\n", rtrim($contents, "\n"));

        return "--- /dev/null\n+++ b/{$relative}\n@@ -0,0 +1," . count($lines) . " @@\n"
            . implode('', array_map(fn (string $l): string => "+{$l}\n", $lines));
    }

    private static function colorDiff(string $diff): string
    {
        return implode("\n", array_map(
            fn (string $l): string => '     ' . (str_starts_with($l, '+') && !str_starts_with($l, '+++') ? "\033[32m{$l}\033[0m" : "\033[2m{$l}\033[0m"),
            explode("\n", rtrim($diff, "\n"))
        ));
    }
}
