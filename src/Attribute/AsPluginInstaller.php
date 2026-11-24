<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_FUNCTION)]
class AsPluginInstaller
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        /** The Composer package the plugin requires, to tell from composer.json whether it is installed (dev-kit). */
        public ?string $package = null,
    ) {}
}
