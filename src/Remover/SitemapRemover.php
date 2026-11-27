<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Remover;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Component\RemoverInterface;
use OpenColibri\Starter\Core\Util\Composer;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Symfony;
use OpenColibri\Starter\Plugins\Installer\SitemapInstaller;

use function Castor\fs;
use function Castor\io;

final readonly class SitemapRemover implements RemoverInterface
{
    public function name(): string
    {
        return 'sitemap';
    }

    public function description(): string
    {
        return 'Remove the sitemap plugin and its generated sitemaps, by Stefan Doorn';
    }

    public function __invoke(App $app): void
    {
        io()->title('Removing Sitemap plugin');

        // The plugin adds no table, and its recipe takes back its bundle, config and routes.
        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer remove ' . SitemapInstaller::PACKAGE);
        // The generated sitemaps (sylius.sitemap.path of the plugin config).
        fs()->remove($app->directory() . '/var/sitemap');
        Symfony::cacheClear($app);
    }
}
