<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Remover;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Component\RemoverInterface;
use OpenColibri\Starter\Core\Util\Composer;
use OpenColibri\Starter\Core\Util\Database;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Symfony;

use function Castor\io;

final readonly class PaypalRemover implements RemoverInterface
{
    public function name(): string
    {
        return 'paypal';
    }

    public function description(): string
    {
        return 'PayPal payment gateway (PayPal Commerce Platform)';
    }

    public function __invoke(App $app): void
    {
        io()->title('Removing PayPal plugin');

        Composer::allowContribRecipes($app);
        Database::rollbackPluginMigrations($app, 'Sylius\PayPalPlugin\Migrations');
        Docker::run($app, 'composer remove sylius/paypal-plugin');
        Symfony::cacheClear($app);
    }
}
