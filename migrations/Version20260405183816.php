<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260405183816 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create meditation module tables';
    }

    public function up(Schema $schema): void
    {
        // Drop existing tables if they exist (from JavaFX) to recreate properly
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        $this->addSql('DROP TABLE IF EXISTS conseil');
        $this->addSql('DROP TABLE IF EXISTS journal_humeur');
        $this->addSql('DROP TABLE IF EXISTS session_meditation');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');

        // Create tables with proper structure
        $this->addSql('CREATE TABLE session_meditation (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            auteur VARCHAR(255) NOT NULL,
            duree INT NOT NULL,
            theme VARCHAR(255) NOT NULL,
            audio_url VARCHAR(500) DEFAULT NULL,
            INDEX IDX_A4AB6208A76ED395 (user_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_A4AB6208A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (user_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE conseil (
            id INT AUTO_INCREMENT NOT NULL,
            session_id INT NOT NULL,
            contenu LONGTEXT NOT NULL,
            INDEX IDX_3F3F0681613FECDF (session_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_3F3F0681613FECDF FOREIGN KEY (session_id) REFERENCES session_meditation (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE journal_humeur (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            date_journal DATE NOT NULL,
            humeur VARCHAR(50) NOT NULL,
            score INT NOT NULL,
            contenu LONGTEXT NOT NULL,
            INDEX IDX_D41CEA78A76ED395 (user_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_D41CEA78A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (user_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        $this->addSql('DROP TABLE IF EXISTS conseil');
        $this->addSql('DROP TABLE IF EXISTS journal_humeur');
        $this->addSql('DROP TABLE IF EXISTS session_meditation');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }
}
