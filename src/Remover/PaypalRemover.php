<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Remover;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Component\RemoverInterface;
use OpenColibri\Starter\Core\Util\Assets;
use OpenColibri\Starter\Core\Util\Composer;
use OpenColibri\Starter\Core\Util\Database;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Javascript;
use OpenColibri\Starter\Core\Util\Symfony;
use OpenColibri\Starter\Plugins\PluginRemoval;

use function Castor\io;

final readonly class PaypalRemover implements RemoverInterface
{
    public function name(): string
    {
        return 'paypal';
    }

    public function description(): string
    {
        return 'PayPal payment gateway (PayPal Commerce Platform), by Sylius';
    }

    public function __invoke(App $app): void
    {
        io()->title('Removing PayPal plugin');

        // Rolls back a plugin that never left this application, or prepares the migration dropping its tables.
        $removal = PluginRemoval::start($app, 'PayPal', 'sylius/paypal-plugin', 'Sylius\PayPalPlugin\Migrations');

        Composer::allowContribRecipes($app);
        // Sylius-Standard 2.3+ imports the plugin assets in its entrypoints, which Encore would no longer find.
        Javascript::removeVendorImports($app, 'sylius/paypal-plugin');
        Docker::run($app, 'composer remove sylius/paypal-plugin');
        // Flex drops the plugin Stimulus controllers from package.json: the node modules and the build follow.
        Docker::run($app, 'yarn install');
        Assets::build($app);
        Symfony::cacheClear($app);
        $removal->finish($app);
    }
}
