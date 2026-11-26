<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Installer;

/**
 * A plugin installer that belongs to a family of plugins, its category (see PluginCategory): plugin pickers group the
 * plugins by it, and the Sylius service installer asks about the payment gateways apart.
 */
interface GroupedPluginInstallerInterface extends PluginInstallerInterface
{
    public const string PAYMENT = 'payment';

    public const string SECURITY = 'security';

    public const string CATALOG = 'catalog';

    public const string ORDERS = 'orders';

    public const string DEVELOPER = 'developer';

    /**
     * The group of the plugin (see the constants), or null when it belongs to none.
     */
    public function group(): ?string;
}
