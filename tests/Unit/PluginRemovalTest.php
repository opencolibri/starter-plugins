<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Tests\Unit;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Util\Git;
use OpenColibri\Starter\Plugins\PluginRemoval;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

#[CoversClass(PluginRemoval::class)]
#[CoversClass(Git::class)]
final class PluginRemovalTest extends TestCase
{
    private const string LOCK = '{"packages": [{"name": "sylius/cms-plugin"}], "packages-dev": [{"name": "acme/dev-tool"}]}';

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/plugin_removal_test_' . uniqid();
        (new Filesystem())->mkdir($this->directory . '/migrations');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testLocks(): void
    {
        static::assertTrue(PluginRemoval::locks(self::LOCK, 'sylius/cms-plugin'));
        static::assertTrue(PluginRemoval::locks(self::LOCK, 'acme/dev-tool'));
        static::assertFalse(PluginRemoval::locks(self::LOCK, 'dedi/sylius-seo-plugin'));
        // Unreadable: nothing proves the package is missing.
        static::assertTrue(PluginRemoval::locks('not json', 'dedi/sylius-seo-plugin'));
    }

    public function testAPluginOutsideAGitRepositoryMayBeDeployed(): void
    {
        static::assertTrue(PluginRemoval::mayBeDeployed($this->app(), 'dedi/sylius-seo-plugin'));
    }

    public function testAPluginInAnIgnoredApplicationMayBeDeployed(): void
    {
        $this->git('init', '--quiet');
        file_put_contents($this->directory . '/.gitignore', "/app\n");
        (new Filesystem())->mkdir($this->directory . '/app');

        static::assertTrue(PluginRemoval::mayBeDeployed(new App('app', $this->directory . '/app'), 'dedi/sylius-seo-plugin'));
    }

    public function testAPluginWhosePackageIsCommittedMayBeDeployed(): void
    {
        $this->commit('composer.lock', self::LOCK);

        static::assertTrue(PluginRemoval::mayBeDeployed($this->app(), 'sylius/cms-plugin'));
    }

    public function testAPluginNeverCommittedWasNotDeployed(): void
    {
        $this->commit('composer.lock', self::LOCK);
        // Installed since the last commit: in the working copy only.
        file_put_contents($this->directory . '/composer.lock', '{"packages": [{"name": "dedi/sylius-seo-plugin"}]}');
        file_put_contents($this->directory . '/migrations/Version1.php', '<?php');

        static::assertFalse(PluginRemoval::mayBeDeployed($this->app(), 'dedi/sylius-seo-plugin', [$this->directory . '/migrations/Version1.php']));
    }

    public function testAPluginWhoseGeneratedMigrationIsCommittedMayBeDeployed(): void
    {
        $this->commit('composer.lock', self::LOCK);
        $this->commit('migrations/Version1.php', '<?php');

        static::assertTrue(PluginRemoval::mayBeDeployed($this->app(), 'dedi/sylius-seo-plugin', [$this->directory . '/migrations/Version1.php']));
    }

    public function testAnInstallAfterACommittedRemovalWasNotDeployed(): void
    {
        $this->commit('composer.lock', self::LOCK);
        // An install and a removal committed before, then an install in the working copy only.
        $this->commit('migrations/Version1.php', '<?php');
        $this->commit('migrations/Version2.php', '<?php');
        file_put_contents($this->directory . '/migrations/Version3.php', '<?php');

        static::assertFalse(PluginRemoval::mayBeDeployed($this->app(), 'dedi/sylius-seo-plugin', [
            $this->directory . '/migrations/Version1.php',
            $this->directory . '/migrations/Version2.php',
            $this->directory . '/migrations/Version3.php',
        ]));
    }

    private function app(): App
    {
        return new App('app', $this->directory);
    }

    private function commit(string $path, string $content): void
    {
        if (!is_dir($this->directory . '/.git')) {
            $this->git('init', '--quiet');
        }

        file_put_contents($this->directory . '/' . $path, $content);
        $this->git('add', $path);
        $this->git('-c', 'user.name=Test', '-c', 'user.email=test@example.com', 'commit', '--quiet', '--no-gpg-sign', '-m', 'Commit ' . $path);
    }

    private function git(string ...$arguments): void
    {
        (new Process(['git', '-C', $this->directory, ...$arguments]))->mustRun();
    }
}
