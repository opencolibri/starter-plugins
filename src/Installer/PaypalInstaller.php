<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Installer;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Util\Composer;
use OpenColibri\Starter\Core\Util\Database;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Symfony;

use function Castor\io;

final readonly class PaypalInstaller implements GroupedPluginInstallerInterface
{
    public function name(): string
    {
        return 'paypal';
    }

    public function description(): string
    {
        return 'PayPal payment gateway (PayPal Commerce Platform)';
    }

    public function package(): string
    {
        return 'sylius/paypal-plugin';
    }

    public function group(): string
    {
        return self::PAYMENT;
    }

    public function __invoke(App $app): void
    {
        io()->title('Adding PayPal plugin');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer require sylius/paypal-plugin');
        Docker::run($app, 'bin/console assets:install public');
        Database::migrate($app);
        Symfony::cacheClear($app);
    }
}
