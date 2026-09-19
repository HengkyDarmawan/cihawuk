<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tahap 4 spesifikasi v1.2 (modul-backend §11): dataset berversi di atas nilai statistik.
 *
 * Hierarki: dokumen sumber → batch impor → dataset → versi dataset → seri indikator → nilai.
 * Nilai mentah observasi tidak pernah ditimpa; dataset hanya merangkai nilai yang sudah
 * diverifikasi, lalu diterbitkan sebagai snapshot (`cms_publication_snapshots.target_type = 'dataset'`).
 *
 * sensitivity: public, aggregate_only, restricted
 * status: draft, in_review, published, superseded, archived
 */
class Migration_Create_dataset_tables extends CI_Migration {

	public function up()
	{
		$t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->db->query("CREATE TABLE datasets (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			slug VARCHAR(120) NOT NULL,
			name VARCHAR(160) NOT NULL,
			theme VARCHAR(40) NOT NULL,
			description VARCHAR(600) NULL,
			coverage VARCHAR(120) NOT NULL,
			sensitivity VARCHAR(20) NOT NULL DEFAULT 'public',
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			current_version_id BIGINT UNSIGNED NULL,
			published_version_id BIGINT UNSIGNED NULL,
			published_at DATETIME NULL,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			archived_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_datasets_public (public_id),
			UNIQUE KEY uq_datasets_slug (slug),
			KEY idx_datasets_status (status, theme),
			CONSTRAINT fk_datasets_author FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		// validation_status: pending, passed, failed — hasil pemeriksaan otomatis sebelum terbit.
		$this->db->query("CREATE TABLE dataset_versions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			dataset_id BIGINT UNSIGNED NOT NULL,
			version_no INT UNSIGNED NOT NULL,
			period_year SMALLINT UNSIGNED NOT NULL,
			period_label VARCHAR(60) NULL,
			methodology VARCHAR(1000) NULL,
			quality_note VARCHAR(1000) NULL,
			source_id BIGINT UNSIGNED NULL,
			source_note VARCHAR(255) NULL,
			validation_status VARCHAR(20) NOT NULL DEFAULT 'pending',
			validation_report TEXT NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_dataset_versions (dataset_id, version_no),
			KEY idx_dataset_versions_year (period_year),
			CONSTRAINT fk_dataset_versions_dataset FOREIGN KEY (dataset_id) REFERENCES datasets (id) ON DELETE CASCADE,
			CONSTRAINT fk_dataset_versions_source FOREIGN KEY (source_id) REFERENCES source_documents (id) ON DELETE SET NULL,
			CONSTRAINT fk_dataset_versions_author FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE dataset_series (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			dataset_version_id BIGINT UNSIGNED NOT NULL,
			indicator_id BIGINT UNSIGNED NOT NULL,
			display_order INT UNSIGNED NOT NULL DEFAULT 0,
			chart_type VARCHAR(20) NOT NULL DEFAULT 'bar',
			is_composition TINYINT(1) NOT NULL DEFAULT 0,
			note VARCHAR(255) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_dataset_series (dataset_version_id, indicator_id),
			CONSTRAINT fk_dataset_series_version FOREIGN KEY (dataset_version_id) REFERENCES dataset_versions (id) ON DELETE CASCADE,
			CONSTRAINT fk_dataset_series_indicator FOREIGN KEY (indicator_id) REFERENCES statistic_indicators (id) ON DELETE RESTRICT
		) $t");

		$this->db->query("ALTER TABLE datasets
			ADD CONSTRAINT fk_datasets_current FOREIGN KEY (current_version_id) REFERENCES dataset_versions (id) ON DELETE SET NULL,
			ADD CONSTRAINT fk_datasets_published FOREIGN KEY (published_version_id) REFERENCES dataset_versions (id) ON DELETE SET NULL");

		// Nilai statistik dapat menjadi bagian sebuah versi dataset; nilai lama tetap berdiri sendiri.
		$this->db->query("ALTER TABLE statistic_values
			ADD COLUMN dataset_version_id BIGINT UNSIGNED NULL AFTER source_id,
			ADD COLUMN suppressed TINYINT(1) NOT NULL DEFAULT 0 AFTER dataset_version_id,
			ADD CONSTRAINT fk_statistic_values_dataset FOREIGN KEY (dataset_version_id) REFERENCES dataset_versions (id) ON DELETE SET NULL");
	}

	public function down()
	{
		$this->db->query('SET FOREIGN_KEY_CHECKS = 0');
		if ($this->db->field_exists('dataset_version_id', 'statistic_values'))
		{
			$this->db->query('ALTER TABLE statistic_values DROP FOREIGN KEY fk_statistic_values_dataset');
			$this->db->query('ALTER TABLE statistic_values DROP COLUMN dataset_version_id, DROP COLUMN suppressed');
		}
		foreach (array('dataset_series', 'dataset_versions', 'datasets') as $table)
		{
			$this->db->query('DROP TABLE IF EXISTS `'.$table.'`');
		}
		if ($this->db->table_exists('cms_publication_snapshots'))
		{
			$this->db->where('target_type', 'dataset')->delete('cms_publication_snapshots');
		}
		$this->db->query('SET FOREIGN_KEY_CHECKS = 1');
	}
}
