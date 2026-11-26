<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Remover;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Component\RemoverInterface;
use OpenColibri\Starter\Core\PhpFile;
use OpenColibri\Starter\Core\Util\Assets;
use OpenColibri\Starter\Core\Util\Composer;
use OpenColibri\Starter\Core\Util\Database;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Javascript;
use OpenColibri\Starter\Core\Util\Symfony;
use OpenColibri\Starter\Plugins\Installer\MollieInstaller;

use function Castor\io;

final readonly class MollieRemover implements RemoverInterface
{
    public function name(): string
    {
        return 'mollie';
    }

    public function description(): string
    {
        return 'Mollie payment gateway (cards, iDEAL, Bancontact, Klarna...)';
    }

    public function __invoke(App $app): void
    {
        io()->title('Removing Mollie plugin');

        Composer::allowContribRecipes($app);
        // The mapped schema before the plugin, to generate the migration of the tables it changes.
        $schema = Database::mappedSchema($app);
        // Its migrations stay executed once its tables are dropped: forget them, so a reinstall runs them again.
        $migrations = Database::pluginMigrations($app, 'Sylius\MolliePlugin\Migrations');

        // Only the constructor the installer added: it initializes the Mollie gateway config.
        $gatewayConfig = $app->directory() . '/' . MollieInstaller::GATEWAY_CONFIG;

        if (str_contains((string) file_get_contents($gatewayConfig), 'initializeMollieGatewayConfig')) {
            (new PhpFile($gatewayConfig))->removeConstructor()->save();
        }

        foreach (MollieInstaller::ENTITIES as $entity => ['interface' => $interface, 'traits' => $traits]) {
            $file = new PhpFile($app->directory() . '/' . $entity);

            foreach ($traits as $trait) {
                $file->removeTrait($trait);
            }

            $file->removeInterface($interface)->save();
        }

        // Sylius-Standard 2.3+ imports the plugin assets in its entrypoints, which Encore would no longer find.
        Javascript::removeVendorImports($app, 'sylius/mollie-plugin');

        // The cache still maps the plugin Doctrine types: the Composer scripts would fail to boot it, clear it instead.
        Docker::run($app, 'composer remove sylius/mollie-plugin --no-scripts');
        Symfony::cacheClear($app);
        Docker::run($app, 'bin/console assets:install public');
        Assets::build($app);
        // Drop its tables in a new migration rather than rolling back the plugin ones: the other environments where the
        // plugin was deployed (a pre-production) run it too.
        Database::migrate($app);
        Database::generatePluginMigration($app, 'Remove the Mollie plugin', $schema, $migrations);
        Database::migrate($app);
    }
}
