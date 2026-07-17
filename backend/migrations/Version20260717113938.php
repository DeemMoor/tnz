<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260717113938 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bracket_match DROP FOREIGN KEY `FK_1D9B0DD35DFCD4B8`');
        $this->addSql('ALTER TABLE bracket_match DROP FOREIGN KEY `FK_1D9B0DD3C0990423`');
        $this->addSql('ALTER TABLE bracket_match DROP FOREIGN KEY `FK_1D9B0DD3D22CABCD`');
        $this->addSql('ALTER TABLE bracket_match ADD CONSTRAINT FK_1D9B0DD35DFCD4B8 FOREIGN KEY (winner_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE bracket_match ADD CONSTRAINT FK_1D9B0DD3C0990423 FOREIGN KEY (player1_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE bracket_match ADD CONSTRAINT FK_1D9B0DD3D22CABCD FOREIGN KEY (player2_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE tournament_entry DROP FOREIGN KEY `FK_EA770D69A76ED395`');
        $this->addSql('ALTER TABLE tournament_entry ADD CONSTRAINT FK_EA770D69A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bracket_match DROP FOREIGN KEY FK_1D9B0DD3C0990423');
        $this->addSql('ALTER TABLE bracket_match DROP FOREIGN KEY FK_1D9B0DD3D22CABCD');
        $this->addSql('ALTER TABLE bracket_match DROP FOREIGN KEY FK_1D9B0DD35DFCD4B8');
        $this->addSql('ALTER TABLE bracket_match ADD CONSTRAINT `FK_1D9B0DD3C0990423` FOREIGN KEY (player1_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE bracket_match ADD CONSTRAINT `FK_1D9B0DD3D22CABCD` FOREIGN KEY (player2_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE bracket_match ADD CONSTRAINT `FK_1D9B0DD35DFCD4B8` FOREIGN KEY (winner_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE tournament_entry DROP FOREIGN KEY FK_EA770D69A76ED395');
        $this->addSql('ALTER TABLE tournament_entry ADD CONSTRAINT `FK_EA770D69A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
