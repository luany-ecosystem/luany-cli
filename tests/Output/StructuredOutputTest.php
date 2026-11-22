<?php

namespace LuanyCli\Tests\Output;

use LuanyCli\Output\Messages;
use LuanyCli\Output\Output;
use PHPUnit\Framework\TestCase;

/**
 * --json, --dry-run and coded messages, end to end through bin/luany.
 */
class StructuredOutputTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir() . '/luany_cli_json_' . bin2hex(random_bytes(4));
        mkdir($this->project . '/vendor/luany/framework', 0777, true);
        file_put_contents($this->project . '/composer.json', json_encode(['require' => ['luany/framework' => '^1.7']]));
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->project));
    }

    /** @return array{int, string} */
    private function luany(string $args, array $env = []): array
    {
        $prefix = '';
        foreach ($env as $k => $v) {
            $prefix .= $k . '=' . escapeshellarg($v) . ' ';
        }
        $cmd = 'cd ' . escapeshellarg($this->project) . ' && ' . $prefix . 'LUANY_NO_UPDATE_CHECK=1 ' . escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg(dirname(__DIR__, 2) . '/bin/luany') . ' ' . $args . ' 2>&1';
        exec($cmd, $out, $code);

        return [$code, implode("\n", $out)];
    }

    /** @return array<string, mixed> */
    private function json(string $args, int $expectedCode = 0, array $env = []): array
    {
        [$code, $out] = $this->luany($args . ' --json', $env);
        $this->assertSame($expectedCode, $code, $out);
        $data = json_decode($out, true);
        $this->assertIsArray($data, "not JSON: {$out}");

        return $data;
    }

    public function test_json_envelope_for_a_generator(): void
    {
        $r = $this->json('make:controller Post');

        $this->assertSame('luany.cli/1', $r['schema']);
        $this->assertTrue($r['ok']);
        $this->assertSame('make:controller', $r['command']);
        $this->assertFalse($r['dry_run']);
        $this->assertSame('create', $r['plan'][0]['op']);
        $this->assertSame('app/Controllers/PostController.php', $r['plan'][0]['path']);
        $this->assertTrue($r['plan'][0]['applied']);
        $this->assertSame('LUANY-MAKE-003', $r['messages'][0]['code']);
        $this->assertFileExists($this->project . '/app/Controllers/PostController.php');
    }

    public function test_dry_run_plans_with_a_diff_and_writes_nothing(): void
    {
        $r = $this->json('make:model Post --dry-run');

        $this->assertTrue($r['dry_run']);
        $this->assertFalse($r['plan'][0]['applied']);
        $this->assertStringContainsString('+++ b/app/Models/Post.php', $r['plan'][0]['diff']);
        $this->assertStringContainsString('+class Post extends Model', $r['plan'][0]['diff']);
        $this->assertDirectoryDoesNotExist($this->project . '/app');

        [$code, $out] = $this->luany('make:view pages.about --dry-run');
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Would create views/pages/about.lte', $out);
        $this->assertDirectoryDoesNotExist($this->project . '/views');
    }

    public function test_errors_have_codes_and_hints(): void
    {
        $r = $this->json('make:controller', 1);
        $this->assertFalse($r['ok']);
        $this->assertSame('LUANY-CLI-005', $r['error']['code']);
        $this->assertSame('Usage: luany make:controller <ControllerName>', $r['error']['message']);
        $this->assertSame('luany make:controller Auth/Login', $r['error']['hint']);

        $r = $this->json('nope', 1);
        $this->assertSame('LUANY-CLI-004', $r['error']['code']);

        $r = $this->json("make:model 'x;y'", 1);
        $this->assertSame('LUANY-CLI-001', $r['error']['code']);
    }

    public function test_existing_file_is_a_warning(): void
    {
        $this->luany('make:model Post');
        $r = $this->json('make:model Post');

        $this->assertTrue($r['ok']);
        $this->assertSame([], $r['plan']);
        $this->assertSame('warning', $r['messages'][0]['level']);
        $this->assertSame('LUANY-MAKE-002', $r['messages'][0]['code']);
    }

    public function test_commands_without_structured_output_refuse_json_and_dry_run(): void
    {
        $r = $this->json('key:generate', 1);
        $this->assertSame('LUANY-CLI-002', $r['error']['code']);

        [$code, $out] = $this->luany('key:generate --dry-run');
        $this->assertSame(1, $code);
        $this->assertStringContainsString('does not support --dry-run yet; nothing was done', $out);
    }

    public function test_messages_follow_the_developer_language(): void
    {
        file_put_contents($this->project . '/.env', "LUANY_DEV_LANGUAGE=pt\n");

        $r = $this->json('make:view pages.sobre');
        $this->assertSame('LUANY-MAKE-003', $r['messages'][0]['code'], 'same code in every language');
        $this->assertSame('Vista criada: views/pages/sobre.lte', $r['messages'][0]['text']);

        [, $out] = $this->luany('make:controller');
        $this->assertStringContainsString('Utilização: luany make:controller <ControllerName>', $out);
    }

    public function test_catalogs_have_the_same_codes(): void
    {
        $this->assertSame(array_keys(Messages::catalog('en')), array_keys(Messages::catalog('pt')));
    }

    public function test_new_file_diff_format(): void
    {
        $this->assertSame("--- /dev/null\n+++ b/a.txt\n@@ -0,0 +1,2 @@\n+one\n+two\n", Output::diff('a.txt', "one\ntwo\n"));
    }
}
