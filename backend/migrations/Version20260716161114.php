<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260716161114 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notification_log (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, subject VARCHAR(200) NOT NULL, recipient_count INT NOT NULL, created_at DATETIME NOT NULL, tournament_id INT DEFAULT NULL, INDEX IDX_ED15DF233D1A3E7 (tournament_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE notification_log ADD CONSTRAINT FK_ED15DF233D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournament (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notification_log DROP FOREIGN KEY FK_ED15DF233D1A3E7');
        $this->addSql('DROP TABLE notification_log');
    }
}
