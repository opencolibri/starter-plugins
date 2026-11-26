<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Remover;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Component\RemoverInterface;
use OpenColibri\Starter\Core\Util\Assets;
use OpenColibri\Starter\Core\Util\Composer;
use OpenColibri\Starter\Core\Util\Database;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Symfony;

use function Castor\io;

final readonly class CmsRemover implements RemoverInterface
{
    public function name(): string
    {
        return 'cms';
    }

    public function description(): string
    {
        return 'CMS plugin for Sylius applications';
    }

    public function __invoke(App $app): void
    {
        io()->title('Removing CMS plugin');

        // The mapped schema before the plugin, to generate the migration of the tables it changes.
        $schema = Database::mappedSchema($app);
        // Its migrations stay executed once its tables are dropped: forget them, so a reinstall runs them again.
        $migrations = Database::pluginMigrations($app, 'Sylius\CmsPlugin\Migrations');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer remove sylius/cms-plugin');
        Assets::build($app);
        // Drop its tables in a new migration rather than rolling back the plugin ones: the other environments where the
        // plugin was deployed (a pre-production) run it too.
        Symfony::cacheClear($app);
        Database::migrate($app);
        Database::generatePluginMigration($app, 'Remove the CMS plugin', $schema, $migrations);
        Database::migrate($app);
    }
}
