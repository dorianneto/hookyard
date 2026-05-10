<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260510154557 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create audit_logs table to store audit log entries';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE audit_logs (id VARCHAR(255) NOT NULL, action VARCHAR(32) NOT NULL, resource VARCHAR(64) NOT NULL, resource_id VARCHAR(255) DEFAULT NULL, metadata JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id VARCHAR NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D62F2858A76ED395 ON audit_logs (user_id)');
        $this->addSql('CREATE INDEX idx_audit_logs_user_created ON audit_logs (user_id, created_at)');
        $this->addSql('CREATE INDEX idx_audit_logs_action ON audit_logs (action)');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT FK_D62F2858A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE audit_logs DROP CONSTRAINT FK_D62F2858A76ED395');
        $this->addSql('DROP TABLE audit_logs');
    }
}
