<?php

namespace LuanyCli\Tests\Support;

use LuanyCli\Support\NetworkValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NetworkValidatorTest extends TestCase
{
    public function test_accepts_valid_hosts(): void
    {
        foreach (['localhost', '127.0.0.1', '0.0.0.0', 'my-app.test', '[::1]'] as $host) {
            $this->assertSame($host, NetworkValidator::host($host));
        }
    }

    /** @return array<string, array{string}> */
    public static function invalidHosts(): array
    {
        return [
            'empty'        => [''],
            'shell'        => ['localhost;id'],
            'leading dash' => ['-x'],
            'bare ipv6'    => ['::1'],
            'bad ipv6'     => ['[nope]'],
            'with port'    => ['localhost:80'],
            'trailing dot' => ['localhost.'],
            'bad ipv4'     => ['999.1.1.1'],
        ];
    }

    #[DataProvider('invalidHosts')]
    public function test_rejects_invalid_hosts(string $host): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NetworkValidator::host($host);
    }

    public function test_accepts_valid_ports(): void
    {
        $this->assertSame(1, NetworkValidator::port('1'));
        $this->assertSame(8000, NetworkValidator::port('8000'));
        $this->assertSame(65535, NetworkValidator::port(65535));
    }

    /** @return array<string, array{int|string}> */
    public static function invalidPorts(): array
    {
        return [
            'text'      => ['abc'],
            'zero'      => ['0'],
            'zero int'  => [0],
            'too big'   => ['65536'],
            'negative'  => [-1],
            'float'     => ['80.5'],
            'mixed'     => ['80abc'],
            'empty'     => [''],
        ];
    }

    #[DataProvider('invalidPorts')]
    public function test_rejects_invalid_ports(int|string $port): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NetworkValidator::port($port);
    }
}
