<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Tasks;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;
use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Component\ComponentChoices;
use OpenColibri\Starter\Core\Component\InstallerInterface;
use OpenColibri\Starter\Core\Component\RemoverInterface;
use OpenColibri\Starter\Plugins\Installer\GroupedPluginInstallerInterface;
use OpenColibri\Starter\Plugins\Installer\PluginInstallerInterface;

use function Castor\io;

final class PluginTasks
{
    /** @var array<string, PluginInstallerInterface> */
    private static array $installers = [];

    /** @var array<string, RemoverInterface> */
    private static array $removers = [];

    public function __construct(
        private readonly string $name,
        private readonly string $directory,
    ) {}

    public function __invoke(): iterable
    {
        $app = new App($this->name, $this->directory);

        yield [
            'task' => new AsTask('add', 'sylius:plugin', 'Adds plugins', ['sylius:add']),
            'function' => static function (#[AsRawTokens] array $plugins = []) use ($app): void {
                $installers = array_map(
                    static fn(callable $installer): callable => static fn() => $installer($app),
                    self::$installers,
                );

                $plugins = self::withoutOptions($plugins);

                if ([] === $plugins) {
                    [$plugins, $unchecked] = self::askWantedPlugins($app);

                    self::removeUnchecked($app, $unchecked);
                }

                foreach ($plugins as $plugin) {
                    if (!isset($installers[$plugin])) {
                        io()->warning(\sprintf('Unknown plugin "%s", skipping.', $plugin));

                        continue;
                    }
                    $installers[$plugin]();
                }
            },
        ];

        yield [
            'task' => new AsTask('remove', 'sylius:plugin', 'Removes plugins', ['sylius:remove']),
            'function' => static function (#[AsRawTokens] array $plugins = []) use ($app): void {
                $removers = array_map(
                    static fn(callable $remover): callable => static fn() => $remover($app),
                    self::$removers,
                );

                $plugins = self::withoutOptions($plugins);

                if ([] === $plugins) {
                    $plugins = io()->choice(
                        'Which plugins would you like to remove?',
                        self::choices(self::$removers),
                        multiSelect: true,
                    );
                }

                foreach ($plugins as $plugin) {
                    if (!isset($removers[$plugin])) {
                        io()->warning(\sprintf('Unknown plugin "%s", skipping.', $plugin));

                        continue;
                    }
                    $removers[$plugin]();
                }
            },
        ];
    }

    /**
     * The raw tokens of sylius:add and sylius:remove hold the global options too ("--no-interaction", "-v"…), which
     * name no plugin.
     *
     * @param list<string> $tokens
     *
     * @return list<string>
     */
    private static function withoutOptions(array $tokens): array
    {
        return array_values(array_filter($tokens, static fn(string $token): bool => !str_starts_with($token, '-')));
    }

    /**
     * Asks which plugins the app should have, the installed ones checked: unchecking one asks to remove it.
     *
     * @return array{0: list<string>, 1: list<string>} the plugins to install, and the installed plugins unchecked
     */
    private static function askWantedPlugins(App $app): array
    {
        $installed = array_values(array_intersect(self::installed($app->directory()), array_keys(self::$installers)));

        /** @var list<string> $wanted */
        $wanted = io()->choice(
            [] === $installed
                ? 'Which plugins would you like to install?'
                : 'Which plugins would you like to have? The installed ones are checked, uncheck one to remove it.',
            self::choices(self::$installers),
            [] === $installed ? null : implode(',', $installed),
            multiSelect: true,
        );

        return [
            array_values(array_diff($wanted, $installed)),
            array_values(array_diff($installed, $wanted)),
        ];
    }

    /**
     * Removes the installed plugins unchecked in the prompt of sylius:add, once confirmed: never a plugin that is
     * not installed, unlike a blind "only these" removal.
     *
     * @param list<string> $plugins
     */
    private static function removeUnchecked(App $app, array $plugins): void
    {
        $removable = array_values(array_filter($plugins, static fn(string $plugin): bool => isset(self::$removers[$plugin])));

        foreach (array_diff($plugins, $removable) as $plugin) {
            io()->warning(\sprintf('The "%s" plugin cannot be removed by sylius:remove, keeping it.', $plugin));
        }

        if ([] === $removable || !io()->confirm(\sprintf('Remove the unchecked plugins (%s)? Their data is dropped.', implode(', ', $removable)), false)) {
            return;
        }

        foreach ($removable as $plugin) {
            (self::$removers[$plugin])($app);
        }
    }

    /**
     * @return list<string> the plugins whose Composer package (PluginInstallerInterface::package()) the app requires,
     *                      custom ones included
     */
    public static function installed(string $directory): array
    {
        $file = $directory . '/composer.json';

        if (!is_file($file)) {
            return [];
        }

        $composer = json_decode((string) file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR);
        $packages = array_keys(array_merge(
            \is_array($composer['require'] ?? null) ? $composer['require'] : [],
            \is_array($composer['require-dev'] ?? null) ? $composer['require-dev'] : [],
        ));

        return array_keys(array_filter(self::packages(), static fn(string $package): bool => \in_array($package, $packages, true)));
    }

    /**
     * @return list<string> the plugins of a group (see GroupedPluginInstallerInterface), sorted by name
     */
    public static function group(string $group): array
    {
        $plugins = array_keys(array_filter(
            self::$installers,
            static fn(PluginInstallerInterface $installer): bool => $installer instanceof GroupedPluginInstallerInterface && $group === $installer->group(),
        ));
        sort($plugins);

        return $plugins;
    }

    /**
     * @param array<string, InstallerInterface|RemoverInterface> $components
     *
     * @return array<string, string>
     */
    public static function choices(array $components): array
    {
        return ComponentChoices::build($components);
    }

    /**
     * @return list<string> the plugins sylius:plugin:add can install
     */
    public static function installerNames(): array
    {
        return array_keys(self::$installers);
    }

    /**
     * @return array<string, string> the description of each plugin sylius:plugin:add can install
     */
    public static function descriptions(): array
    {
        $descriptions = array_map(static fn(PluginInstallerInterface $installer): string => (string) $installer->description(), self::$installers);
        ksort($descriptions);

        return $descriptions;
    }

    /**
     * @return list<string> the plugins sylius:plugin:remove can remove
     */
    public static function removerNames(): array
    {
        return array_keys(self::$removers);
    }

    /**
     * @return array<string, string> the Composer package of each plugin that requires one
     */
    public static function packages(): array
    {
        $packages = array_filter(array_map(static fn(PluginInstallerInterface $installer): ?string => $installer->package(), self::$installers));
        ksort($packages);

        return $packages;
    }

    public static function addInstaller(PluginInstallerInterface $installer): void
    {
        self::$installers[$installer->name()] = $installer;
    }

    public static function addRemover(RemoverInterface $remover): void
    {
        self::$removers[$remover->name()] = $remover;
    }
}
