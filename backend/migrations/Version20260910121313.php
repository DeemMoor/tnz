<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ручное время открытия записи у турнира (registration_opens_at).
 * NULL — считаем по-старому: четверг перед турниром, 16:00.
 */
final class Version20260910121313 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ручное время открытия записи у турнира (tournament.registration_opens_at)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tournament ADD registration_opens_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tournament DROP registration_opens_at');
    }
}
