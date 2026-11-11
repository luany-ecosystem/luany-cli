<?php

namespace LuanyCli\Support;

/**
 * Console — interactive prompts for CLI commands.
 *
 *   $io->ask('Project name', 'my-app');
 *   $io->confirm('Run migrations now?', true);          // [Y/n]
 *   $io->choice('Database', ['sqlite' => 'SQLite …', 'mysql' => 'MySQL'], 'sqlite');
 *
 * Reads from STDIN by default; tests inject a stream. End of input (EOF,
 * piped / non-interactive runs) always resolves to the default, so prompts
 * can never loop forever.
 */
final class Console
{
    /** @var resource */
    private $input;

    /**
     * @param resource|null $input
     */
    public function __construct($input = null, private readonly bool $colors = true)
    {
        $this->input = $input ?? STDIN;
    }

    /**
     * Whether a human is on the other end (STDIN is a terminal).
     */
    public static function isInteractive(): bool
    {
        return function_exists('stream_isatty') && @stream_isatty(STDIN);
    }

    public function ask(string $question, ?string $default = null): string
    {
        $hint = $default !== null && $default !== '' ? ' ' . $this->dim("[{$default}]") : '';
        $this->write("  {$question}{$hint}: ");

        $answer = $this->readLine();

        return $answer === null || $answer === '' ? (string) $default : $answer;
    }

    public function confirm(string $question, bool $default = true): bool
    {
        $hint = $default ? '[Y/n]' : '[y/N]';

        while (true) {
            $this->write("  {$question} " . $this->dim($hint) . ': ');
            $answer = $this->readLine();

            if ($answer === null || $answer === '') {
                return $default;
            }

            $answer = strtolower($answer);
            if (in_array($answer, ['y', 'yes', 's', 'sim'], true)) {
                return true;
            }
            if (in_array($answer, ['n', 'no', 'nao', 'não'], true)) {
                return false;
            }

            $this->error('Please answer yes or no.');
        }
    }

    /**
     * Numbered menu. Accepts the number or the key; Enter picks $default.
     *
     * @param array<string, string> $options key => description
     */
    public function choice(string $question, array $options, string $default): string
    {
        $keys = array_keys($options);

        $this->write("  {$question}\n");
        foreach ($keys as $i => $key) {
            $marker = $key === $default ? $this->accent('›') : ' ';
            $this->write(sprintf("   %s %2d  %-10s %s\n", $marker, $i + 1, $key, $this->dim($options[$key])));
        }

        while (true) {
            $this->write('  ' . $this->dim("Choose 1-" . count($keys) . " or a name [{$default}]") . ': ');
            $answer = $this->readLine();

            if ($answer === null || $answer === '') {
                return $default;
            }
            if (ctype_digit($answer) && isset($keys[(int) $answer - 1])) {
                return $keys[(int) $answer - 1];
            }
            if (isset($options[strtolower($answer)])) {
                return strtolower($answer);
            }

            $this->error("Unknown option [{$answer}].");
        }
    }

    public function info(string $message): void
    {
        $this->write('  ' . $this->color('32', '✓') . "  {$message}\n");
    }

    public function note(string $message): void
    {
        $this->write('  ' . $this->color('33', '→') . "  {$message}\n");
    }

    public function error(string $message): void
    {
        $this->write('  ' . $this->color('31', '✗') . "  {$message}\n");
    }

    public function write(string $text): void
    {
        echo $text;
    }

    private function readLine(): ?string
    {
        $line = fgets($this->input);
        return $line === false ? null : trim($line);
    }

    private function dim(string $text): string    { return $this->color('2', $text); }
    private function accent(string $text): string { return $this->color('36', $text); }

    private function color(string $code, string $text): string
    {
        return $this->colors ? "\033[{$code}m{$text}\033[0m" : $text;
    }
}
