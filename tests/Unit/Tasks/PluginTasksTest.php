<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Tests\Unit\Tasks;

use Castor\Container;
use Castor\Runner\Parallel\JobRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use OpenColibri\Starter\Core\Component\CallableInstaller;
use OpenColibri\Starter\Core\Component\CallableRemover;
use OpenColibri\Starter\Plugins\Installer\CallablePluginInstaller;
use OpenColibri\Starter\Plugins\Installer\CmsInstaller;
use OpenColibri\Starter\Plugins\Installer\GroupedPluginInstallerInterface;
use OpenColibri\Starter\Plugins\Installer\MollieInstaller;
use OpenColibri\Starter\Plugins\Installer\PaypalInstaller;
use OpenColibri\Starter\Plugins\Installer\StripeInstaller;
use OpenColibri\Starter\Plugins\Installer\ProductBundleInstaller;
use OpenColibri\Starter\Plugins\Installer\WishlistInstaller;
use OpenColibri\Starter\Plugins\Remover\ApiRemover;
use OpenColibri\Starter\Plugins\Tasks\PluginTasks;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

#[CoversClass(PluginTasks::class)]
final class PluginTasksTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/plugin_tasks_test_' . uniqid();
        $this->resetComponents();
        $this->setUpContainer();
    }

    /**
     * PluginTasks keeps its installers and removers in private statics, so they
     * have to be cleared between tests to keep them independent.
     */
    private function resetComponents(): void
    {
        (new \ReflectionProperty(PluginTasks::class, 'installers'))->setValue(null, []);
        (new \ReflectionProperty(PluginTasks::class, 'removers'))->setValue(null, []);
    }

    private function setUpContainer(?SymfonyStyle $symfonyStyle = null): void
    {
        $container = (new \ReflectionClass(Container::class))->newInstanceWithoutConstructor();

        $setProperty = static function (Container $container, string $property, mixed $value): void {
            $prop = new \ReflectionProperty(Container::class, $property);
            $prop->setValue($container, $value);
        };

        $setProperty($container, 'symfonyStyle', $symfonyStyle ?? new SymfonyStyle(new ArrayInput([]), new BufferedOutput()));

        // Castor 1.9+ resolves io() and output() through the job registry of parallel().
        if (property_exists(Container::class, 'jobRegistry')) {
            (new \ReflectionProperty(Container::class, 'jobRegistry'))->setValue($container, new JobRegistry(new ArrayInput([])));
        }

        Container::set($container);
    }

    public function testItLabelsChoicesWithTheDescription(): void
    {
        static::assertSame([
            'cms' => 'cms - CMS plugin for Sylius applications',
        ], PluginTasks::choices(['cms' => new CmsInstaller()]));
    }

    public function testItFallsBackToTheBareNameWithoutDescription(): void
    {
        static::assertSame([
            'empty_description' => 'empty_description',
            'without_description' => 'without_description',
        ], PluginTasks::choices([
            'without_description' => new CallableInstaller('without_description', static fn(): null => null),
            'empty_description' => new CallableInstaller('empty_description', static fn(): null => null, ''),
        ]));
    }

    public function testItSortsChoicesByName(): void
    {
        $choices = PluginTasks::choices([
            'wishlist' => new CallableInstaller('wishlist', static fn(): null => null, 'Wishlist plugin for Sylius'),
            'cms' => new CmsInstaller(),
            'api' => new CallableRemover('api', static fn(): null => null),
        ]);

        static::assertSame(['api', 'cms', 'wishlist'], array_keys($choices));
    }

    public function testItExposesADescriptionForEveryBuiltinPlugin(): void
    {
        PluginTasks::addInstaller(new CmsInstaller());
        PluginTasks::addInstaller(new ProductBundleInstaller());
        PluginTasks::addRemover(new ApiRemover());

        static::assertSame([
            'cms' => 'cms - CMS plugin for Sylius applications',
            'product_bundle' => 'product_bundle - Product bundle for Sylius',
        ], PluginTasks::choices((new \ReflectionProperty(PluginTasks::class, 'installers'))->getValue()));

        static::assertSame([
            'api' => 'api - Sylius API and its test tooling',
        ], PluginTasks::choices((new \ReflectionProperty(PluginTasks::class, 'removers'))->getValue()));
    }

    public function testItListsTheComposerPackageOfEachPluginThatRequiresOne(): void
    {
        PluginTasks::addInstaller(new WishlistInstaller());
        PluginTasks::addInstaller(new CallablePluginInstaller('acme_loyalty', static fn(): null => null, 'Acme loyalty', 'acme/sylius-loyalty-plugin'));
        PluginTasks::addInstaller(new CmsInstaller());

        static::assertSame([
            'acme_loyalty' => 'acme/sylius-loyalty-plugin',
            'cms' => 'sylius/cms-plugin',
            'wishlist' => 'sylius/wishlist-plugin',
        ], PluginTasks::packages());
    }

    public function testItAsksForAChoiceWithDescriptionsWhenNoPluginIsGiven(): void
    {
        PluginTasks::addInstaller(new CmsInstaller());

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())
            ->method('choice')
            ->with(
                'Which plugins would you like to install?',
                ['cms' => 'cms - CMS plugin for Sylius applications'],
                null,
                true,
            )
            ->willThrowException(new MissingInputException());

        $this->setUpContainer($io);

        $tasks = iterator_to_array((new PluginTasks('test-app', $this->tempDir))());

        $this->expectException(MissingInputException::class);

        $tasks[0]['function']([]);
    }

    public function testItAsksForAChoiceWithDescriptionsWhenNoPluginIsGivenToRemove(): void
    {
        PluginTasks::addRemover(new ApiRemover());

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())
            ->method('choice')
            ->with(
                'Which plugins would you like to remove?',
                ['api' => 'api - Sylius API and its test tooling'],
                null,
                true,
            )
            ->willThrowException(new MissingInputException());

        $this->setUpContainer($io);

        $tasks = iterator_to_array((new PluginTasks('test-app', $this->tempDir))());

        $this->expectException(MissingInputException::class);

        $tasks[1]['function']([]);
    }

    public function testItListsThePluginsOfAGroup(): void
    {
        PluginTasks::addInstaller(new StripeInstaller());
        PluginTasks::addInstaller(new CmsInstaller());
        PluginTasks::addInstaller(new PaypalInstaller());
        PluginTasks::addInstaller(new MollieInstaller());
        PluginTasks::addInstaller(new CallablePluginInstaller('acme_pay', static fn(): null => null, 'Acme Pay', 'acme/sylius-pay-plugin', GroupedPluginInstallerInterface::PAYMENT));

        static::assertSame(['acme_pay', 'mollie', 'paypal', 'stripe'], PluginTasks::group(GroupedPluginInstallerInterface::PAYMENT));
        static::assertSame([], PluginTasks::group('unknown'));
    }

    public function testItTellsTheInstalledPluginsFromComposerJson(): void
    {
        PluginTasks::addInstaller(new StripeInstaller());
        PluginTasks::addInstaller(new MollieInstaller());
        PluginTasks::addInstaller(new CmsInstaller());
        $this->writeComposerJson(['flux-se/sylius-stripe-plugin', 'sylius/cms-plugin']);

        static::assertSame(['cms', 'stripe'], PluginTasks::installed($this->tempDir));
        static::assertSame([], PluginTasks::installed($this->tempDir . '/missing'));
    }

    public function testItPreChecksTheInstalledPluginsAndRemovesOnlyTheUncheckedOnesOnceConfirmed(): void
    {
        $calls = [];
        $this->registerPlugin('stripe', 'flux-se/sylius-stripe-plugin', $calls);
        $this->registerPlugin('mollie', 'sylius/mollie-plugin', $calls);
        $this->registerPlugin('paypal', 'sylius/paypal-plugin', $calls);
        $this->writeComposerJson(['flux-se/sylius-stripe-plugin', 'sylius/mollie-plugin']);

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())
            ->method('choice')
            ->with(
                'Which plugins would you like to have? The installed ones are checked, uncheck one to remove it.',
                static::anything(),
                'mollie,stripe',
                true,
            )
            // Keeps stripe, unchecks mollie, checks paypal.
            ->willReturn(['paypal', 'stripe'])
        ;
        $io->expects($this->once())
            ->method('confirm')
            ->with('Remove the unchecked plugins (mollie)? Their data is dropped.', false)
            ->willReturn(true)
        ;
        $this->setUpContainer($io);

        $tasks = iterator_to_array((new PluginTasks('test-app', $this->tempDir))());
        $tasks[0]['function']([]);

        static::assertSame(['remove mollie', 'install paypal'], $calls);
    }

    public function testItKeepsTheUncheckedPluginsWhenTheRemovalIsDeclined(): void
    {
        $calls = [];
        $this->registerPlugin('stripe', 'flux-se/sylius-stripe-plugin', $calls);
        $this->registerPlugin('paypal', 'sylius/paypal-plugin', $calls);
        $this->writeComposerJson(['flux-se/sylius-stripe-plugin']);

        $io = static::createStub(SymfonyStyle::class);
        $io->method('choice')->willReturn(['paypal']);
        $io->method('confirm')->willReturn(false);
        $this->setUpContainer($io);

        $tasks = iterator_to_array((new PluginTasks('test-app', $this->tempDir))());
        $tasks[0]['function']([]);

        static::assertSame(['install paypal'], $calls);
    }

    public function testItNeverRemovesAPluginGivenOnTheCommandLine(): void
    {
        $calls = [];
        $this->registerPlugin('stripe', 'flux-se/sylius-stripe-plugin', $calls);
        $this->registerPlugin('paypal', 'sylius/paypal-plugin', $calls);
        $this->writeComposerJson(['flux-se/sylius-stripe-plugin']);

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('choice');
        $io->expects($this->never())->method('confirm');
        $this->setUpContainer($io);

        $tasks = iterator_to_array((new PluginTasks('test-app', $this->tempDir))());
        $tasks[0]['function'](['paypal']);

        static::assertSame(['install paypal'], $calls);
    }

    /**
     * @param list<string> $calls
     */
    private function registerPlugin(string $name, string $package, array &$calls): void
    {
        PluginTasks::addInstaller(new CallablePluginInstaller($name, static function () use ($name, &$calls): void {
            $calls[] = 'install ' . $name;
        }, null, $package, GroupedPluginInstallerInterface::PAYMENT));
        PluginTasks::addRemover(new CallableRemover($name, static function () use ($name, &$calls): void {
            $calls[] = 'remove ' . $name;
        }));
    }

    /**
     * @param list<string> $packages
     */
    private function writeComposerJson(array $packages): void
    {
        @mkdir($this->tempDir, 0o777, true);
        file_put_contents($this->tempDir . '/composer.json', json_encode(['require' => array_fill_keys($packages, '*')], \JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        @unlink($this->tempDir . '/composer.json');
        @rmdir($this->tempDir);
    }
}
