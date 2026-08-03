<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Таблица replaced_game: игры, вытесненные из сетки заменой игрока
 * (подошёл опоздавший — победитель играет заново уже с ним).
 */
final class Version20260803082324 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Лог игр, вытесненных заменой игрока в сетке (replaced_game)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE replaced_game (id INT AUTO_INCREMENT NOT NULL, played_at DATETIME NOT NULL, tournament_id INT NOT NULL, bracket_match_id INT DEFAULT NULL, winner_id INT NOT NULL, loser_id INT NOT NULL, INDEX IDX_B76F886333D1A3E7 (tournament_id), INDEX IDX_B76F88633BFD7AC5 (bracket_match_id), INDEX IDX_B76F88635DFCD4B8 (winner_id), INDEX IDX_B76F88631BCAA5F6 (loser_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE replaced_game ADD CONSTRAINT FK_B76F886333D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournament (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE replaced_game ADD CONSTRAINT FK_B76F88633BFD7AC5 FOREIGN KEY (bracket_match_id) REFERENCES bracket_match (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE replaced_game ADD CONSTRAINT FK_B76F88635DFCD4B8 FOREIGN KEY (winner_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE replaced_game ADD CONSTRAINT FK_B76F88631BCAA5F6 FOREIGN KEY (loser_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE replaced_game DROP FOREIGN KEY FK_B76F886333D1A3E7');
        $this->addSql('ALTER TABLE replaced_game DROP FOREIGN KEY FK_B76F88633BFD7AC5');
        $this->addSql('ALTER TABLE replaced_game DROP FOREIGN KEY FK_B76F88635DFCD4B8');
        $this->addSql('ALTER TABLE replaced_game DROP FOREIGN KEY FK_B76F88631BCAA5F6');
        $this->addSql('DROP TABLE replaced_game');
    }
}
