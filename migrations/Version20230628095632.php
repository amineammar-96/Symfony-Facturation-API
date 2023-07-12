<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230628095632 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE email (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE invoice (id INT AUTO_INCREMENT NOT NULL, company_name VARCHAR(255) DEFAULT NULL, client_name VARCHAR(255) DEFAULT NULL, client_company_address VARCHAR(255) DEFAULT NULL, invoice_number VARCHAR(100) NOT NULL, invoice_service_description VARCHAR(255) NOT NULL, invoice_amount_ht VARCHAR(100) NOT NULL, invoice_amount_ttc VARCHAR(100) NOT NULL, invoice_tax_amount VARCHAR(100) NOT NULL, invoice_periode VARCHAR(255) NOT NULL, invoice_payment_condition VARCHAR(255) NOT NULL, client_company_postal_code VARCHAR(255) NOT NULL, client_address_city VARCHAR(255) NOT NULL, invoice_date VARCHAR(255) NOT NULL, related_invoice_ref VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, total_paid NUMERIC(10, 2) DEFAULT NULL, payment_status VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE mollie_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, mollie_id VARCHAR(255) NOT NULL, name VARCHAR(255) DEFAULT NULL, locale VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE transaction (id INT AUTO_INCREMENT NOT NULL, invoice_number VARCHAR(255) NOT NULL, transaction_amount NUMERIC(10, 2) NOT NULL, transaction_payment_method VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, mollie_customer_id VARCHAR(255) DEFAULT NULL, mollie_payment_id VARCHAR(255) DEFAULT NULL, transaction_description VARCHAR(255) DEFAULT NULL, mollie_payment_status VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE email');
        $this->addSql('DROP TABLE invoice');
        $this->addSql('DROP TABLE mollie_user');
        $this->addSql('DROP TABLE transaction');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
