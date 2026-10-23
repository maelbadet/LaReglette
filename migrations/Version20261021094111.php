<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261021094111 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow account deletion while preserving boulders and removing personal ascents';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ascent DROP FOREIGN KEY `FK_7814569A76ED395`');
        $this->addSql('ALTER TABLE ascent ADD CONSTRAINT FK_7814569A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE boulder DROP FOREIGN KEY `FK_D17AF437AB159F5`');
        $this->addSql('ALTER TABLE boulder CHANGE opened_by_id opened_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE boulder ADD CONSTRAINT FK_D17AF437AB159F5 FOREIGN KEY (opened_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ascent DROP FOREIGN KEY FK_7814569A76ED395');
        $this->addSql('ALTER TABLE ascent ADD CONSTRAINT `FK_7814569A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE boulder DROP FOREIGN KEY FK_D17AF437AB159F5');
        $this->addSql('ALTER TABLE boulder CHANGE opened_by_id opened_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE boulder ADD CONSTRAINT `FK_D17AF437AB159F5` FOREIGN KEY (opened_by_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
