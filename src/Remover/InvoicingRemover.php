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

final readonly class InvoicingRemover implements RemoverInterface
{
    public function name(): string
    {
        return 'invoicing';
    }

    public function description(): string
    {
        return 'Invoicing plugin for Sylius';
    }

    public function __invoke(App $app): void
    {
        io()->title('Removing Invoicing plugin');

        // The mapped schema before the plugin, to generate the migration of the tables it changes.
        $schema = Database::mappedSchema($app);
        // Its migrations stay executed once its tables are dropped: forget them, so a reinstall runs them again.
        $migrations = Database::pluginMigrations($app, 'Sylius\InvoicingPlugin\Migrations');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer remove sylius/invoicing-plugin');
        // Drop its tables in a new migration rather than rolling back the plugin ones: the other environments where the
        // plugin was deployed (a pre-production) run it too.
        Symfony::cacheClear($app);
        Database::migrate($app);
        Database::generatePluginMigration($app, 'Remove the invoicing plugin', $schema, $migrations);
        Database::migrate($app);
    }
}
