<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Installer;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\PhpFile;
use OpenColibri\Starter\Core\Util\Composer;
use OpenColibri\Starter\Core\Util\Database;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Symfony;

use function Castor\fs;
use function Castor\io;

final readonly class MollieInstaller implements GroupedPluginInstallerInterface
{
    /**
     * The interface and the traits the plugin needs on each sylius-standard entity.
     *
     * @var array<string, array{interface: string, traits: list<string>}>
     */
    public const array ENTITIES = [
        'src/Entity/Order/Order.php' => [
            'interface' => 'Sylius\\MolliePlugin\\Entity\\OrderInterface',
            'traits' => [
                'Sylius\\MolliePlugin\\Entity\\MolliePaymentIdOrderTrait',
                'Sylius\\MolliePlugin\\Entity\\QRCodeOrderTrait',
                'Sylius\\MolliePlugin\\Entity\\RecurringOrderTrait',
                'Sylius\\MolliePlugin\\Entity\\AbandonedEmailOrderTrait',
            ],
        ],
        'src/Entity/Payment/GatewayConfig.php' => [
            'interface' => 'Sylius\\MolliePlugin\\Entity\\GatewayConfigInterface',
            'traits' => ['Sylius\\MolliePlugin\\Entity\\GatewayConfigTrait'],
        ],
        'src/Entity/Product/Product.php' => [
            'interface' => 'Sylius\\MolliePlugin\\Entity\\ProductInterface',
            'traits' => ['Sylius\\MolliePlugin\\Entity\\ProductTrait'],
        ],
        'src/Entity/Product/ProductVariant.php' => [
            'interface' => 'Sylius\\MolliePlugin\\Entity\\ProductVariantInterface',
            'traits' => ['Sylius\\MolliePlugin\\Entity\\RecurringProductVariantTrait'],
        ],
        'src/Entity/User/AdminUser.php' => [
            'interface' => 'Sylius\\MolliePlugin\\Entity\\OnboardingStatusAwareInterface',
            'traits' => ['Sylius\\MolliePlugin\\Entity\\OnboardingStatusAwareTrait'],
        ],
    ];

    public const string GATEWAY_CONFIG = 'src/Entity/Payment/GatewayConfig.php';

    /**
     * The initial PostgreSQL migration of the plugin adds sylius_order.abandoned_email as NOT NULL without a default,
     * which fails as soon as the shop has orders.
     */
    private const string POSTGRESQL_MIGRATION = 'Sylius\\MolliePlugin\\Migrations\\Version20250509141720';

    private const string ABANDONED_EMAIL_COLUMN = 'ADD abandoned_email BOOLEAN NOT NULL';

    public function name(): string
    {
        return 'mollie';
    }

    public function description(): string
    {
        return 'Mollie payment gateway (cards, iDEAL, Bancontact, Klarna...), by Sylius';
    }

    public function package(): string
    {
        return 'sylius/mollie-plugin';
    }

    public function group(): string
    {
        return self::PAYMENT;
    }

    public function __invoke(App $app): void
    {
        io()->title('Adding Mollie plugin');

        Composer::allowContribRecipes($app);
        // The plugin mapping needs the entities below: clear the cache once they are there.
        Docker::run($app, 'composer require sylius/mollie-plugin --no-scripts');

        foreach (self::ENTITIES as $entity => ['interface' => $interface, 'traits' => $traits]) {
            $file = (new PhpFile($app->directory() . '/' . $entity))->addInterface($interface);

            foreach ($traits as $trait) {
                $file->addTrait($trait);
            }

            $file->save();
        }

        (new PhpFile($app->directory() . '/' . self::GATEWAY_CONFIG))
            ->addConstructor(<<<'PHP'
                    public function __construct()
                    {
                        parent::__construct();
                        $this->initializeMollieGatewayConfig();
                    }
                PHP)
            ->save()
        ;

        Symfony::cacheClear($app);
        Docker::run($app, 'bin/console assets:install public');
        self::runPostgresqlMigrationOnExistingOrders($app);
        Database::migrate($app);
    }

    /**
     * Runs the initial PostgreSQL migration of the plugin with a default on sylius_order.abandoned_email, so it
     * passes on a shop that already has orders. Nothing to do on MySQL, nor once it ran.
     */
    private static function runPostgresqlMigrationOnExistingOrders(App $app): void
    {
        $file = 'var/mollie-postgresql-migration.sql';
        $path = $app->directory() . '/' . $file;

        // --dry-run --write-sql only writes the SQL of the migration: it is skipped on MySQL, and empty once the tables exist.
        Docker::run($app, \sprintf('bin/console doctrine:migrations:execute --up --dry-run --write-sql=%s --no-interaction %s', $file, escapeshellarg(self::POSTGRESQL_MIGRATION)));
        $sql = is_file($path) ? (string) file_get_contents($path) : '';
        fs()->remove($path);

        if (!str_contains($sql, self::ABANDONED_EMAIL_COLUMN)) {
            return;
        }

        $sql = str_replace(self::ABANDONED_EMAIL_COLUMN, 'ADD abandoned_email BOOLEAN DEFAULT false NOT NULL', $sql)
            // The plugin mapping declares no default: drop it once the existing orders are filled.
            . "\nALTER TABLE sylius_order ALTER abandoned_email DROP DEFAULT;\n";

        // The written SQL also records the migration as executed.
        Docker::run($app, 'bin/console dbal:run-sql ' . escapeshellarg('BEGIN; ' . $sql . ' COMMIT;'));
    }
}
