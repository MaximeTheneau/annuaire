<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260302103441 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE address (id BINARY(16) NOT NULL, name VARCHAR(180) DEFAULT NULL, slug VARCHAR(200) DEFAULT NULL, formatted VARCHAR(255) NOT NULL, google_place_id VARCHAR(255) NOT NULL, lat NUMERIC(10, 7) DEFAULT NULL, lng NUMERIC(10, 7) DEFAULT NULL, postal_code VARCHAR(10) NOT NULL, city_id BINARY(16) NOT NULL, INDEX IDX_D4E6F818BAC62AF (city_id), UNIQUE INDEX uniq_address_place (google_place_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE app_user (id BINARY(16) NOT NULL, name VARCHAR(180) DEFAULT NULL, slug VARCHAR(200) DEFAULT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, is_verified TINYINT NOT NULL, two_factor_enabled TINYINT NOT NULL, last_login_at DATETIME DEFAULT NULL, last_login_ip VARCHAR(45) DEFAULT NULL, created_at DATETIME NOT NULL, auth_code VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) NOT NULL, first_name VARCHAR(255) NOT NULL, UNIQUE INDEX uniq_user_email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE category (id BINARY(16) NOT NULL, name VARCHAR(180) DEFAULT NULL, slug VARCHAR(200) DEFAULT NULL, UNIQUE INDEX uniq_category_slug (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE city (id BINARY(16) NOT NULL, name VARCHAR(180) DEFAULT NULL, slug VARCHAR(200) DEFAULT NULL, insee_code VARCHAR(5) DEFAULT NULL, department_id BINARY(16) NOT NULL, INDEX IDX_2D5B0234AE80F5DF (department_id), UNIQUE INDEX uniq_city_slug_department (slug, department_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE company (id BINARY(16) NOT NULL, name VARCHAR(180) DEFAULT NULL, slug VARCHAR(200) DEFAULT NULL, siret BIGINT NOT NULL, phone VARCHAR(30) NOT NULL, website VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, approved TINYINT DEFAULT NULL, img VARCHAR(500) DEFAULT NULL, srcset VARCHAR(1000) DEFAULT NULL, img_width INT DEFAULT NULL, img_height INT DEFAULT NULL, owner_id BINARY(16) NOT NULL, address_id BINARY(16) NOT NULL, INDEX IDX_4FBF094FF5B7AF75 (address_id), UNIQUE INDEX uniq_company_siret (siret), UNIQUE INDEX uniq_company_name (name), UNIQUE INDEX uniq_company_slug (slug), UNIQUE INDEX uniq_company_owner (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE company_category (company_id BINARY(16) NOT NULL, category_id BINARY(16) NOT NULL, INDEX IDX_1EDB0CAC979B1AD6 (company_id), INDEX IDX_1EDB0CAC12469DE2 (category_id), PRIMARY KEY (company_id, category_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE company_intervention_department (company_id BINARY(16) NOT NULL, department_id BINARY(16) NOT NULL, INDEX IDX_B0CBE0FE979B1AD6 (company_id), INDEX IDX_B0CBE0FEAE80F5DF (department_id), PRIMARY KEY (company_id, department_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE department (id BINARY(16) NOT NULL, name VARCHAR(180) DEFAULT NULL, slug VARCHAR(200) DEFAULT NULL, code VARCHAR(5) NOT NULL, intervention_area_id BINARY(16) DEFAULT NULL, INDEX IDX_CD1DE18AF6E134F9 (intervention_area_id), UNIQUE INDEX uniq_department_code (code), UNIQUE INDEX uniq_department_slug (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE intervention_area (id BINARY(16) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE security_token (id BINARY(16) NOT NULL, name VARCHAR(180) DEFAULT NULL, slug VARCHAR(200) DEFAULT NULL, type VARCHAR(40) NOT NULL, token VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, payload JSON DEFAULT NULL, user_id BINARY(16) NOT NULL, INDEX IDX_B38B4291A76ED395 (user_id), UNIQUE INDEX uniq_security_token_token (token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE address ADD CONSTRAINT FK_D4E6F818BAC62AF FOREIGN KEY (city_id) REFERENCES city (id)');
        $this->addSql('ALTER TABLE city ADD CONSTRAINT FK_2D5B0234AE80F5DF FOREIGN KEY (department_id) REFERENCES department (id)');
        $this->addSql('ALTER TABLE company ADD CONSTRAINT FK_4FBF094F7E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE company ADD CONSTRAINT FK_4FBF094FF5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id)');
        $this->addSql('ALTER TABLE company_category ADD CONSTRAINT FK_1EDB0CAC979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE company_category ADD CONSTRAINT FK_1EDB0CAC12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE company_intervention_department ADD CONSTRAINT FK_B0CBE0FE979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE company_intervention_department ADD CONSTRAINT FK_B0CBE0FEAE80F5DF FOREIGN KEY (department_id) REFERENCES department (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE department ADD CONSTRAINT FK_CD1DE18AF6E134F9 FOREIGN KEY (intervention_area_id) REFERENCES intervention_area (id)');
        $this->addSql('ALTER TABLE security_token ADD CONSTRAINT FK_B38B4291A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE address DROP FOREIGN KEY FK_D4E6F818BAC62AF');
        $this->addSql('ALTER TABLE city DROP FOREIGN KEY FK_2D5B0234AE80F5DF');
        $this->addSql('ALTER TABLE company DROP FOREIGN KEY FK_4FBF094F7E3C61F9');
        $this->addSql('ALTER TABLE company DROP FOREIGN KEY FK_4FBF094FF5B7AF75');
        $this->addSql('ALTER TABLE company_category DROP FOREIGN KEY FK_1EDB0CAC979B1AD6');
        $this->addSql('ALTER TABLE company_category DROP FOREIGN KEY FK_1EDB0CAC12469DE2');
        $this->addSql('ALTER TABLE company_intervention_department DROP FOREIGN KEY FK_B0CBE0FE979B1AD6');
        $this->addSql('ALTER TABLE company_intervention_department DROP FOREIGN KEY FK_B0CBE0FEAE80F5DF');
        $this->addSql('ALTER TABLE department DROP FOREIGN KEY FK_CD1DE18AF6E134F9');
        $this->addSql('ALTER TABLE security_token DROP FOREIGN KEY FK_B38B4291A76ED395');
        $this->addSql('DROP TABLE address');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE city');
        $this->addSql('DROP TABLE company');
        $this->addSql('DROP TABLE company_category');
        $this->addSql('DROP TABLE company_intervention_department');
        $this->addSql('DROP TABLE department');
        $this->addSql('DROP TABLE intervention_area');
        $this->addSql('DROP TABLE security_token');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
