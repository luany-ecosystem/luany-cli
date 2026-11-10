<?php

namespace LuanyCli\Tests\Commands;

use LuanyCli\Commands\ServeCommand;
use PHPUnit\Framework\TestCase;

class ServeCommandTest extends TestCase
{
    public function test_command_name(): void
    {
        $this->assertSame('serve', (new ServeCommand())->name());
    }

    public function test_command_description_is_not_empty(): void
    {
        $this->assertNotEmpty((new ServeCommand())->description());
    }

    // -- Shell argument hardening (#3) -----------------------------------------

    public function test_build_command_uses_php_binary_and_escapes_arguments(): void
    {
        $cmd = ServeCommand::buildCommand('localhost', 8000, '/tmp/my project/public');

        $this->assertStringStartsWith(escapeshellarg(PHP_BINARY) . ' -S ', $cmd);
        $this->assertStringContainsString(escapeshellarg('localhost:8000'), $cmd);
        $this->assertStringContainsString(escapeshellarg('/tmp/my project/public'), $cmd);
    }

    /** @return array<string, array{string}> */
    public static function maliciousHosts(): array
    {
        return [
            'command chain'    => ['localhost; touch /tmp/pwned'],
            'subshell'         => ['$(id)'],
            'backticks'        => ['`id`'],
            'space'            => ['local host'],
            'option injection' => ['-dauto_prepend_file=/etc/passwd'],
            'empty'            => [''],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('maliciousHosts')]
    public function test_rejects_malicious_host(string $host): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ServeCommand())->handle([$host, '8000']);
    }

    /** @return array<string, array{string}> */
    public static function invalidPorts(): array
    {
        return [
            'injection' => ['8000; id'],
            'zero'      => ['0'],
            'too large' => ['65536'],
            'text'      => ['abc'],
            'negative'  => ['-1'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidPorts')]
    public function test_rejects_invalid_port(string $port): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ServeCommand())->handle(['localhost', $port]);
    }
}
