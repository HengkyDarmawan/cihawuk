<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tahap 8 spesifikasi v1.2 (modul-backend 15, modul-frontend 11): transparansi anggaran.
 *
 * Anggaran murni, anggaran perubahan, dan realisasi disimpan sebagai REVISI TERPISAH di
 * atas satu tahun anggaran, sehingga ketiganya tidak pernah tercampur dalam satu angka.
 * Publikasi memakai `cms_publication_snapshots` dengan `target_type = 'budget'`.
 *
 * status tahun: draft, reconciling, verified, approved, published, locked
 * jenis revisi: original, amended, realization
 */
class Migration_Create_budget_tables extends CI_Migration {

	public function up()
	{
		$t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->db->query("CREATE TABLE budget_years (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			fiscal_year SMALLINT UNSIGNED NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			is_provisional TINYINT(1) NOT NULL DEFAULT 1,
			note VARCHAR(1000) NULL,
			verified_by BIGINT UNSIGNED NULL,
			verified_at DATETIME NULL,
			approved_by BIGINT UNSIGNED NULL,
			approved_at DATETIME NULL,
			locked_at DATETIME NULL,
			published_at DATETIME NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_budget_years_public (public_id),
			UNIQUE KEY uq_budget_years_year (fiscal_year),
			CONSTRAINT fk_budget_years_verifier FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL,
			CONSTRAINT fk_budget_years_approver FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL,
			CONSTRAINT fk_budget_years_author FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE budget_revisions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			budget_year_id BIGINT UNSIGNED NOT NULL,
			revision_type VARCHAR(20) NOT NULL,
			label VARCHAR(160) NOT NULL,
			document_year SMALLINT UNSIGNED NULL,
			document_note VARCHAR(500) NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_budget_revisions_public (public_id),
			UNIQUE KEY uq_budget_revisions_type (budget_year_id, revision_type),
			CONSTRAINT fk_budget_revisions_year FOREIGN KEY (budget_year_id) REFERENCES budget_years (id) ON DELETE CASCADE
		) $t");

		$this->db->query("CREATE TABLE budget_categories (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			budget_year_id BIGINT UNSIGNED NOT NULL,
			parent_id BIGINT UNSIGNED NULL,
			section VARCHAR(20) NOT NULL,
			code VARCHAR(40) NULL,
			name VARCHAR(220) NOT NULL,
			level TINYINT UNSIGNED NOT NULL DEFAULT 1,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_budget_categories_public (public_id),
			KEY idx_budget_categories_year (budget_year_id, section, parent_id),
			CONSTRAINT fk_budget_categories_year FOREIGN KEY (budget_year_id) REFERENCES budget_years (id) ON DELETE CASCADE,
			CONSTRAINT fk_budget_categories_parent FOREIGN KEY (parent_id) REFERENCES budget_categories (id) ON DELETE RESTRICT
		) $t");

		$this->db->query("CREATE TABLE budget_lines (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			revision_id BIGINT UNSIGNED NOT NULL,
			category_id BIGINT UNSIGNED NOT NULL,
			amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			variance_note VARCHAR(500) NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_budget_lines (revision_id, category_id),
			CONSTRAINT fk_budget_lines_revision FOREIGN KEY (revision_id) REFERENCES budget_revisions (id) ON DELETE CASCADE,
			CONSTRAINT fk_budget_lines_category FOREIGN KEY (category_id) REFERENCES budget_categories (id) ON DELETE CASCADE
		) $t");

		$this->db->query("CREATE TABLE budget_projects (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			budget_year_id BIGINT UNSIGNED NOT NULL,
			category_id BIGINT UNSIGNED NULL,
			name VARCHAR(220) NOT NULL,
			location_public VARCHAR(255) NULL,
			target_output VARCHAR(500) NULL,
			outcome_note VARCHAR(500) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'planned',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_budget_projects_public (public_id),
			KEY idx_budget_projects_year (budget_year_id),
			CONSTRAINT fk_budget_projects_year FOREIGN KEY (budget_year_id) REFERENCES budget_years (id) ON DELETE CASCADE,
			CONSTRAINT fk_budget_projects_category FOREIGN KEY (category_id) REFERENCES budget_categories (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE budget_project_progress (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			project_id BIGINT UNSIGNED NOT NULL,
			reported_on DATE NOT NULL,
			physical_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
			stage VARCHAR(20) NOT NULL DEFAULT 'progress',
			note VARCHAR(500) NULL,
			media_id BIGINT UNSIGNED NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_budget_progress_project (project_id, reported_on),
			CONSTRAINT fk_budget_progress_project FOREIGN KEY (project_id) REFERENCES budget_projects (id) ON DELETE CASCADE,
			CONSTRAINT fk_budget_progress_media FOREIGN KEY (media_id) REFERENCES media_assets (id) ON DELETE SET NULL,
			CONSTRAINT fk_budget_progress_author FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE budget_documents (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			budget_year_id BIGINT UNSIGNED NOT NULL,
			title VARCHAR(220) NOT NULL,
			document_year SMALLINT UNSIGNED NULL,
			private_file_id BIGINT UNSIGNED NULL,
			public_document_id BIGINT UNSIGNED NULL,
			is_redacted TINYINT(1) NOT NULL DEFAULT 0,
			note VARCHAR(500) NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_budget_documents_public (public_id),
			KEY idx_budget_documents_year (budget_year_id),
			CONSTRAINT fk_budget_documents_year FOREIGN KEY (budget_year_id) REFERENCES budget_years (id) ON DELETE CASCADE,
			CONSTRAINT fk_budget_documents_private FOREIGN KEY (private_file_id) REFERENCES private_files (id) ON DELETE SET NULL,
			CONSTRAINT fk_budget_documents_public_doc FOREIGN KEY (public_document_id) REFERENCES public_documents (id) ON DELETE SET NULL
		) $t");
	}

	public function down()
	{
		$this->db->query('SET FOREIGN_KEY_CHECKS = 0');
		foreach (array('budget_documents', 'budget_project_progress', 'budget_projects',
			'budget_lines', 'budget_categories', 'budget_revisions', 'budget_years') as $table)
		{
			$this->db->query('DROP TABLE IF EXISTS `'.$table.'`');
		}
		if ($this->db->table_exists('cms_publication_snapshots'))
		{
			$this->db->where('target_type', 'budget')->delete('cms_publication_snapshots');
		}
		$this->db->query('SET FOREIGN_KEY_CHECKS = 1');
	}
}
