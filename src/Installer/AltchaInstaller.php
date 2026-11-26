<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Installer;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Util\Composer;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Symfony;
use OpenColibri\Starter\Core\Util\Yaml;
use OpenColibri\Starter\Plugins\PluginResourceCopier;

use function Castor\fs;
use function Castor\io;

final readonly class AltchaInstaller implements GroupedPluginInstallerInterface
{
    public function name(): string
    {
        return 'altcha';
    }

    public function description(): string
    {
        return 'Add ALTCHA (self-hosted, privacy-friendly captcha) to customer registration';
    }

    public function package(): string
    {
        return 'tito10047/altcha-bundle';
    }

    public function group(): string
    {
        return self::SECURITY;
    }

    public function __invoke(App $app): void
    {
        io()->title('Adding ALTCHA plugin');

        // The contrib recipe registers the bundle, the challenge route and the ALTCHA_SECRET env var.
        Composer::allowContribRecipes($app);
        Docker::run($app, 'composer require tito10047/altcha-bundle');

        // Override the recipe config: we load the widget script directly instead of the bundle Stimulus controller.
        // Since 3.x, Flex enables that controller in assets/controllers.json, and it imports the "altcha" npm package,
        // which nothing installs: every later asset build (a theme setup...) would fail. Keep it disabled.
        Symfony::addJsController($app, '@tito10047/altcha-bundle', 'altcha', ['enabled' => false, 'fetch' => 'eager']);

        fs()->dumpFile(
            $app->directory() . '/config/packages/altcha.yaml',
            <<<'YAML'
                altcha:
                    enable: true
                    hmacSignature: '%env(ALTCHA_SECRET)%'
                    floating: false
                    use_stimulus: false
                    include_script: true

                when@test:
                    altcha:
                        enable: false

                YAML
        );

        Yaml::import($app, 'config/packages/_sylius.yaml', '../sylius/twig_hooks/**/**.php');

        PluginResourceCopier::copy($app, 'altcha');
        Symfony::cacheClear($app);
    }
}
