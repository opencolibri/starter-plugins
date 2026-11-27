<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Remover;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Component\RemoverInterface;
use OpenColibri\Starter\Core\PhpFile;
use OpenColibri\Starter\Core\Util\Composer;
use OpenColibri\Starter\Core\Util\Database;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Symfony;
use OpenColibri\Starter\Plugins\Installer\SeoInstaller;
use OpenColibri\Starter\Plugins\PluginRemoval;

use function Castor\finder;
use function Castor\fs;
use function Castor\io;

final readonly class SeoRemover implements RemoverInterface
{
    public function name(): string
    {
        return 'seo';
    }

    public function description(): string
    {
        return 'Remove the Dedi SEO plugin and its SEO contents';
    }

    public function __invoke(App $app): void
    {
        io()->title('Removing SEO plugin');

        // Rolls back a plugin that never left this application, or prepares the migration dropping its tables.
        $removal = PluginRemoval::start($app, 'SEO', 'dedi/sylius-seo-plugin');

        Composer::allowContribRecipes($app);

        foreach (SeoInstaller::ENTITIES as $entity => $wiring) {
            preg_match_all('/function\s+(\w+)\s*\(/', $wiring['methods'], $methods);

            $file = (new PhpFile($app->directory() . '/' . $entity))->removeMethod(...$methods[1]);

            foreach ($wiring['traits'] as $trait) {
                $file->removeTrait($trait);
            }

            foreach ($wiring['interfaces'] as $interface) {
                $file->removeInterface($interface);
            }

            foreach ($wiring['imports'] as $import) {
                $file->removeImport($import);
            }

            $file->save();
        }

        $resourcesDir = \dirname(__DIR__, 2) . '/resources/seo';

        foreach (finder()->files()->in($resourcesDir) as $file) {
            fs()->remove($app->directory() . '/' . $file->getRelativePathname());
        }

        $bundles = $app->directory() . '/config/bundles.php';
        $content = fs()->readFile($bundles);
        $replaced = preg_replace(\sprintf('/^\s*%s::class\s*=>.*\R/m', preg_quote(SeoInstaller::BUNDLE, '/')), '', $content);

        if (null !== $replaced && $replaced !== $content) {
            fs()->dumpFile($bundles, $replaced);
        }

        Docker::run($app, 'composer remove dedi/sylius-seo-plugin');

        Symfony::cacheClear($app);
        $removal->finish($app);
    }
}
