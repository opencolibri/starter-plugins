<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Remover;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Component\RemoverInterface;
use OpenColibri\Starter\Core\Util\Assets;
use OpenColibri\Starter\Core\Util\Composer;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Symfony;

use function Castor\io;

final readonly class StripeRemover implements RemoverInterface
{
    public function name(): string
    {
        return 'stripe';
    }

    public function description(): string
    {
        return 'Stripe payment gateway (Checkout, Payment Element, express checkout)';
    }

    public function __invoke(App $app): void
    {
        io()->title('Removing Stripe plugin');

        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer remove flux-se/sylius-stripe-plugin');
        Docker::run($app, 'yarn install');
        Assets::build($app);
        Symfony::cacheClear($app);
    }
}
