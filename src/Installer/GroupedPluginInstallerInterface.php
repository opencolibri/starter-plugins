<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Installer;

/**
 * A plugin installer that belongs to a family of plugins, such as the payment gateways: the Sylius service installer
 * asks about each group apart.
 */
interface GroupedPluginInstallerInterface extends PluginInstallerInterface
{
    public const string PAYMENT = 'payment';

    /**
     * The group of the plugin (see the constants), or null when it belongs to none.
     */
    public function group(): ?string;
}
