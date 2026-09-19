<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tahap 7 spesifikasi v1.2 (prompt-master 18.6, modul-backend 10.3).
 *
 * Struktur organisasi dipisah menjadi periode, unit/lembaga, jabatan, orang, dan penugasan
 * supaya pejabat dapat berganti tanpa menghapus jabatan atau menyusun ulang seluruh node.
 *
 * `people` sengaja terpisah dari `users`: menutup akun tidak boleh menghapus profil pejabat,
 * dan sebaliknya. Nomor SK disimpan terenkripsi dan tidak pernah keluar ke halaman publik.
 *
 * Publikasi memakai `cms_publication_snapshots` dengan `target_type = 'organization'` dan
 * `target_id` = id periode.
 */
class Migration_Create_organization_tables extends CI_Migration {

	public function up()
	{
		$t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->db->query("CREATE TABLE org_periods (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			name VARCHAR(160) NOT NULL,
			year_start SMALLINT UNSIGNED NOT NULL,
			year_end SMALLINT UNSIGNED NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			is_public_default TINYINT(1) NOT NULL DEFAULT 0,
			note VARCHAR(500) NULL,
			published_at DATETIME NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_org_periods_public (public_id),
			KEY idx_org_periods_status (status, year_start),
			CONSTRAINT fk_org_periods_author FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE org_units (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			period_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(180) NOT NULL,
			unit_type VARCHAR(40) NOT NULL DEFAULT 'village_government',
			parent_id BIGINT UNSIGNED NULL,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_org_units_public (public_id),
			KEY idx_org_units_period (period_id, parent_id),
			CONSTRAINT fk_org_units_period FOREIGN KEY (period_id) REFERENCES org_periods (id) ON DELETE CASCADE,
			CONSTRAINT fk_org_units_parent FOREIGN KEY (parent_id) REFERENCES org_units (id) ON DELETE RESTRICT
		) $t");

		$this->db->query("CREATE TABLE org_positions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			period_id BIGINT UNSIGNED NOT NULL,
			unit_id BIGINT UNSIGNED NULL,
			parent_id BIGINT UNSIGNED NULL,
			title VARCHAR(180) NOT NULL,
			level TINYINT UNSIGNED NOT NULL DEFAULT 1,
			duties_public VARCHAR(1000) NULL,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_org_positions_public (public_id),
			KEY idx_org_positions_period (period_id, parent_id),
			CONSTRAINT fk_org_positions_period FOREIGN KEY (period_id) REFERENCES org_periods (id) ON DELETE CASCADE,
			CONSTRAINT fk_org_positions_unit FOREIGN KEY (unit_id) REFERENCES org_units (id) ON DELETE SET NULL,
			CONSTRAINT fk_org_positions_parent FOREIGN KEY (parent_id) REFERENCES org_positions (id) ON DELETE RESTRICT
		) $t");

		// Entitas orang; TIDAK terhubung ke akun login.
		$this->db->query("CREATE TABLE people (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			full_name VARCHAR(180) NOT NULL,
			title_prefix VARCHAR(40) NULL,
			title_suffix VARCHAR(60) NULL,
			photo_media_id BIGINT UNSIGNED NULL,
			bio_public VARCHAR(1000) NULL,
			photo_consent TINYINT(1) NOT NULL DEFAULT 0,
			data_status VARCHAR(20) NOT NULL DEFAULT 'draft',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_people_public (public_id),
			KEY idx_people_name (full_name),
			CONSTRAINT fk_people_photo FOREIGN KEY (photo_media_id) REFERENCES media_assets (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE org_assignments (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			period_id BIGINT UNSIGNED NOT NULL,
			position_id BIGINT UNSIGNED NOT NULL,
			person_id BIGINT UNSIGNED NULL,
			assignment_type VARCHAR(20) NOT NULL DEFAULT 'definitive',
			start_date DATE NULL,
			end_date DATE NULL,
			decree_number_ciphertext TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			end_reason VARCHAR(255) NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_org_assignments_public (public_id),
			KEY idx_org_assignments_position (position_id, status),
			CONSTRAINT fk_org_assignments_period FOREIGN KEY (period_id) REFERENCES org_periods (id) ON DELETE CASCADE,
			CONSTRAINT fk_org_assignments_position FOREIGN KEY (position_id) REFERENCES org_positions (id) ON DELETE CASCADE,
			CONSTRAINT fk_org_assignments_person FOREIGN KEY (person_id) REFERENCES people (id) ON DELETE RESTRICT
		) $t");
	}

	public function down()
	{
		$this->db->query('SET FOREIGN_KEY_CHECKS = 0');
		foreach (array('org_assignments', 'org_positions', 'org_units', 'people', 'org_periods') as $table)
		{
			$this->db->query('DROP TABLE IF EXISTS `'.$table.'`');
		}
		if ($this->db->table_exists('cms_publication_snapshots'))
		{
			$this->db->where('target_type', 'organization')->delete('cms_publication_snapshots');
		}
		$this->db->query('SET FOREIGN_KEY_CHECKS = 1');
	}
}
