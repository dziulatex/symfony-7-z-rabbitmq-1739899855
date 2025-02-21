<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250221135846 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE clients (id INT UNSIGNED NOT NULL, file_upload_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', full_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, city VARCHAR(255) NOT NULL, INDEX IDX_C82E7442C00547 (file_upload_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE file_upload (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', filename VARCHAR(255) NOT NULL, path VARCHAR(255) NOT NULL, uploaded_at DATETIME NOT NULL, md5_hash VARCHAR(32) NOT NULL, state VARCHAR(255) NOT NULL, error_message LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE sessions (sess_id VARBINARY(128) NOT NULL, sess_data LONGBLOB NOT NULL, sess_lifetime INT UNSIGNED NOT NULL, sess_time INT UNSIGNED NOT NULL, INDEX sess_lifetime_idx (sess_lifetime), PRIMARY KEY(sess_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_bin` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE clients ADD CONSTRAINT FK_C82E7442C00547 FOREIGN KEY (file_upload_id) REFERENCES file_upload (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_clients_file_upload_id ON clients (file_upload_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX IDX_clients_file_upload_id ON clients');
        $this->addSql('ALTER TABLE clients DROP FOREIGN KEY FK_C82E7442C00547');
        $this->addSql('DROP TABLE clients');
        $this->addSql('DROP TABLE file_upload');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE sessions');

    }
}
