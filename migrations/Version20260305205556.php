<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260305205556 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE accounts (id BINARY(16) NOT NULL, owner_name VARCHAR(100) NOT NULL, balance NUMERIC(19, 4) NOT NULL, currency VARCHAR(3) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE audit_logs (id BINARY(16) NOT NULL, transaction_id BINARY(16) DEFAULT NULL, event VARCHAR(100) NOT NULL, metadata JSON NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE transactions (id BINARY(16) NOT NULL, amount NUMERIC(19, 4) NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(20) NOT NULL, idempotency_key VARCHAR(255) NOT NULL, failure_reason LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, from_account_id BINARY(16) NOT NULL, to_account_id BINARY(16) NOT NULL, UNIQUE INDEX UNIQ_EAA81A4C7FD1C147 (idempotency_key), INDEX idx_idempotency_key (idempotency_key), INDEX idx_from_account (from_account_id), INDEX idx_to_account (to_account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CB0CF99BD FOREIGN KEY (from_account_id) REFERENCES accounts (id)');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CBC58BDC7 FOREIGN KEY (to_account_id) REFERENCES accounts (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4CB0CF99BD');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4CBC58BDC7');
        $this->addSql('DROP TABLE accounts');
        $this->addSql('DROP TABLE audit_logs');
        $this->addSql('DROP TABLE transactions');
    }
}
