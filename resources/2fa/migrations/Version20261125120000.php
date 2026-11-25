<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Written with the schema API rather than SQL, so it runs on MySQL as well as PostgreSQL.
 */
final class Version20261125120000 extends AbstractMigration
{
    private const array TABLES = ['sylius_admin_user', 'sylius_shop_user'];

    private const array COLUMNS = ['google_authenticator_secret', 'email_auth_code'];

    public function getDescription(): string
    {
        return 'Add the two-factor authentication secrets to the admin and shop users';
    }

    public function up(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            foreach (self::COLUMNS as $column) {
                $schema->getTable($table)->addColumn($column, Types::STRING, ['length' => 255, 'notnull' => false]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            foreach (self::COLUMNS as $column) {
                $schema->getTable($table)->dropColumn($column);
            }
        }
    }
}
