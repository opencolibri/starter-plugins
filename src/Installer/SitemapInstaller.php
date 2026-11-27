<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Installer;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Util\Composer;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Symfony;

use function Castor\io;

final readonly class SitemapInstaller implements GroupedPluginInstallerInterface
{
    public const string PACKAGE = 'stefandoorn/sitemap-plugin';

    public function name(): string
    {
        return 'sitemap';
    }

    public function description(): string
    {
        return 'XML sitemap of the products, taxons and static pages';
    }

    public function package(): string
    {
        return self::PACKAGE;
    }

    public function group(): string
    {
        return self::CATALOG;
    }

    public function __invoke(App $app): void
    {
        io()->title('Adding Sitemap plugin');

        // Its contrib recipe registers the bundle and imports its config and routes.
        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer require ' . self::PACKAGE);
        Symfony::cacheClear($app);
        // The sitemap is served from generated files: generate them once (then on a schedule, see the docs).
        Docker::run($app, 'bin/console sylius:sitemap:generate');
    }
}
