<?php

namespace LuanyCli\Tests\Support;

use LuanyCli\Support\DriverCheck;
use PHPUnit\Framework\TestCase;

class DriverCheckTest extends TestCase
{
    public function test_problem_explains_how_to_install(): void
    {
        $missing = fn (string $ext) => false;
        $problem = DriverCheck::problem('sqlite', $missing);

        $this->assertStringContainsString('pdo_sqlite', $problem);
        $this->assertStringContainsString('sqlite3', $problem);
        $this->assertStringContainsString('php.ini', $problem);
        $this->assertStringContainsString('pdo_pgsql', DriverCheck::problem('pgsql', $missing));
    }

    public function test_no_problem_when_loaded(): void
    {
        $this->assertNull(DriverCheck::problem('mysql', fn (string $ext) => $ext === 'pdo_mysql'));
        $this->assertTrue(DriverCheck::isAvailable('oracle', fn () => false), 'unknown drivers are reported elsewhere');
    }
}
