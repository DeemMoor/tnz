<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715084214 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE tournament (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, date DATE NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tournament_entry (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, registered_at DATETIME NOT NULL, checked_in TINYINT NOT NULL, checked_in_at DATETIME DEFAULT NULL, table_number INT DEFAULT NULL, eliminated TINYINT NOT NULL, tournament_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_EA770D6933D1A3E7 (tournament_id), INDEX IDX_EA770D69A76ED395 (user_id), UNIQUE INDEX uniq_entry_tournament_user (tournament_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE tournament_entry ADD CONSTRAINT FK_EA770D6933D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournament (id)');
        $this->addSql('ALTER TABLE tournament_entry ADD CONSTRAINT FK_EA770D69A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tournament_entry DROP FOREIGN KEY FK_EA770D6933D1A3E7');
        $this->addSql('ALTER TABLE tournament_entry DROP FOREIGN KEY FK_EA770D69A76ED395');
        $this->addSql('DROP TABLE tournament');
        $this->addSql('DROP TABLE tournament_entry');
    }
}
