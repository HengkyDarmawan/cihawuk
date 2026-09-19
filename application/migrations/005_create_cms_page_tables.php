<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tahap 2 spesifikasi v1.2 (modul-backend §18.2 dan §18.3): halaman publik berbasis
 * section registry, versi draft, snapshot publikasi, review, penjadwalan dan redirect.
 *
 * Prinsipnya: frontend membaca snapshot yang diterbitkan, bukan tabel draft.
 * Data bisnis (statistik, potensi, berita) tetap dirujuk lewat ID/preset, tidak disalin ke JSON.
 *
 * publication_status: draft, in_review, changes_requested, approved, scheduled, published, unpublished, archived.
 */
class Migration_Create_cms_page_tables extends CI_Migration {

	public function up()
	{
		$t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->db->query("CREATE TABLE cms_pages (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			page_key VARCHAR(60) NOT NULL,
			parent_id BIGINT UNSIGNED NULL,
			is_system TINYINT(1) NOT NULL DEFAULT 0,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			current_version_id BIGINT UNSIGNED NULL,
			published_version_id BIGINT UNSIGNED NULL,
			published_at DATETIME NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			archived_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_cms_pages_public (public_id),
			UNIQUE KEY uq_cms_pages_key (page_key),
			KEY idx_cms_pages_status (status),
			CONSTRAINT fk_cms_pages_parent FOREIGN KEY (parent_id) REFERENCES cms_pages (id) ON DELETE RESTRICT,
			CONSTRAINT fk_cms_pages_author FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE cms_page_versions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			page_id BIGINT UNSIGNED NOT NULL,
			version_no INT UNSIGNED NOT NULL,
			nav_title VARCHAR(120) NOT NULL,
			title VARCHAR(180) NOT NULL,
			slug VARCHAR(180) NOT NULL,
			summary VARCHAR(500) NULL,
			template_code VARCHAR(40) NOT NULL DEFAULT 'page.standard',
			seo_title VARCHAR(200) NULL,
			seo_description VARCHAR(300) NULL,
			cover_media_id BIGINT UNSIGNED NULL,
			search_indexable TINYINT(1) NOT NULL DEFAULT 1,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_cms_page_versions (page_id, version_no),
			KEY idx_cms_page_versions_slug (slug),
			CONSTRAINT fk_cms_page_versions_page FOREIGN KEY (page_id) REFERENCES cms_pages (id) ON DELETE CASCADE,
			CONSTRAINT fk_cms_page_versions_cover FOREIGN KEY (cover_media_id) REFERENCES media_assets (id) ON DELETE RESTRICT,
			CONSTRAINT fk_cms_page_versions_author FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE cms_sections (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			page_id BIGINT UNSIGNED NOT NULL,
			section_type VARCHAR(40) NOT NULL,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			is_enabled TINYINT(1) NOT NULL DEFAULT 1,
			starts_at DATETIME NULL,
			ends_at DATETIME NULL,
			current_version_id BIGINT UNSIGNED NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			archived_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_cms_sections_public (public_id),
			KEY idx_cms_sections_page (page_id, sort_order),
			CONSTRAINT fk_cms_sections_page FOREIGN KEY (page_id) REFERENCES cms_pages (id) ON DELETE CASCADE,
			CONSTRAINT fk_cms_sections_author FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE cms_section_versions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			section_id BIGINT UNSIGNED NOT NULL,
			version_no INT UNSIGNED NOT NULL,
			title VARCHAR(180) NULL,
			subtitle VARCHAR(300) NULL,
			layout_variant VARCHAR(40) NOT NULL,
			config_json LONGTEXT NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_cms_section_versions (section_id, version_no),
			CONSTRAINT fk_cms_section_versions_section FOREIGN KEY (section_id) REFERENCES cms_sections (id) ON DELETE CASCADE,
			CONSTRAINT fk_cms_section_versions_author FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		// Snapshot immutable: frontend membaca baris ini, bukan tabel draft.
		$this->db->query("CREATE TABLE cms_publication_snapshots (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			target_type VARCHAR(30) NOT NULL DEFAULT 'page',
			target_id BIGINT UNSIGNED NOT NULL,
			page_version_id BIGINT UNSIGNED NULL,
			revision_no INT UNSIGNED NOT NULL,
			snapshot_json LONGTEXT NOT NULL,
			reason VARCHAR(500) NULL,
			rolled_back_from BIGINT UNSIGNED NULL,
			published_by BIGINT UNSIGNED NULL,
			published_at DATETIME NOT NULL,
			superseded_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_cms_snapshots (target_type, target_id, revision_no),
			KEY idx_cms_snapshots_active (target_type, target_id, superseded_at),
			CONSTRAINT fk_cms_snapshots_version FOREIGN KEY (page_version_id) REFERENCES cms_page_versions (id) ON DELETE SET NULL,
			CONSTRAINT fk_cms_snapshots_actor FOREIGN KEY (published_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE cms_redirects (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			old_path VARCHAR(191) NOT NULL,
			new_path VARCHAR(191) NOT NULL,
			status_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
			source VARCHAR(30) NOT NULL DEFAULT 'cms_page',
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_cms_redirects_old (old_path),
			KEY idx_cms_redirects_active (active)
		) $t");

		$this->db->query("CREATE TABLE content_review_requests (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			object_type VARCHAR(30) NOT NULL,
			object_id BIGINT UNSIGNED NOT NULL,
			version_no INT UNSIGNED NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			submitted_by BIGINT UNSIGNED NULL,
			submitted_at DATETIME NOT NULL,
			reviewed_by BIGINT UNSIGNED NULL,
			reviewed_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY idx_review_object (object_type, object_id, status),
			CONSTRAINT fk_review_submitter FOREIGN KEY (submitted_by) REFERENCES users (id) ON DELETE SET NULL,
			CONSTRAINT fk_review_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE content_review_comments (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			request_id BIGINT UNSIGNED NOT NULL,
			field_path VARCHAR(120) NULL,
			comment VARCHAR(1000) NOT NULL,
			resolution VARCHAR(20) NOT NULL DEFAULT 'open',
			actor_user_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_review_comments_request (request_id, created_at),
			CONSTRAINT fk_review_comments_request FOREIGN KEY (request_id) REFERENCES content_review_requests (id) ON DELETE CASCADE,
			CONSTRAINT fk_review_comments_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE scheduled_publications (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			object_type VARCHAR(30) NOT NULL,
			object_id BIGINT UNSIGNED NOT NULL,
			action VARCHAR(20) NOT NULL DEFAULT 'publish',
			run_at DATETIME NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			idempotency_key CHAR(64) NOT NULL,
			reason VARCHAR(500) NULL,
			requested_by BIGINT UNSIGNED NULL,
			executed_at DATETIME NULL,
			last_error VARCHAR(255) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_scheduled_publications (idempotency_key),
			KEY idx_scheduled_due (status, run_at),
			CONSTRAINT fk_scheduled_actor FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("ALTER TABLE cms_pages
			ADD CONSTRAINT fk_cms_pages_current FOREIGN KEY (current_version_id) REFERENCES cms_page_versions (id) ON DELETE SET NULL,
			ADD CONSTRAINT fk_cms_pages_published FOREIGN KEY (published_version_id) REFERENCES cms_page_versions (id) ON DELETE SET NULL");
		$this->db->query("ALTER TABLE cms_sections
			ADD CONSTRAINT fk_cms_sections_current FOREIGN KEY (current_version_id) REFERENCES cms_section_versions (id) ON DELETE SET NULL");
	}

	public function down()
	{
		$this->db->query('SET FOREIGN_KEY_CHECKS = 0');
		foreach (array('scheduled_publications', 'content_review_comments', 'content_review_requests', 'cms_redirects',
			'cms_publication_snapshots', 'cms_section_versions', 'cms_sections', 'cms_page_versions', 'cms_pages') as $table)
		{
			$this->db->query('DROP TABLE IF EXISTS `'.$table.'`');
		}
		$this->db->query('SET FOREIGN_KEY_CHECKS = 1');
	}
}
