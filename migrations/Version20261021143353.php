<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261021143353 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store asynchronous success count, climber count and average rating on boulders';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE boulder ADD success_count INT DEFAULT 0 NOT NULL, ADD climber_count INT DEFAULT 0 NOT NULL, ADD average_rating DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE boulder DROP success_count, DROP climber_count, DROP average_rating');
    }
}
