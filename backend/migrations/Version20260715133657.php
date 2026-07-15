<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715133657 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bracket_match (id INT AUTO_INCREMENT NOT NULL, table_number INT NOT NULL, round INT NOT NULL, slot INT NOT NULL, status VARCHAR(10) NOT NULL, played_at DATETIME DEFAULT NULL, tournament_id INT NOT NULL, player1_id INT DEFAULT NULL, player2_id INT DEFAULT NULL, winner_id INT DEFAULT NULL, INDEX IDX_1D9B0DD333D1A3E7 (tournament_id), INDEX IDX_1D9B0DD3C0990423 (player1_id), INDEX IDX_1D9B0DD3D22CABCD (player2_id), INDEX IDX_1D9B0DD35DFCD4B8 (winner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE bracket_match ADD CONSTRAINT FK_1D9B0DD333D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournament (id)');
        $this->addSql('ALTER TABLE bracket_match ADD CONSTRAINT FK_1D9B0DD3C0990423 FOREIGN KEY (player1_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE bracket_match ADD CONSTRAINT FK_1D9B0DD3D22CABCD FOREIGN KEY (player2_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE bracket_match ADD CONSTRAINT FK_1D9B0DD35DFCD4B8 FOREIGN KEY (winner_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bracket_match DROP FOREIGN KEY FK_1D9B0DD333D1A3E7');
        $this->addSql('ALTER TABLE bracket_match DROP FOREIGN KEY FK_1D9B0DD3C0990423');
        $this->addSql('ALTER TABLE bracket_match DROP FOREIGN KEY FK_1D9B0DD3D22CABCD');
        $this->addSql('ALTER TABLE bracket_match DROP FOREIGN KEY FK_1D9B0DD35DFCD4B8');
        $this->addSql('DROP TABLE bracket_match');
    }
}
