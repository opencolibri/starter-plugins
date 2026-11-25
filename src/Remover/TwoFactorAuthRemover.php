<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Remover;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Component\RemoverInterface;
use OpenColibri\Starter\Core\PhpFile;
use OpenColibri\Starter\Core\Util\Composer;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Symfony;
use OpenColibri\Starter\Plugins\Installer\TwoFactorAuthInstaller;

use function Castor\finder;
use function Castor\fs;
use function Castor\io;

final readonly class TwoFactorAuthRemover implements RemoverInterface
{
    private const string MIGRATION = 'DoctrineMigrations\\Version20261125120000';

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

        Composer::allowContribRecipes($app);

        // Drop the columns while the migration is still there, then unwire everything before Composer clears the cache.
        $migrations = Docker::run($app, 'bin/console doctrine:migrations:list --no-interaction --no-ansi')->getOutput();

        if (preg_match(\sprintf('/^\|\s*%s\s+\|\s+migrated\s+\|/m', preg_quote(self::MIGRATION, '/')), $migrations)) {
            Docker::run($app, \sprintf('bin/console doctrine:migrations:execute --down --no-interaction %s', escapeshellarg(self::MIGRATION)));
        }

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
