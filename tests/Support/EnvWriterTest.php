<?php

namespace LuanyCli\Tests\Support;

use LuanyCli\Support\EnvWriter;
use PHPUnit\Framework\TestCase;

class EnvWriterTest extends TestCase
{
    public function test_replaces_uncomments_appends_and_quotes(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($file, "APP_NAME=Luany\nDB_CONNECTION=sqlite\n# DB_HOST=127.0.0.1\n");

        EnvWriter::set($file, [
            'DB_CONNECTION' => 'mysql',
            'DB_HOST'       => 'db.local',
            'DB_PASS'       => 'p@ss word$1',
        ]);

        $this->assertSame(
            "APP_NAME=Luany\nDB_CONNECTION=mysql\nDB_HOST=db.local\nDB_PASS=\"p@ss word\$1\"\n",
            file_get_contents($file)
        );
        @unlink($file);
    }

    public function test_rejects_invalid_keys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EnvWriter::set(tempnam(sys_get_temp_dir(), 'env'), ["X\nY" => '1']);
    }
}
