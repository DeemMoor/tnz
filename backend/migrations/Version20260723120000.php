<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Каскадное удаление матчей и записей участников при удалении турнира.
 *
 * Раньше внешние ключи bracket_match.tournament_id и tournament_entry.tournament_id
 * стояли без ON DELETE, поэтому сломанный турнир нельзя было удалить ни из админки,
 * ни простым DELETE — мешали зависимые строки. Теперь удаление турнира само чистит
 * его сетку и участников.
 */
final class Version20260723120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ON DELETE CASCADE на FK турнира в bracket_match и tournament_entry';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bracket_match DROP FOREIGN KEY FK_1D9B0DD333D1A3E7');
        $this->addSql('ALTER TABLE bracket_match ADD CONSTRAINT FK_1D9B0DD333D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournament (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tournament_entry DROP FOREIGN KEY FK_EA770D6933D1A3E7');
        $this->addSql('ALTER TABLE tournament_entry ADD CONSTRAINT FK_EA770D6933D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournament (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bracket_match DROP FOREIGN KEY FK_1D9B0DD333D1A3E7');
        $this->addSql('ALTER TABLE bracket_match ADD CONSTRAINT FK_1D9B0DD333D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournament (id)');
        $this->addSql('ALTER TABLE tournament_entry DROP FOREIGN KEY FK_EA770D6933D1A3E7');
        $this->addSql('ALTER TABLE tournament_entry ADD CONSTRAINT FK_EA770D6933D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournament (id)');
    }
}
