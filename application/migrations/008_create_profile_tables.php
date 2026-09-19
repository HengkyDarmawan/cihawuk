<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tahap 5 spesifikasi v1.2 (modul-backend 10.1 dan 10.2): profil desa sebagai blok
 * terstruktur berversi, bukan satu baris berisi HTML bebas.
 *
 * Setiap blok menyimpan periode berlaku, sumber, status verifikasi, dan versi.
 * Publikasi memakai `cms_publication_snapshots` dengan `target_type = 'profile'` dan
 * `target_id = 0`: satu snapshot berisi seluruh blok terbit beserta linimasa kepemimpinan,
 * sehingga halaman publik tidak pernah setengah lama setengah baru.
 *
 * status: draft, in_review, published, archived
 * verification_status: unverified, verified, disputed
 */
class Migration_Create_profile_tables extends CI_Migration {

	public function up()
	{
		$t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->db->query("CREATE TABLE profile_blocks (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			block_key VARCHAR(40) NOT NULL,
			title VARCHAR(160) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			current_version_id BIGINT UNSIGNED NULL,
			published_version_id BIGINT UNSIGNED NULL,
			period_start SMALLINT UNSIGNED NULL,
			period_end SMALLINT UNSIGNED NULL,
			verification_status VARCHAR(20) NOT NULL DEFAULT 'unverified',
			verified_by BIGINT UNSIGNED NULL,
			verified_at DATETIME NULL,
			source_id BIGINT UNSIGNED NULL,
			source_note VARCHAR(255) NULL,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			published_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_profile_blocks_public (public_id),
			UNIQUE KEY uq_profile_blocks_key (block_key),
			CONSTRAINT fk_profile_blocks_source FOREIGN KEY (source_id) REFERENCES source_documents (id) ON DELETE SET NULL,
			CONSTRAINT fk_profile_blocks_verifier FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE profile_block_versions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			block_id BIGINT UNSIGNED NOT NULL,
			version_no INT UNSIGNED NOT NULL,
			body_json LONGTEXT NOT NULL,
			change_note VARCHAR(255) NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_profile_block_versions (block_id, version_no),
			CONSTRAINT fk_profile_block_versions_block FOREIGN KEY (block_id) REFERENCES profile_blocks (id) ON DELETE CASCADE,
			CONSTRAINT fk_profile_block_versions_author FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		/*
		| Linimasa kepemimpinan. `year_end` boleh NULL dan `ongoing_claim` menandai teks
		| "sampai sekarang" pada dokumen sumber — itu klaim dokumen, bukan pernyataan bahwa
		| orangnya masih menjabat hari ini. Halaman publik wajib menuliskannya apa adanya.
		*/
		$this->db->query("CREATE TABLE leadership_terms (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			person_name VARCHAR(150) NOT NULL,
			position_title VARCHAR(150) NOT NULL DEFAULT 'Kepala Desa',
			year_start SMALLINT UNSIGNED NOT NULL,
			year_end SMALLINT UNSIGNED NULL,
			ongoing_claim TINYINT(1) NOT NULL DEFAULT 0,
			summary VARCHAR(600) NULL,
			photo_media_id BIGINT UNSIGNED NULL,
			source_id BIGINT UNSIGNED NULL,
			source_note VARCHAR(255) NULL,
			verification_status VARCHAR(20) NOT NULL DEFAULT 'unverified',
			publication_status VARCHAR(20) NOT NULL DEFAULT 'draft',
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_leadership_terms_public (public_id),
			KEY idx_leadership_terms_period (year_start, year_end),
			CONSTRAINT fk_leadership_terms_photo FOREIGN KEY (photo_media_id) REFERENCES media_assets (id) ON DELETE SET NULL,
			CONSTRAINT fk_leadership_terms_source FOREIGN KEY (source_id) REFERENCES source_documents (id) ON DELETE SET NULL
		) $t");
	}

	public function down()
	{
		$this->db->query('SET FOREIGN_KEY_CHECKS = 0');
		foreach (array('leadership_terms', 'profile_block_versions', 'profile_blocks') as $table)
		{
			$this->db->query('DROP TABLE IF EXISTS `'.$table.'`');
		}
		if ($this->db->table_exists('cms_publication_snapshots'))
		{
			$this->db->where('target_type', 'profile')->delete('cms_publication_snapshots');
		}
		$this->db->query('SET FOREIGN_KEY_CHECKS = 1');
	}
}
