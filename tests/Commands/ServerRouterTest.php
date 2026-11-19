<?php

namespace LuanyCli\Tests\Commands;

use LuanyCli\Commands\ServeCommand;
use PHPUnit\Framework\TestCase;

/**
 * Runs PHP's built-in server with the router used by `luany serve` / `luany dev`.
 */
class ServerRouterTest extends TestCase
{
    private string $dir;

    /** @var resource|null */
    private $process = null;

    private int $port;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/luany-router-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/public/assets', 0755, true);
        file_put_contents($this->dir . '/secret.txt', 'SECRET');
        file_put_contents($this->dir . '/public/assets/app.css', 'body{}');
        file_put_contents($this->dir . '/public/index.php',
            '<?php echo "app:" . parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) . "|" . $_SERVER["SCRIPT_NAME"];');

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->port = (int) substr((string) stream_socket_get_name($socket, false), strrpos((string) stream_socket_get_name($socket, false), ':') + 1);
        fclose($socket);

        $cmd = ServeCommand::buildCommand('127.0.0.1', $this->port, $this->dir . '/public');
        $this->process = proc_open('exec ' . $cmd, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);

        for ($i = 0; $i < 50 && @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.2) === false; $i++) {
            usleep(100_000);
        }
    }

    protected function tearDown(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }
        array_map('unlink', array_filter([
            $this->dir . '/public/assets/app.css', $this->dir . '/public/index.php', $this->dir . '/secret.txt',
        ], 'is_file'));
        @rmdir($this->dir . '/public/assets');
        @rmdir($this->dir . '/public');
        @rmdir($this->dir);
    }

    private function get(string $uri): string
    {
        $context = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);

        return (string) @file_get_contents("http://127.0.0.1:{$this->port}{$uri}", false, $context);
    }

    public function test_router_script_exists(): void
    {
        $this->assertFileExists(ServeCommand::routerPath());
    }

    public function test_dotted_routes_reach_the_application(): void
    {
        $this->assertSame('app:/sitemap.xml|/index.php', $this->get('/sitemap.xml'));
        $this->assertSame('app:/en/search.json|/index.php', $this->get('/en/search.json'));
        $this->assertSame('app:/users/7|/index.php', $this->get('/users/7'));
        $this->assertSame('app:/|/index.php', $this->get('/'));
    }

    public function test_static_files_are_served_and_root_is_not_escaped(): void
    {
        $this->assertSame('body{}', $this->get('/assets/app.css'));
        $this->assertStringNotContainsString('SECRET', $this->get('/../secret.txt'));
        $this->assertStringNotContainsString('SECRET', $this->get('/%2e%2e/secret.txt'));
    }
}
