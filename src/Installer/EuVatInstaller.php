<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Plugins\Installer;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\PhpFile;
use OpenColibri\Starter\Core\Util\Composer;
use OpenColibri\Starter\Core\Util\Database;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Symfony;
use OpenColibri\Starter\Plugins\PluginResourceCopier;

use function Castor\io;

final readonly class EuVatInstaller implements GroupedPluginInstallerInterface
{
    public const string PACKAGE = 'flux-se/sylius-eu-vat-plugin';

    public const string BUNDLE = 'FluxSE\\SyliusEUVatPlugin\\FluxSESyliusEUVatPlugin';

    public const string MIGRATIONS = 'FluxSE\\SyliusEUVatPlugin\\Migrations';

    /**
     * The sylius-standard entities the plugin needs: the base country and European zone of the channels, the VAT
     * number of the addresses.
     *
     * @var array<string, array{interface: string, trait: string}>
     */
    public const array ENTITIES = [
        'src/Entity/Channel/Channel.php' => [
            'interface' => 'FluxSE\\SyliusEUVatPlugin\\Entity\\EuropeanChannelAwareInterface',
            'trait' => 'FluxSE\\SyliusEUVatPlugin\\Entity\\EuropeanChannelAwareTrait',
        ],
        'src/Entity/Addressing/Address.php' => [
            'interface' => 'FluxSE\\SyliusEUVatPlugin\\Entity\\VATNumberAwareInterface',
            'trait' => 'FluxSE\\SyliusEUVatPlugin\\Entity\\VATNumberAwareTrait',
        ],
    ];

    /** The zone of the member states, the one the plugin fixtures create. */
    public const string ZONE = 'EU';

    /** The member states of the European Union, as in the plugin fixtures. */
    private const array COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU',
        'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
    ];

    public function name(): string
    {
        return 'eu_vat';
    }

    public function description(): string
    {
        return 'EU VAT number on addresses, checked with VIES, and the reverse charge rule, by FLUX-SE';
    }

    public function package(): string
    {
        return self::PACKAGE;
    }

    public function group(): string
    {
        return self::ORDERS;
    }

    public function __invoke(App $app): void
    {
        io()->title('Adding EU VAT plugin');

        // The VIES client calls the SOAP web service of the European Commission.
        if (!preg_match('/^soap\s*$/m', Docker::run($app, 'php -m')->getOutput())) {
            throw new \RuntimeException('The EU VAT plugin needs the PHP soap extension: rebuild the image with "castor docker:build", restart it with "castor up", then add the plugin again.');
        }

        Composer::allowContribRecipes($app);
        // The plugin has no recipe, and its mapping needs the entities below: clear the cache once they are there.
        Docker::run($app, 'composer require ' . self::PACKAGE . ' --no-scripts');
        Symfony::addBundle($app, 'Prometee\\VIESClientBundle\\VIESClientBundle');
        Symfony::addBundle($app, self::BUNDLE);

        // Its admin and shop Twig hooks.
        PluginResourceCopier::copy($app, 'eu_vat');

        foreach (self::ENTITIES as $entity => ['interface' => $interface, 'trait' => $trait]) {
            (new PhpFile($app->directory() . '/' . $entity))
                ->addInterface($interface)
                ->addTrait($trait)
                ->save()
            ;
        }

        Symfony::cacheClear($app);
        // The plugin ships its migration: the VAT number of the addresses, the base country and zone of the channels.
        Database::migrate($app);
        self::configureChannels($app);
    }

    private static function isPostgresql(App $app): bool
    {
        return str_contains(Docker::run($app, 'bin/console dbal:run-sql ' . escapeshellarg('SELECT version()'))->getOutput(), 'PostgreSQL');
    }

    /**
     * Without a base country and a European zone, a channel charges the VAT to every customer: give them the country of
     * their billing data and the European Union zone, created with its countries (disabled) when the shop lacks them.
     * A channel already configured, or an existing EU zone, is left as it is.
     */
    private static function configureChannels(App $app): void
    {
        // Doctrine maps the ids to sequences on PostgreSQL, to auto-increment columns on MySQL.
        $id = self::isPostgresql($app) ? static fn(string $table): string => \sprintf("nextval('%s_id_seq')", $table) : static fn(): string => 'NULL';
        $countries = implode(' UNION ALL ', array_map(static fn(string $code): string => \sprintf("SELECT '%s' AS code", $code), self::COUNTRIES));
        $zone = \sprintf("'%s'", self::ZONE);

        $statements = [
            \sprintf(
                'INSERT INTO sylius_country (id, code, enabled) SELECT %s, eu.code, false FROM (%s) eu WHERE NOT EXISTS (SELECT 1 FROM sylius_country c WHERE c.code = eu.code)',
                $id('sylius_country'),
                $countries,
            ),
            \sprintf(
                "INSERT INTO sylius_zone (id, code, name, type, scope, priority) SELECT %s, %s, 'European Union', 'country', 'all', 0 FROM (SELECT 1 AS one) z WHERE NOT EXISTS (SELECT 1 FROM sylius_zone WHERE code = %2\$s)",
                $id('sylius_zone'),
                $zone,
            ),
            // Only a zone without members: the one just created, not a zone of the shop.
            \sprintf(
                "INSERT INTO sylius_zone_member (id, belongs_to, code) SELECT %s, z.id, eu.code FROM sylius_zone z CROSS JOIN (%s) eu WHERE z.code = %s AND z.type = 'country' AND NOT EXISTS (SELECT 1 FROM sylius_zone_member m WHERE m.belongs_to = z.id)",
                $id('sylius_zone_member'),
                $countries,
                $zone,
            ),
            \sprintf('UPDATE sylius_channel SET european_zone_id = (SELECT id FROM sylius_zone WHERE code = %s) WHERE european_zone_id IS NULL', $zone),
            'UPDATE sylius_channel SET base_country_id = (SELECT c.id FROM sylius_shop_billing_data b INNER JOIN sylius_country c ON c.code = b.country_code WHERE b.id = sylius_channel.shop_billing_data_id) WHERE base_country_id IS NULL',
        ];

        foreach ($statements as $statement) {
            Docker::run($app, 'bin/console dbal:run-sql ' . escapeshellarg($statement));
        }
    }
}
