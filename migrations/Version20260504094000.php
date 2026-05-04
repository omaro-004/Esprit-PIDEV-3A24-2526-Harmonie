<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260504094000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename foreign key columns to add _id suffix.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\AbstractMySQLPlatform) {
            $this->abortIf(true, 'Migration can only be executed safely on mysql.');
        }

        // Rename courses.subjectid to subjectid_id
        $hasOld = (bool) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'subjectid'"
        );
        if ($hasOld) {
            $fkName = $this->connection->fetchOne(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'subjectid' AND REFERENCED_TABLE_NAME IS NOT NULL"
            );
            if ($fkName) {
                $this->addSql('ALTER TABLE courses DROP FOREIGN KEY '.$fkName);
            }
            $this->addSql('ALTER TABLE courses CHANGE subjectid subjectid_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE courses ADD CONSTRAINT FK_COURSES_SUBJECTID_ID FOREIGN KEY (subjectid_id) REFERENCES subject (id) ON DELETE SET NULL');
        }

        // Rename courses.userid to userid_id
        $hasOld = (bool) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'userid'"
        );
        if ($hasOld) {
            $fkName = $this->connection->fetchOne(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'userid' AND REFERENCED_TABLE_NAME IS NOT NULL"
            );
            if ($fkName) {
                $this->addSql('ALTER TABLE courses DROP FOREIGN KEY '.$fkName);
            }
            $this->addSql('ALTER TABLE courses CHANGE userid userid_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE courses ADD CONSTRAINT FK_COURSES_USERID_ID FOREIGN KEY (userid_id) REFERENCES `user` (user_id) ON DELETE SET NULL');
        }

        // Rename coursefile.courseid to courseid_id
        $hasOld = (bool) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'coursefile' AND COLUMN_NAME = 'courseid'"
        );
        if ($hasOld) {
            $fkName = $this->connection->fetchOne(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'coursefile' AND COLUMN_NAME = 'courseid' AND REFERENCED_TABLE_NAME IS NOT NULL"
            );
            if ($fkName) {
                $this->addSql('ALTER TABLE coursefile DROP FOREIGN KEY '.$fkName);
            }
            $this->addSql('ALTER TABLE coursefile CHANGE courseid courseid_id INT NOT NULL');
            $this->addSql('ALTER TABLE coursefile ADD CONSTRAINT FK_COURSEFILE_COURSEID_ID FOREIGN KEY (courseid_id) REFERENCES courses (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        // Rollback not implemented for simplicity
    }
}
