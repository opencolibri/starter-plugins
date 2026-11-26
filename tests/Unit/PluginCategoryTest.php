<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Tests\Unit;

use OpenColibri\Starter\Plugins\Installer\CallablePluginInstaller;
use OpenColibri\Starter\Plugins\Installer\GroupedPluginInstallerInterface;
use OpenColibri\Starter\Plugins\Installer\PluginInstallerInterface;
use OpenColibri\Starter\Plugins\PluginCategory;
use PHPUnit\Framework\TestCase;

final class PluginCategoryTest extends TestCase
{
    public function testEveryPluginOfThePackageHasAKnownCategory(): void
    {
        $installers = [];

        foreach (glob(\dirname(__DIR__, 2) . '/src/Installer/*Installer.php') ?: [] as $file) {
            $class = 'OpenColibri\\Starter\\Plugins\\Installer\\' . basename($file, '.php');
            $reflection = new \ReflectionClass($class);

            if ($reflection->isInstantiable() && $reflection->implementsInterface(PluginInstallerInterface::class) && CallablePluginInstaller::class !== $class) {
                $installers[] = $reflection->newInstance();
            }
        }

        static::assertGreaterThan(10, \count($installers));

        foreach ($installers as $installer) {
            static::assertInstanceOf(GroupedPluginInstallerInterface::class, $installer, \sprintf('The "%s" plugin has no category.', $installer->name()));
            static::assertNotNull(PluginCategory::tryFrom((string) $installer->group()), \sprintf('The "%s" plugin has an unknown category: add it to %s and to docs/commands/plugins.md.', $installer->name(), PluginCategory::class));
        }
    }

    public function testItDescribesTheCategoryOfAPlugin(): void
    {
        $describe = static fn(?string $group): ?array => PluginCategory::describe(new CallablePluginInstaller('acme', static fn(): null => null, group: $group));

        static::assertSame(['name' => 'security', 'label' => 'Security and privacy', 'description' => 'Protect the accounts and the forms, and handle personal data.'], $describe('security'));
        static::assertSame(['name' => 'marketing_tools', 'label' => 'Marketing tools', 'description' => null], $describe('marketing_tools'));
        static::assertNull($describe(null));
        static::assertSame(['payment', 'developer', 'marketing_tools'], PluginCategory::sort(['marketing_tools', 'developer', 'payment']));
    }
}
