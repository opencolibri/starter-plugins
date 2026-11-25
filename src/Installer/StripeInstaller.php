<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Installer;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Util\Assets;
use OpenColibri\Starter\Core\Util\Composer;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Symfony;

use function Castor\io;

final readonly class StripeInstaller implements GroupedPluginInstallerInterface
{
    public function name(): string
    {
        return 'stripe';
    }

    public function description(): string
    {
        return 'Stripe payment gateway (Checkout, Payment Element, express checkout)';
    }

    public function package(): string
    {
        return 'flux-se/sylius-stripe-plugin';
    }

    public function group(): string
    {
        return self::PAYMENT;
    }

    public function __invoke(App $app): void
    {
        io()->title('Adding Stripe plugin');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer require flux-se/sylius-stripe-plugin');
        // The plugin ships Stimulus controllers for its Payment Element.
        Docker::run($app, 'yarn install');
        Assets::build($app);
        Symfony::cacheClear($app);
    }
}
