<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Written with the schema API rather than SQL, so it runs on MySQL as well as PostgreSQL.
 */
final class Version20261125130000 extends AbstractMigration
{
    private const array TABLES = ['sylius_avatar_image', 'sylius_product_image', 'sylius_taxon_image'];

    public function getDescription(): string
    {
        return 'Add the media of the JoliCode media bundle to the Sylius images';
    }

    public function up(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            // "media" is the Doctrine type of the JoliCode media bundle: a 255 characters string.
            $schema->getTable($table)->addColumn('media', 'media', ['length' => 255, 'notnull' => false]);
        }
    }

    public function postUp(Schema $schema): void
    {
        // Point the media at the current images, once the columns exist.
        foreach (self::TABLES as $table) {
            $this->connection->executeStatement(\sprintf('UPDATE %s SET media = path', $table));
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            $schema->getTable($table)->dropColumn('media');
        }
    }
}
