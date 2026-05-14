<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260513213722 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix foreign key constraints to add ON DELETE CASCADE';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE delivery_attempts DROP CONSTRAINT fk_dea6060821af7e36');
        $this->addSql('ALTER TABLE delivery_attempts DROP CONSTRAINT fk_dea6060871f7e88b');
        $this->addSql('ALTER TABLE delivery_attempts ADD CONSTRAINT FK_DEA6060821AF7E36 FOREIGN KEY (endpoint_id) REFERENCES endpoints (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE delivery_attempts ADD CONSTRAINT FK_DEA6060871F7E88B FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE endpoints DROP CONSTRAINT fk_dc1d25b0953c1c61');
        $this->addSql('ALTER TABLE endpoints ADD CONSTRAINT FK_DC1D25B0953C1C61 FOREIGN KEY (source_id) REFERENCES sources (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_endpoint_deliveries DROP CONSTRAINT fk_d994eef321af7e36');
        $this->addSql('ALTER TABLE event_endpoint_deliveries DROP CONSTRAINT fk_d994eef371f7e88b');
        $this->addSql('ALTER TABLE event_endpoint_deliveries ADD CONSTRAINT FK_D994EEF321AF7E36 FOREIGN KEY (endpoint_id) REFERENCES endpoints (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_endpoint_deliveries ADD CONSTRAINT FK_D994EEF371F7E88B FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE events DROP CONSTRAINT fk_5387574a953c1c61');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT FK_5387574A953C1C61 FOREIGN KEY (source_id) REFERENCES sources (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE delivery_attempts DROP CONSTRAINT FK_DEA6060871F7E88B');
        $this->addSql('ALTER TABLE delivery_attempts DROP CONSTRAINT FK_DEA6060821AF7E36');
        $this->addSql('ALTER TABLE delivery_attempts ADD CONSTRAINT fk_dea6060871f7e88b FOREIGN KEY (event_id) REFERENCES events (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE delivery_attempts ADD CONSTRAINT fk_dea6060821af7e36 FOREIGN KEY (endpoint_id) REFERENCES endpoints (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE endpoints DROP CONSTRAINT FK_DC1D25B0953C1C61');
        $this->addSql('ALTER TABLE endpoints ADD CONSTRAINT fk_dc1d25b0953c1c61 FOREIGN KEY (source_id) REFERENCES sources (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE event_endpoint_deliveries DROP CONSTRAINT FK_D994EEF371F7E88B');
        $this->addSql('ALTER TABLE event_endpoint_deliveries DROP CONSTRAINT FK_D994EEF321AF7E36');
        $this->addSql('ALTER TABLE event_endpoint_deliveries ADD CONSTRAINT fk_d994eef371f7e88b FOREIGN KEY (event_id) REFERENCES events (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE event_endpoint_deliveries ADD CONSTRAINT fk_d994eef321af7e36 FOREIGN KEY (endpoint_id) REFERENCES endpoints (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE events DROP CONSTRAINT FK_5387574A953C1C61');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT fk_5387574a953c1c61 FOREIGN KEY (source_id) REFERENCES sources (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
