<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Installer;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\PhpFile;
use OpenColibri\Starter\Core\Util\Composer;
use OpenColibri\Starter\Core\Util\Database;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Symfony;
use OpenColibri\Starter\Plugins\PluginResourceCopier;

use function Castor\io;

final readonly class TwoFactorAuthInstaller implements PluginInstallerInterface
{
    public const string INTERFACE = 'BitExpert\\SyliusTwoFactorAuthPlugin\\Entity\\TwoFactorAuthInterface';

    public const string TRAIT = 'BitExpert\\SyliusTwoFactorAuthPlugin\\Entity\\TwoFactorAuthTrait';

    /** The user entities sylius-standard ships, which get the second factor. */
    public const array ENTITIES = ['src/Entity/User/AdminUser.php', 'src/Entity/User/ShopUser.php'];

    public function name(): string
    {
        return '2fa';
    }

    public function description(): string
    {
        return 'Two-factor authentication (authenticator app or email) for admin and shop users';
    }

    public function package(): string
    {
        return 'bitexpert/sylius-2fa-plugin';
    }

    public function __invoke(App $app): void
    {
        io()->title('Adding 2FA plugin');

        // The scheb/2fa-bundle and endroid/qr-code-bundle recipes register their bundles; the plugin has none.
        Composer::allowContribRecipes($app);
        // endroid/qr-code-bundle pulls the endroid/installer Composer plugin, whose trust prompt would block the
        // install: Flex already registers its bundle, so the plugin stays disabled.
        Docker::run($app, 'composer config allow-plugins.endroid/installer false');
        // Without its config (copied below), the plugin bundle does not compile: clear the cache once it is there.
        Docker::run($app, 'composer require bitexpert/sylius-2fa-plugin --no-scripts');
        Symfony::addBundle($app, 'Scheb\\TwoFactorBundle\\SchebTwoFactorBundle');
        Symfony::addBundle($app, 'Endroid\\QrCodeBundle\\EndroidQrCodeBundle');
        Symfony::addBundle($app, 'BitExpert\\SyliusTwoFactorAuthPlugin\\BitExpertSyliusTwoFactorAuthPlugin');

        // The plugin config, the admin and shop firewalls second factor, its routes and the migration of its columns.
        PluginResourceCopier::copy($app, '2fa');

        foreach (self::ENTITIES as $entity) {
            (new PhpFile($app->directory() . '/' . $entity))
                ->addInterface(self::INTERFACE)
                ->addTrait(self::TRAIT)
                ->save()
            ;
        }

        Symfony::cacheClear($app);
        Docker::run($app, 'bin/console assets:install public');
        Database::migrate($app);
    }
}
