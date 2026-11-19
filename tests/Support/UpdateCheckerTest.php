<?php

namespace LuanyCli\Tests\Support;

use LuanyCli\Commands\SelfUpdateCommand;
use LuanyCli\Support\UpdateChecker;
use LuanyCli\WelcomeMessage;
use PHPUnit\Framework\TestCase;

class UpdateCheckerTest extends TestCase
{
    private string $cache;
    private int $calls = 0;

    protected function setUp(): void
    {
        $this->cache = sys_get_temp_dir() . '/luany-update-' . uniqid() . '/check.json';
        UpdateChecker::useCacheFile($this->cache);
        UpdateChecker::useFetcher(function () {
            $this->calls++;
            return (string) json_encode(['packages' => ['luany/cli' => [
                ['version' => 'v1.8.0-RC1'], ['version' => 'v1.7.0'], ['version' => 'v1.6.1'], ['version' => 'dev-main'],
            ]]]);
        });
        putenv('LUANY_NO_UPDATE_CHECK');
        putenv('CI');
    }

    protected function tearDown(): void
    {
        UpdateChecker::useCacheFile(null);
        UpdateChecker::useFetcher(null);
        @unlink($this->cache);
        @rmdir(dirname($this->cache));
        putenv('LUANY_NO_UPDATE_CHECK=1');   // the rest of the suite stays offline
    }

    public function test_parses_the_latest_stable_version(): void
    {
        $this->assertSame('1.7.0', UpdateChecker::latest());
        $this->assertNull(UpdateChecker::parse('{"packages":{}}'));
        $this->assertNull(UpdateChecker::parse('not json'));
    }

    public function test_reports_a_newer_version_only(): void
    {
        $this->assertSame('1.7.0', UpdateChecker::newerThan('v1.5.1'));
        $this->assertNull(UpdateChecker::newerThan('v1.7.0'));
        $this->assertNull(UpdateChecker::newerThan('dev-main'));
        $this->assertStringContainsString('luany self-update', WelcomeMessage::updateNotice('v1.5.1'));
        $this->assertSame('', WelcomeMessage::updateNotice('v1.7.0'));
    }

    public function test_checks_packagist_once_a_day(): void
    {
        UpdateChecker::latest();
        UpdateChecker::latest();
        UpdateChecker::newerThan('v1.0.0');
        $this->assertSame(1, $this->calls);

        UpdateChecker::latest(refresh: true);
        $this->assertSame(2, $this->calls);
    }

    public function test_network_failure_is_silent_and_keeps_the_last_known_version(): void
    {
        UpdateChecker::latest();
        UpdateChecker::useFetcher(fn () => null);
        $this->assertSame('1.7.0', UpdateChecker::latest(refresh: true));
    }

    public function test_disabled_in_ci_and_by_env(): void
    {
        putenv('CI=true');
        $this->assertNull(UpdateChecker::newerThan('v1.0.0'));
        putenv('CI');
        putenv('LUANY_NO_UPDATE_CHECK=1');
        $this->assertNull(UpdateChecker::newerThan('v1.0.0'));
        putenv('LUANY_NO_UPDATE_CHECK');
        $this->assertSame(0, $this->calls);
    }

    public function test_self_update_detects_a_project_install(): void
    {
        putenv('COMPOSER_HOME=/home/me/.config/composer');
        $this->assertNull(SelfUpdateCommand::projectInstall('/home/me/.config/composer/vendor/luany/cli'));
        $this->assertSame('/srv/app', SelfUpdateCommand::projectInstall('/srv/app/vendor/luany/cli'));
        $this->assertNull(SelfUpdateCommand::projectInstall('/home/me/code/luany-cli'));
        putenv('COMPOSER_HOME');
    }
}
