<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Remover;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Component\RemoverInterface;
use OpenColibri\Starter\Core\PhpFile;
use OpenColibri\Starter\Core\Util\Composer;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Symfony;
use OpenColibri\Starter\Plugins\Installer\EuVatInstaller;
use OpenColibri\Starter\Plugins\PluginRemoval;

use function Castor\finder;
use function Castor\fs;
use function Castor\io;

final readonly class EuVatRemover implements RemoverInterface
{
    private const string LABEL = 'EU VAT';

    public function name(): string
    {
        return 'eu_vat';
    }

    public function description(): string
    {
        return 'Remove the EU VAT plugin and the VAT numbers, by FLUX-SE';
    }

    public function __invoke(App $app): void
    {
        io()->title('Removing EU VAT plugin');

        // Rolls back a plugin that never left this application, or prepares the migration dropping its columns. The
        // down() of the migration the plugin ships is written for MySQL only: roll it back with a generated one.
        $removal = PluginRemoval::start($app, self::LABEL, EuVatInstaller::PACKAGE, EuVatInstaller::MIGRATIONS, rollbackWithDiff: true);

        Composer::allowContribRecipes($app);

        foreach (EuVatInstaller::ENTITIES as $entity => ['interface' => $interface, 'trait' => $trait]) {
            (new PhpFile($app->directory() . '/' . $entity))
                ->removeTrait($trait)
                ->removeInterface($interface)
                ->save()
            ;
        }

        foreach (finder()->files()->in(\dirname(__DIR__, 2) . '/resources/eu_vat') as $file) {
            fs()->remove($app->directory() . '/' . $file->getRelativePathname());
        }

        self::removeBundle($app, EuVatInstaller::BUNDLE);
        self::removeBundle($app, 'Prometee\\VIESClientBundle\\VIESClientBundle');

        // Composer drops the VIES client with it. The EU zone and the countries the install created stay: the shop
        // may use them since.
        Docker::run($app, 'composer remove ' . EuVatInstaller::PACKAGE);
        Symfony::cacheClear($app);
        $removal->finish($app);
    }

    private static function removeBundle(App $app, string $bundle): void
    {
        $file = $app->directory() . '/config/bundles.php';
        $content = fs()->readFile($file);
        $replaced = preg_replace(\sprintf('/^\s*%s::class\s*=>.*\R/m', preg_quote($bundle, '/')), '', $content);

        if (null !== $replaced && $replaced !== $content) {
            fs()->dumpFile($file, $replaced);
        }
    }
}
