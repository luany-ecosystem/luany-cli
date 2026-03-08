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
}