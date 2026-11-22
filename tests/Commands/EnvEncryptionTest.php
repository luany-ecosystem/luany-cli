<?php

namespace LuanyCli\Tests\Commands;

use Dotenv\Dotenv;
use Luany\Framework\Support\EnvironmentCipher;
use LuanyCli\Commands\Env\EnvDecryptCommand;
use LuanyCli\Commands\Env\EnvEditCommand;
use LuanyCli\Commands\Env\EnvEncryptCommand;
use LuanyCli\Commands\Env\EnvSetCommand;
use LuanyCli\Support\EncryptedEnv;
use PHPUnit\Framework\TestCase;

class EnvEncryptionTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/luany_envenc_' . uniqid();
        mkdir($this->base . '/vendor', 0755, true);
        file_put_contents($this->base . '/vendor/autoload.php', '<?php require ' . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ';');
        file_put_contents($this->base . '/.env', "APP_NAME=Demo\nDB_PASS=s3cret\n");
        foreach (['LUANY_ENV_KEY', 'LUANY_ENV_KEY_PRODUCTION'] as $v) {
            putenv($v);
            unset($_SERVER[$v], $_ENV[$v]);
        }
        EnvEditCommand::$editor = null;
    }

    protected function tearDown(): void
    {
        EnvEditCommand::$editor = null;
        foreach (array_filter(glob($this->base . '/{,.,vendor/}*', GLOB_BRACE) ?: [], 'is_file') as $f) {
            unlink($f);
        }
        @rmdir($this->base . '/vendor');
        @rmdir($this->base);
    }

    /** @param class-string $command @param list<string> $args @return array{int, string} */
    private function run_(string $command, array $args): array
    {
        ob_start();
        $code = $command::run($this->base, $args);
        return [$code, (string) ob_get_clean()];
    }

    private function key(string $output): string
    {
        $this->assertSame(1, preg_match('/(base64:[A-Za-z0-9+\/=]{44})/', $output, $m), $output);
        return $m[1];
    }

    public function test_encrypt_generates_a_key_once_and_decrypt_restores_the_file(): void
    {
        [$code, $out] = $this->run_(EnvEncryptCommand::class, []);
        $this->assertSame(0, $code, $out);
        $key = $this->key($out);
        $this->assertStringContainsString('LUANY_ENV_KEY=' . $key, $out);
        $this->assertFileExists($this->base . '/.env.encrypted');
        $this->assertStringNotContainsString('s3cret', (string) file_get_contents($this->base . '/.env.encrypted'));

        [$code, $out] = $this->run_(EnvEncryptCommand::class, []);
        $this->assertSame(1, $code, 'no silent overwrite');
        $this->assertStringContainsString('--force', $out);

        unlink($this->base . '/.env');
        [$code] = $this->run_(EnvDecryptCommand::class, ['--key=' . $key]);
        $this->assertSame(0, $code);
        $this->assertSame("APP_NAME=Demo\nDB_PASS=s3cret\n", file_get_contents($this->base . '/.env'));

        [$code, $out] = $this->run_(EnvDecryptCommand::class, ['--key=' . $key]);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('already exists', $out);
    }

    public function test_environment_files_and_key_from_the_environment(): void
    {
        file_put_contents($this->base . '/.env.production', "APP_ENV=production\nDB_PASS=prod\n");
        $key = EnvironmentCipher::generateKey();
        putenv('LUANY_ENV_KEY_PRODUCTION=' . $key);

        [$code, $out] = $this->run_(EnvEncryptCommand::class, ['--env=production']);
        $this->assertSame(0, $code, $out);
        $this->assertStringNotContainsString('Key', $out, 'existing key reused, nothing printed');

        $payload = (string) file_get_contents($this->base . '/.env.production.encrypted');
        $this->assertSame("APP_ENV=production\nDB_PASS=prod\n", EnvironmentCipher::decrypt($payload, $key, 'production'));
    }

    public function test_set_replaces_and_appends_values_without_writing_plain_text(): void
    {
        $key = EnvironmentCipher::generateKey();
        $this->run_(EnvEncryptCommand::class, ['--key=' . $key]);
        unlink($this->base . '/.env');

        $tricky = "p@ss w0rd #1 \$HOME 'q' \\";
        [$code, $out] = $this->run_(EnvSetCommand::class, ['DB_PASS=changed', 'API_TOKEN=' . $tricky, '--key=' . $key]);
        $this->assertSame(0, $code, $out);
        $this->assertFileDoesNotExist($this->base . '/.env');

        $values = Dotenv::parse(EnvironmentCipher::decrypt((string) file_get_contents($this->base . '/.env.encrypted'), $key));
        $this->assertSame('Demo', $values['APP_NAME']);
        $this->assertSame('changed', $values['DB_PASS']);
        $this->assertSame($tricky, $values['API_TOKEN'], 'quoted literally');
    }

    public function test_edit_reencrypts_the_editor_result_and_removes_the_temp_file(): void
    {
        $key = EnvironmentCipher::generateKey();
        $this->run_(EnvEncryptCommand::class, ['--key=' . $key]);
        $seen = null;
        EnvEditCommand::$editor = function (string $file) use (&$seen): int {
            $seen = $file;
            file_put_contents($file, file_get_contents($file) . "NEW=1\n");
            return 0;
        };

        [$code, $out] = $this->run_(EnvEditCommand::class, ['--key=' . $key]);
        $this->assertSame(0, $code, $out);
        $this->assertFileDoesNotExist((string) $seen);
        $this->assertStringContainsString('NEW=1', EnvironmentCipher::decrypt((string) file_get_contents($this->base . '/.env.encrypted'), $key));

        EnvEditCommand::$editor = fn () => 0;
        [, $out] = $this->run_(EnvEditCommand::class, ['--key=' . $key]);
        $this->assertStringContainsString('No changes', $out);
    }

    public function test_edit_temp_files_are_private_and_editor_leftovers_are_removed(): void
    {
        $key = EnvironmentCipher::generateKey();
        $this->run_(EnvEncryptCommand::class, ['--key=' . $key]);
        $dir = null;
        $modes = [];
        EnvEditCommand::$editor = function (string $file) use (&$dir, &$modes): int {
            $dir = dirname($file);
            $modes = [fileperms($dir) & 0777, fileperms($file) & 0777];
            file_put_contents($dir . '/.' . basename($file) . '.swp', 'SECRET');
            file_put_contents($file . '~', 'SECRET');
            return 1;
        };

        [$code] = $this->run_(EnvEditCommand::class, ['--key=' . $key]);
        $this->assertSame(1, $code, 'a failing editor changes nothing');
        if (DIRECTORY_SEPARATOR === '/') {
            $this->assertSame([0700, 0600], $modes);
        }
        $this->assertDirectoryDoesNotExist((string) $dir, 'swap and backup files are removed with the directory');
    }

    public function test_missing_and_wrong_keys_fail_clearly(): void
    {
        $this->run_(EnvEncryptCommand::class, []);

        [$code, $out] = $this->run_(EnvSetCommand::class, ['A=1']);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('LUANY_ENV_KEY', $out);

        [$code, $out] = $this->run_(EnvSetCommand::class, ['A=1', '--key=' . EnvironmentCipher::generateKey()]);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('wrong key', $out);
    }

    public function test_set_values_helper(): void
    {
        $this->assertSame("A=1\nB=2\n", EncryptedEnv::setValues("A=0\n", ['A' => '1', 'B' => '2']));
        $this->expectException(\InvalidArgumentException::class);
        EncryptedEnv::setValues('', ['bad-name' => 'x']);
    }
}
