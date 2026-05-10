<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260504093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename consommation.id_aliment to id_aliment_id.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\AbstractMySQLPlatform) {
            $this->abortIf(true, 'Migration can only be executed safely on mysql.');
        }

        $hasOld = (bool) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consommation' AND COLUMN_NAME = 'id_aliment'"
        );
        $hasNew = (bool) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consommation' AND COLUMN_NAME = 'id_aliment_id'"
        );

        if ($hasOld && !$hasNew) {
            $fkName = $this->connection->fetchOne(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consommation' AND COLUMN_NAME = 'id_aliment' AND REFERENCED_TABLE_NAME IS NOT NULL"
            );
            if ($fkName) {
                $this->addSql('ALTER TABLE consommation DROP FOREIGN KEY '.$fkName);
            }

            $this->addSql('ALTER TABLE consommation CHANGE id_aliment id_aliment_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE consommation ADD CONSTRAINT FK_CONSOMMATION_ALIMENT FOREIGN KEY (id_aliment_id) REFERENCES aliment (id_aliment) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\AbstractMySQLPlatform) {
            $this->abortIf(true, 'Migration can only be executed safely on mysql.');
        }

        $hasNew = (bool) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consommation' AND COLUMN_NAME = 'id_aliment_id'"
        );

        if ($hasNew) {
            $fkName = $this->connection->fetchOne(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consommation' AND COLUMN_NAME = 'id_aliment_id' AND REFERENCED_TABLE_NAME IS NOT NULL"
            );
            if ($fkName) {
                $this->addSql('ALTER TABLE consommation DROP FOREIGN KEY '.$fkName);
            }

            $this->addSql('ALTER TABLE consommation CHANGE id_aliment_id id_aliment INT DEFAULT NULL');
            $this->addSql('ALTER TABLE consommation ADD CONSTRAINT FK_CONSOMMATION_ALIMENT FOREIGN KEY (id_aliment) REFERENCES aliment (id_aliment) ON DELETE SET NULL');
        }
    }
}
