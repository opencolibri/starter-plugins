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

        // The mapped schema before the plugin, to generate the migration of the tables it changes.
        $schema = Database::mappedSchema($app);
        // Its migrations stay executed once its tables are dropped: forget them, so a reinstall runs them again.
        $migrations = Database::pluginMigrations($app, 'Sylius\PayPalPlugin\Migrations');

        Composer::allowContribRecipes($app);
        // Sylius-Standard 2.3+ imports the plugin assets in its entrypoints, which Encore would no longer find.
        Javascript::removeVendorImports($app, 'sylius/paypal-plugin');
        Docker::run($app, 'composer remove sylius/paypal-plugin');
        // Flex drops the plugin Stimulus controllers from package.json: the node modules and the build follow.
        Docker::run($app, 'yarn install');
        Assets::build($app);
        // Drop its tables in a new migration rather than rolling back the plugin ones: the other environments where the
        // plugin was deployed (a pre-production) run it too.
        Symfony::cacheClear($app);
        Database::migrate($app);
        Database::generatePluginMigration($app, 'Remove the PayPal plugin', $schema, $migrations);
        Database::migrate($app);
    }
}
