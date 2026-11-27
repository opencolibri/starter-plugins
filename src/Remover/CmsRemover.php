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
use OpenColibri\Starter\Plugins\PluginRemoval;

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

        // Rolls back a plugin that never left this application, or prepares the migration dropping its tables.
        $removal = PluginRemoval::start($app, 'CMS', 'sylius/cms-plugin', 'Sylius\CmsPlugin\Migrations');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer remove sylius/cms-plugin');
        Assets::build($app);
        Symfony::cacheClear($app);
        $removal->finish($app);
    }
}
