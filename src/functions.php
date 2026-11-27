<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins;

use Castor\Attribute\AsListener;
use Castor\Event\AfterBootEvent;
use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Component\CallableRemover;
use OpenColibri\Starter\Core\Component\ComponentResolver;
use OpenColibri\Starter\Core\Installer\SyliusInstallerExtensions;
use OpenColibri\Starter\Core\Service\SyliusService;
use OpenColibri\Starter\Core\Task\TaskProviderRegistry;
use OpenColibri\Starter\Plugins\Attribute\AsPluginInstaller;
use OpenColibri\Starter\Plugins\Attribute\AsPluginRemover;
use OpenColibri\Starter\Plugins\Installer\AiDevToolsInstaller;
use OpenColibri\Starter\Plugins\Installer\AltchaInstaller;
use OpenColibri\Starter\Plugins\Installer\BugSnagInstaller;
use OpenColibri\Starter\Plugins\Installer\CmsInstaller;
use OpenColibri\Starter\Plugins\Installer\EuVatInstaller;
use OpenColibri\Starter\Plugins\Installer\GdprInstaller;
use OpenColibri\Starter\Plugins\Installer\InvoicingInstaller;
use OpenColibri\Starter\Plugins\Installer\MediaInstaller;
use OpenColibri\Starter\Plugins\Installer\MollieInstaller;
use OpenColibri\Starter\Plugins\Installer\PaymentGatewaysSyliusInstallerExtension;
use OpenColibri\Starter\Plugins\Installer\PaypalInstaller;
use OpenColibri\Starter\Plugins\Installer\CallablePluginInstaller;
use OpenColibri\Starter\Plugins\Installer\PluginInstallerDescriptor;
use OpenColibri\Starter\Plugins\Installer\ProductBundleInstaller;
use OpenColibri\Starter\Plugins\Installer\RecaptchaInstaller;
use OpenColibri\Starter\Plugins\Installer\RefundInstaller;
use OpenColibri\Starter\Plugins\Installer\SeoInstaller;
use OpenColibri\Starter\Plugins\Installer\SitemapInstaller;
use OpenColibri\Starter\Plugins\Installer\StripeInstaller;
use OpenColibri\Starter\Plugins\Installer\TwoFactorAuthInstaller;
use OpenColibri\Starter\Plugins\Installer\WishlistInstaller;
use OpenColibri\Starter\Plugins\Remover\AiDevToolsRemover;
use OpenColibri\Starter\Plugins\Remover\AltchaRemover;
use OpenColibri\Starter\Plugins\Remover\ApiRemover;
use OpenColibri\Starter\Plugins\Remover\BugSnagRemover;
use OpenColibri\Starter\Plugins\Remover\CmsRemover;
use OpenColibri\Starter\Plugins\Remover\EuVatRemover;
use OpenColibri\Starter\Plugins\Remover\GdprRemover;
use OpenColibri\Starter\Plugins\Remover\InvoicingRemover;
use OpenColibri\Starter\Plugins\Remover\MollieRemover;
use OpenColibri\Starter\Plugins\Remover\PaypalRemover;
use OpenColibri\Starter\Plugins\Remover\PluginRemoverDescriptor;
use OpenColibri\Starter\Plugins\Remover\RecaptchaRemover;
use OpenColibri\Starter\Plugins\Remover\SeoRemover;
use OpenColibri\Starter\Plugins\Remover\SitemapRemover;
use OpenColibri\Starter\Plugins\Remover\StripeRemover;
use OpenColibri\Starter\Plugins\Remover\TwoFactorAuthRemover;
use OpenColibri\Starter\Plugins\Remover\WishlistRemover;
use OpenColibri\Starter\Plugins\Tasks\PluginTasks;

TaskProviderRegistry::register(
    'plugins',
    static fn(SyliusService $service): iterable => (new PluginTasks($service->getName(), $service->getDirectory()))(),
);

SyliusInstallerExtensions::register('payment_gateways', new PaymentGatewaysSyliusInstallerExtension());

#[AsListener(AfterBootEvent::class)]
function initialize(AfterBootEvent $afterBootEvent): void
{
    PluginTasks::addInstaller(new AiDevToolsInstaller());
    PluginTasks::addInstaller(new AltchaInstaller());
    PluginTasks::addInstaller(new BugSnagInstaller());
    PluginTasks::addInstaller(new CmsInstaller());
    PluginTasks::addInstaller(new EuVatInstaller());
    PluginTasks::addInstaller(new GdprInstaller());
    PluginTasks::addInstaller(new InvoicingInstaller());
    PluginTasks::addInstaller(new MediaInstaller());
    PluginTasks::addInstaller(new MollieInstaller());
    PluginTasks::addInstaller(new PaypalInstaller());
    PluginTasks::addInstaller(new ProductBundleInstaller());
    PluginTasks::addInstaller(new RecaptchaInstaller());
    PluginTasks::addInstaller(new RefundInstaller());
    PluginTasks::addInstaller(new SeoInstaller());
    PluginTasks::addInstaller(new SitemapInstaller());
    PluginTasks::addInstaller(new StripeInstaller());
    PluginTasks::addInstaller(new TwoFactorAuthInstaller());
    PluginTasks::addInstaller(new WishlistInstaller());

    PluginTasks::addRemover(new AiDevToolsRemover());
    PluginTasks::addRemover(new AltchaRemover());
    PluginTasks::addRemover(new ApiRemover());
    PluginTasks::addRemover(new BugSnagRemover());
    PluginTasks::addRemover(new CmsRemover());
    PluginTasks::addRemover(new EuVatRemover());
    PluginTasks::addRemover(new GdprRemover());
    PluginTasks::addRemover(new InvoicingRemover());
    PluginTasks::addRemover(new MollieRemover());
    PluginTasks::addRemover(new PaypalRemover());
    PluginTasks::addRemover(new RecaptchaRemover());
    PluginTasks::addRemover(new SeoRemover());
    PluginTasks::addRemover(new SitemapRemover());
    PluginTasks::addRemover(new StripeRemover());
    PluginTasks::addRemover(new TwoFactorAuthRemover());
    PluginTasks::addRemover(new WishlistRemover());

    foreach (ComponentResolver::candidates() as $reflection) {
        $descriptor = resolve_plugin_installer($reflection);

        if (null !== $descriptor) {
            PluginTasks::addInstaller($descriptor->installer instanceof \ReflectionFunction
                ? new CallablePluginInstaller($descriptor->attribute->name, $descriptor->installer->getClosure(), $descriptor->attribute->description, $descriptor->attribute->package, $descriptor->attribute->group)
                : $descriptor->installer);
        }

        $descriptor = resolve_plugin_remover($reflection);

        if (null !== $descriptor) {
            PluginTasks::addRemover($descriptor->remover instanceof \ReflectionFunction
                ? new CallableRemover($descriptor->attribute->name, $descriptor->remover->getClosure(), $descriptor->attribute->description)
                : $descriptor->remover);
        }
    }
}

function resolve_plugin_installer(\ReflectionFunction|\ReflectionClass $reflection): ?PluginInstallerDescriptor
{
    $resolved = ComponentResolver::resolve($reflection, AsPluginInstaller::class);

    if (null === $resolved) {
        return null;
    }

    [$attribute, $installer] = $resolved;

    if ($installer instanceof \ReflectionFunction) {
        return new PluginInstallerDescriptor($attribute, $installer);
    }

    return new PluginInstallerDescriptor($attribute, new CallablePluginInstaller($attribute->name, static fn(App $app) => $installer($app), $attribute->description, $attribute->package, $attribute->group));
}

function resolve_plugin_remover(\ReflectionFunction|\ReflectionClass $reflection): ?PluginRemoverDescriptor
{
    $resolved = ComponentResolver::resolve($reflection, AsPluginRemover::class);

    if (null === $resolved) {
        return null;
    }

    [$attribute, $remover] = $resolved;

    if ($remover instanceof \ReflectionFunction) {
        return new PluginRemoverDescriptor($attribute, $remover);
    }

    return new PluginRemoverDescriptor($attribute, new CallableRemover($attribute->name, static fn(App $app) => $remover($app), $attribute->description));
}
