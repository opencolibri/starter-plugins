<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Installer;

use OpenColibri\Starter\Core\Component\InstallerInterface;

interface PluginInstallerInterface extends InstallerInterface
{
    /**
     * The Composer package the plugin requires, to tell from composer.json whether it is installed (dev-kit), or
     * null when it requires none.
     */
    public function package(): ?string;
}
