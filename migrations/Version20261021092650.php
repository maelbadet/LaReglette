<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261021092650 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ascent (id INT AUTO_INCREMENT NOT NULL, completed_at DATETIME NOT NULL, rating SMALLINT NOT NULL, comment LONGTEXT DEFAULT NULL, user_id INT NOT NULL, boulder_id INT DEFAULT NULL, INDEX IDX_7814569A76ED395 (user_id), INDEX IDX_781456987658A6F (boulder_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE boulder (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, color VARCHAR(50) NOT NULL, grade SMALLINT NOT NULL, opened_at DATETIME DEFAULT NULL, dismantled_at DATETIME DEFAULT NULL, sector_id INT NOT NULL, opened_by_id INT NOT NULL, INDEX IDX_D17AF437DE95C867 (sector_id), INDEX IDX_D17AF437AB159F5 (opened_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE sector (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE ascent ADD CONSTRAINT FK_7814569A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE ascent ADD CONSTRAINT FK_781456987658A6F FOREIGN KEY (boulder_id) REFERENCES boulder (id)');
        $this->addSql('ALTER TABLE boulder ADD CONSTRAINT FK_D17AF437DE95C867 FOREIGN KEY (sector_id) REFERENCES sector (id)');
        $this->addSql('ALTER TABLE boulder ADD CONSTRAINT FK_D17AF437AB159F5 FOREIGN KEY (opened_by_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ascent DROP FOREIGN KEY FK_7814569A76ED395');
        $this->addSql('ALTER TABLE ascent DROP FOREIGN KEY FK_781456987658A6F');
        $this->addSql('ALTER TABLE boulder DROP FOREIGN KEY FK_D17AF437DE95C867');
        $this->addSql('ALTER TABLE boulder DROP FOREIGN KEY FK_D17AF437AB159F5');
        $this->addSql('DROP TABLE ascent');
        $this->addSql('DROP TABLE boulder');
        $this->addSql('DROP TABLE sector');
    }
}
