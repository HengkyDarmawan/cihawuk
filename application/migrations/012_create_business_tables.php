<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tahap 9 spesifikasi v1.2 (modul-backend 12.3): direktori UMKM.
 *
 * Profil usaha hanya boleh terbit setelah pemiliknya menyetujui. Dokumen sumber hanya
 * memuat kategori ekonomi agregat, bukan daftar usaha, jadi tabel ini sengaja dibiarkan
 * kosong sampai pengelola memasukkan usaha yang pemiliknya benar-benar setuju.
 */
class Migration_Create_business_tables extends CI_Migration {

	public function up()
	{
		$t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->db->query("CREATE TABLE businesses (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			slug VARCHAR(220) NOT NULL,
			name VARCHAR(200) NOT NULL,
			category VARCHAR(40) NOT NULL,
			owner_name VARCHAR(180) NULL,
			description VARCHAR(1000) NULL,
			products VARCHAR(600) NULL,
			public_location VARCHAR(400) NULL,
			public_contact VARCHAR(255) NULL,
			opening_hours VARCHAR(255) NULL,
			photo_media_id BIGINT UNSIGNED NULL,
			owner_consent TINYINT(1) NOT NULL DEFAULT 0,
			consent_recorded_at DATETIME NULL,
			consent_note VARCHAR(500) NULL,
			publication_status VARCHAR(20) NOT NULL DEFAULT 'draft',
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			published_at DATETIME NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			archived_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_businesses_public (public_id),
			UNIQUE KEY uq_businesses_slug (slug),
			KEY idx_businesses_public (publication_status, category),
			CONSTRAINT fk_businesses_photo FOREIGN KEY (photo_media_id) REFERENCES media_assets (id) ON DELETE SET NULL,
			CONSTRAINT fk_businesses_author FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");
	}

	public function down()
	{
		$this->db->query('SET FOREIGN_KEY_CHECKS = 0');
		$this->db->query('DROP TABLE IF EXISTS `businesses`');
		$this->db->query('SET FOREIGN_KEY_CHECKS = 1');
	}
}
