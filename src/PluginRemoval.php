<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Util\Database;
use OpenColibri\Starter\Core\Util\Git;

use function Castor\io;

/**
 * How a plugin removal handles the database, decided before anything is removed:
 *
 * - a plugin that may have been deployed somewhere (its package is in the committed composer.lock, or a migration
 *   generated for it is committed) is dropped by a new migration, which every environment runs, and skipped where the
 *   plugin never was (see Database::generatePluginMigration());
 * - a plugin that never left this application is rolled back right away: its migrations go down, and the ones generated
 *   for it are deleted, so the application gets no migration for it at all.
 *
 * Outside a git repository (or in one that ignores the application), nothing proves the plugin was not deployed: it
 * gets a migration.
 */
final readonly class PluginRemoval
{
    /**
     * @param array<string, string> $schema     the mapped schema before the removal (see Database::mappedSchema())
     * @param list<string>          $migrations the migrations the plugin ships, to forget
     */
    private function __construct(
        private string $label,
        private bool $deployed,
        private array $schema,
        private array $migrations,
    ) {}

    /**
     * The description of the migration generated when the plugin is added.
     */
    public static function addDescription(string $label): string
    {
        return \sprintf('Add the %s plugin', $label);
    }

    /**
     * Call before removing anything: it rolls back a plugin that never left this application.
     *
     * @param string      $label               the plugin name in the migration descriptions ("SEO")
     * @param string      $package             the Composer package of the plugin
     * @param string|null $migrationsNamespace the namespace of the migrations the plugin ships, if any
     */
    public static function start(App $app, string $label, string $package, ?string $migrationsNamespace = null): self
    {
        $generated = Database::generatedMigrations($app, [self::addDescription($label), self::removeDescription($label)]);

        if (self::mayBeDeployed($app, $package, $generated)) {
            io()->note(\sprintf('The %s plugin may have been deployed: a migration will drop its tables.', $label));

            return new self(
                $label,
                true,
                Database::mappedSchema($app),
                null === $migrationsNamespace ? [] : Database::pluginMigrations($app, $migrationsNamespace),
            );
        }

        io()->note(\sprintf('The %s plugin was never committed: its migrations are rolled back.', $label));
        // Only the ones of this install: the committed ones (an earlier install and removal) may have run elsewhere.
        Database::discardGeneratedMigrations($app, array_values(array_filter(
            $generated,
            static fn(string $path): bool => false === self::committedFile($app, $path),
        )));

        if (null !== $migrationsNamespace) {
            Database::rollbackPluginMigrations($app, $migrationsNamespace);
        }

        return new self($label, false, [], []);
    }

    /**
     * Call once the plugin is removed and the cache cleared: it drops the tables of a plugin that may have been
     * deployed in a new migration.
     */
    public function finish(App $app): void
    {
        if (!$this->deployed) {
            return;
        }

        Database::migrate($app);
        Database::generatePluginMigration($app, self::removeDescription($this->label), $this->schema, $this->migrations);
        Database::migrate($app);
    }

    /**
     * Whether the installed plugin may have been deployed: its package is in the committed composer.lock, or the last
     * migration generated for it (the one of this install) is committed. The earlier ones, an install and removal
     * committed before, tell nothing about this install.
     *
     * @param list<string> $generatedMigrations the paths of the migrations generated for the plugin, in their order
     */
    public static function mayBeDeployed(App $app, string $package, array $generatedMigrations = []): bool
    {
        $lock = Git::committedFile($app, 'composer.lock');

        if (null === $lock) {
            return true;
        }

        if (false !== $lock && self::locks($lock, $package)) {
            return true;
        }

        $last = [] === $generatedMigrations ? null : $generatedMigrations[array_key_last($generatedMigrations)];

        return null !== $last && false !== self::committedFile($app, $last);
    }

    private static function committedFile(App $app, string $path): string|false|null
    {
        return Git::committedFile($app, substr($path, \strlen($app->directory()) + 1));
    }

    /**
     * Whether a composer.lock content locks the package.
     */
    public static function locks(string $lock, string $package): bool
    {
        $packages = json_decode($lock, true);

        if (!\is_array($packages)) {
            // Unreadable: nothing proves the package is missing.
            return true;
        }

        foreach (['packages', 'packages-dev'] as $section) {
            foreach (\is_array($packages[$section] ?? null) ? $packages[$section] : [] as $locked) {
                if (\is_array($locked) && ($locked['name'] ?? null) === $package) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function removeDescription(string $label): string
    {
        return \sprintf('Remove the %s plugin', $label);
    }
}
