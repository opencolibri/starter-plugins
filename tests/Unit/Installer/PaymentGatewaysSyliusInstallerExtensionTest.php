<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Tests\Unit\Installer;

use Castor\Docker\Installer\InputType;
use Castor\Docker\Installer\InstallerOptions;
use OpenColibri\Starter\Plugins\Installer\CmsInstaller;
use OpenColibri\Starter\Plugins\Installer\MollieInstaller;
use OpenColibri\Starter\Plugins\Installer\PaymentGatewaysSyliusInstallerExtension;
use OpenColibri\Starter\Plugins\Installer\PaypalInstaller;
use OpenColibri\Starter\Plugins\Installer\StripeInstaller;
use OpenColibri\Starter\Plugins\Tasks\PluginTasks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentGatewaysSyliusInstallerExtension::class)]
final class PaymentGatewaysSyliusInstallerExtensionTest extends TestCase
{
    private const array GATEWAYS = ['mollie', 'paypal', 'stripe'];

    protected function setUp(): void
    {
        (new \ReflectionProperty(PluginTasks::class, 'installers'))->setValue(null, []);
        (new \ReflectionProperty(PluginTasks::class, 'removers'))->setValue(null, []);
    }

    public function testItAsksForEveryGatewayAllPickedByDefault(): void
    {
        PluginTasks::addInstaller(new StripeInstaller());
        PluginTasks::addInstaller(new CmsInstaller());
        PluginTasks::addInstaller(new MollieInstaller());
        PluginTasks::addInstaller(new PaypalInstaller());

        $inputs = (new PaymentGatewaysSyliusInstallerExtension())->getInputs();

        static::assertCount(1, $inputs);
        static::assertSame('with-payment-gateways', InstallerOptions::optionName($inputs[0]));
        static::assertSame(InputType::Choice, $inputs[0]->type);
        static::assertTrue($inputs[0]->multiple);
        static::assertSame(self::GATEWAYS, $inputs[0]->choices);
        static::assertSame(self::GATEWAYS, $inputs[0]->default);
    }

    public function testItAsksNothingWithoutGateway(): void
    {
        PluginTasks::addInstaller(new CmsInstaller());

        static::assertSame([], (new PaymentGatewaysSyliusInstallerExtension())->getInputs());
    }

    public function testItRemovesTheGatewaysSyliusStandardShipsButWereNotPicked(): void
    {
        static::assertSame(
            [[], ['mollie', 'paypal']],
            PaymentGatewaysSyliusInstallerExtension::changes(['stripe'], self::GATEWAYS, ['mollie', 'paypal', 'refund', 'stripe']),
        );
    }

    public function testItOnlyAddsThePickedGatewaysTheAppLacks(): void
    {
        static::assertSame(
            [['stripe'], ['paypal']],
            PaymentGatewaysSyliusInstallerExtension::changes(['mollie', 'stripe'], self::GATEWAYS, ['mollie', 'paypal']),
        );
    }

    public function testItRemovesEveryGatewayWhenNoneIsPicked(): void
    {
        static::assertSame(
            [[], ['mollie', 'stripe']],
            PaymentGatewaysSyliusInstallerExtension::changes([], self::GATEWAYS, ['cms', 'mollie', 'stripe']),
        );
    }

    public function testItIgnoresUnknownGateways(): void
    {
        static::assertSame(
            [[], []],
            PaymentGatewaysSyliusInstallerExtension::changes(['adyen', 'mollie'], self::GATEWAYS, ['mollie']),
        );
    }
}
