<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260510135030 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE delivery_attempts ALTER event_id TYPE VARCHAR');
        $this->addSql('ALTER TABLE delivery_attempts ALTER endpoint_id TYPE VARCHAR');
        $this->addSql('ALTER TABLE delivery_attempts ADD CONSTRAINT FK_DEA6060871F7E88B FOREIGN KEY (event_id) REFERENCES events (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE delivery_attempts ADD CONSTRAINT FK_DEA6060821AF7E36 FOREIGN KEY (endpoint_id) REFERENCES endpoints (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_DEA6060871F7E88B ON delivery_attempts (event_id)');
        $this->addSql('CREATE INDEX IDX_DEA6060821AF7E36 ON delivery_attempts (endpoint_id)');
        $this->addSql('ALTER TABLE endpoints ALTER source_id TYPE VARCHAR');
        $this->addSql('ALTER TABLE endpoints ADD CONSTRAINT FK_DC1D25B0953C1C61 FOREIGN KEY (source_id) REFERENCES sources (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_DC1D25B0953C1C61 ON endpoints (source_id)');
        $this->addSql('ALTER TABLE event_endpoint_deliveries ALTER event_id TYPE VARCHAR');
        $this->addSql('ALTER TABLE event_endpoint_deliveries ALTER endpoint_id TYPE VARCHAR');
        $this->addSql('ALTER TABLE event_endpoint_deliveries ADD CONSTRAINT FK_D994EEF371F7E88B FOREIGN KEY (event_id) REFERENCES events (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_endpoint_deliveries ADD CONSTRAINT FK_D994EEF321AF7E36 FOREIGN KEY (endpoint_id) REFERENCES endpoints (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_D994EEF321AF7E36 ON event_endpoint_deliveries (endpoint_id)');
        $this->addSql('ALTER TABLE events ALTER source_id TYPE VARCHAR');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT FK_5387574A953C1C61 FOREIGN KEY (source_id) REFERENCES sources (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_5387574A953C1C61 ON events (source_id)');
        $this->addSql('ALTER TABLE request_usage ALTER user_id TYPE VARCHAR');
        $this->addSql('ALTER TABLE request_usage ADD CONSTRAINT FK_8DFCE255A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_8DFCE255A76ED395 ON request_usage (user_id)');
        $this->addSql('ALTER TABLE sources ALTER user_id TYPE VARCHAR');
        $this->addSql('ALTER TABLE sources ADD CONSTRAINT FK_D25D65F2A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_D25D65F2A76ED395 ON sources (user_id)');
        $this->addSql('ALTER TABLE users ALTER plan_id TYPE VARCHAR');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9E899029B FOREIGN KEY (plan_id) REFERENCES plans (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_1483A5E9E899029B ON users (plan_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE delivery_attempts DROP CONSTRAINT FK_DEA6060871F7E88B');
        $this->addSql('ALTER TABLE delivery_attempts DROP CONSTRAINT FK_DEA6060821AF7E36');
        $this->addSql('DROP INDEX IDX_DEA6060871F7E88B');
        $this->addSql('DROP INDEX IDX_DEA6060821AF7E36');
        $this->addSql('ALTER TABLE delivery_attempts ALTER event_id TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE delivery_attempts ALTER endpoint_id TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE endpoints DROP CONSTRAINT FK_DC1D25B0953C1C61');
        $this->addSql('DROP INDEX IDX_DC1D25B0953C1C61');
        $this->addSql('ALTER TABLE endpoints ALTER source_id TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE event_endpoint_deliveries DROP CONSTRAINT FK_D994EEF371F7E88B');
        $this->addSql('ALTER TABLE event_endpoint_deliveries DROP CONSTRAINT FK_D994EEF321AF7E36');
        $this->addSql('DROP INDEX IDX_D994EEF321AF7E36');
        $this->addSql('ALTER TABLE event_endpoint_deliveries ALTER event_id TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE event_endpoint_deliveries ALTER endpoint_id TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE events DROP CONSTRAINT FK_5387574A953C1C61');
        $this->addSql('DROP INDEX IDX_5387574A953C1C61');
        $this->addSql('ALTER TABLE events ALTER source_id TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE request_usage DROP CONSTRAINT FK_8DFCE255A76ED395');
        $this->addSql('DROP INDEX IDX_8DFCE255A76ED395');
        $this->addSql('ALTER TABLE request_usage ALTER user_id TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE sources DROP CONSTRAINT FK_D25D65F2A76ED395');
        $this->addSql('DROP INDEX IDX_D25D65F2A76ED395');
        $this->addSql('ALTER TABLE sources ALTER user_id TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE users DROP CONSTRAINT FK_1483A5E9E899029B');
        $this->addSql('DROP INDEX IDX_1483A5E9E899029B');
        $this->addSql('ALTER TABLE users ALTER plan_id TYPE VARCHAR(255)');
    }
}
