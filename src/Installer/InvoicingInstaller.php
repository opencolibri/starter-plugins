<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Installer;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Util\Composer;
use OpenColibri\Starter\Core\Util\Database;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Symfony;

use function Castor\fs;
use function Castor\io;

final readonly class InvoicingInstaller implements GroupedPluginInstallerInterface
{
    public function name(): string
    {
        return 'invoicing';
    }

    public function description(): string
    {
        return 'Invoicing plugin for Sylius, by Sylius';
    }

    public function package(): string
    {
        return 'sylius/invoicing-plugin';
    }

    public function group(): string
    {
        return self::ORDERS;
    }

    public function __invoke(App $app): void
    {
        io()->title('Adding Invoicing Plugin');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer require sylius/invoicing-plugin');
        Database::migrate($app);
        Symfony::cacheClear($app);
    }
}
