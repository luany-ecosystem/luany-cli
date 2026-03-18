<?php

use LuanyCli\Support\EnvParser;
use PHPUnit\Framework\TestCase;

class EnvParserTest extends TestCase
{
    private string $envFile;

    protected function setUp(): void
    {
        $this->envFile = sys_get_temp_dir() . '/luany_env_test_' . uniqid() . '.env';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->envFile)) {
            unlink($this->envFile);
        }
    }

    public function test_parses_simple_key_value(): void
    {
        file_put_contents($this->envFile, "APP_NAME=Luany\n");
        $this->assertSame('Luany', EnvParser::parse($this->envFile)['APP_NAME']);
    }

    public function test_strips_double_quotes(): void
    {
        file_put_contents($this->envFile, 'APP_NAME="Luany App"' . "\n");
        $this->assertSame('Luany App', EnvParser::parse($this->envFile)['APP_NAME']);
    }

    public function test_strips_single_quotes(): void
    {
        file_put_contents($this->envFile, "APP_NAME='Luany App'\n");
        $this->assertSame('Luany App', EnvParser::parse($this->envFile)['APP_NAME']);
    }

    public function test_skips_comments(): void
    {
        file_put_contents($this->envFile, "# comment\nAPP_NAME=Luany\n");
        $result = EnvParser::parse($this->envFile);
        $this->assertArrayNotHasKey('# comment', $result);
        $this->assertSame('Luany', $result['APP_NAME']);
    }

    public function test_handles_base64_value_with_equals(): void
    {
        $key = 'base64:' . base64_encode(random_bytes(32));
        file_put_contents($this->envFile, "APP_KEY={$key}\n");
        $this->assertSame($key, EnvParser::parse($this->envFile)['APP_KEY']);
    }

    public function test_returns_empty_array_for_missing_file(): void
    {
        $this->assertSame([], EnvParser::parse('/nonexistent/.env'));
    }

    public function test_skips_lines_without_equals(): void
    {
        file_put_contents($this->envFile, "INVALID\nAPP_NAME=Luany\n");
        $result = EnvParser::parse($this->envFile);
        $this->assertArrayNotHasKey('INVALID', $result);
    }
}

