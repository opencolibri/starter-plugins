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
use OpenColibri\Starter\Plugins\Installer\TwoFactorAuthInstaller;
use OpenColibri\Starter\Plugins\PluginRemoval;

use function Castor\finder;
use function Castor\fs;
use function Castor\io;

final readonly class TwoFactorAuthRemover implements RemoverInterface
{
    public function name(): string
    {
        return '2fa';
    }

    public function description(): string
    {
        return 'Remove two-factor authentication from admin and shop users';
    }

    public function __invoke(App $app): void
    {
        io()->title('Removing 2FA plugin');

        // Rolls back a plugin that never left this application, or prepares the migration dropping its tables.
        $removal = PluginRemoval::start($app, '2FA', 'bitexpert/sylius-2fa-plugin');

        Composer::allowContribRecipes($app);

        foreach (TwoFactorAuthInstaller::ENTITIES as $entity) {
            (new PhpFile($app->directory() . '/' . $entity))
                ->removeTrait(TwoFactorAuthInstaller::TRAIT)
                ->removeInterface(TwoFactorAuthInstaller::INTERFACE)
                ->save()
            ;
        }

        $resourcesDir = \dirname(__DIR__, 2) . '/resources/2fa';

        foreach (finder()->files()->in($resourcesDir) as $file) {
            fs()->remove($app->directory() . '/' . $file->getRelativePathname());
        }

        // The installer registers these bundles whether or not a recipe did, so unregister them all.
        self::removeBundle($app, 'BitExpert\\SyliusTwoFactorAuthPlugin\\BitExpertSyliusTwoFactorAuthPlugin');
        self::removeBundle($app, 'Endroid\\QrCodeBundle\\EndroidQrCodeBundle');
        self::removeBundle($app, 'Scheb\\TwoFactorBundle\\SchebTwoFactorBundle');

        // Composer drops scheb/2fa-* and endroid/qr-code-bundle with it, and their recipes remove their config.
        Docker::run($app, 'composer remove bitexpert/sylius-2fa-plugin');
        Docker::run($app, 'composer config --unset allow-plugins.endroid/installer');

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
