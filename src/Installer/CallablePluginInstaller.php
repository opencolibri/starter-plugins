<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Installer;

use OpenColibri\Starter\Core\App;

/**
 * A plugin installer declared with #[AsPluginInstaller].
 */
final readonly class CallablePluginInstaller implements PluginInstallerInterface
{
    public function __construct(
        public string $name,
        public \Closure $code,
        public ?string $description = null,
        public ?string $package = null,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function package(): ?string
    {
        return $this->package;
    }

    public function __invoke(App $app): void
    {
        ($this->code)($app);
    }
}
