<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CMS, media, peta, register dokumen sumber, observasi, data issue dan statistik.
 * publication_status: draft, in_review, published, archived.
 * verification_status: unverified, pending, verified, rejected.
 */
class Migration_Create_content_and_source_tables extends CI_Migration {

	public function up()
	{
		$t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		// rights_status: unknown, owned, licensed, permission_granted, rejected
		$this->db->query("CREATE TABLE media_assets (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			storage_key VARCHAR(120) NOT NULL,
			original_name VARCHAR(255) NOT NULL,
			mime_type VARCHAR(100) NOT NULL,
			byte_size BIGINT UNSIGNED NOT NULL,
			width INT UNSIGNED NULL,
			height INT UNSIGNED NULL,
			checksum CHAR(64) NOT NULL,
			alt_text VARCHAR(255) NOT NULL DEFAULT '',
			source_credit VARCHAR(255) NULL,
			license_note VARCHAR(255) NULL,
			rights_status VARCHAR(30) NOT NULL DEFAULT 'unknown',
			is_placeholder TINYINT(1) NOT NULL DEFAULT 0,
			uploaded_by BIGINT UNSIGNED NULL,
			publication_status VARCHAR(20) NOT NULL DEFAULT 'draft',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_media_assets_key (storage_key),
			KEY idx_media_assets_status (publication_status, rights_status),
			CONSTRAINT fk_media_assets_uploader FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE content_categories (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			content_type VARCHAR(30) NOT NULL,
			name VARCHAR(100) NOT NULL,
			slug VARCHAR(120) NOT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_content_categories (content_type, slug)
		) $t");

		$this->db->query("CREATE TABLE source_documents (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source_code VARCHAR(10) NOT NULL,
			original_filename VARCHAR(255) NOT NULL,
			title VARCHAR(255) NOT NULL,
			source_year SMALLINT UNSIGNED NULL,
			usage_note VARCHAR(500) NULL,
			checksum CHAR(64) NULL,
			private_file_id BIGINT UNSIGNED NULL,
			import_status VARCHAR(30) NOT NULL DEFAULT 'not_imported',
			imported_by BIGINT UNSIGNED NULL,
			imported_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_source_documents_code (source_code),
			UNIQUE KEY uq_source_documents_checksum (checksum),
			CONSTRAINT fk_source_documents_file FOREIGN KEY (private_file_id) REFERENCES private_files (id) ON DELETE SET NULL,
			CONSTRAINT fk_source_documents_importer FOREIGN KEY (imported_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE posts (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			type VARCHAR(20) NOT NULL,
			category_id BIGINT UNSIGNED NULL,
			title VARCHAR(200) NOT NULL,
			slug VARCHAR(220) NOT NULL,
			excerpt VARCHAR(500) NULL,
			body_html LONGTEXT NOT NULL,
			cover_media_id BIGINT UNSIGNED NULL,
			author_id BIGINT UNSIGNED NULL,
			reviewer_id BIGINT UNSIGNED NULL,
			is_featured TINYINT(1) NOT NULL DEFAULT 0,
			publication_status VARCHAR(20) NOT NULL DEFAULT 'draft',
			publish_at DATETIME NULL,
			published_at DATETIME NULL,
			source_id BIGINT UNSIGNED NULL,
			seo_title VARCHAR(200) NULL,
			seo_description VARCHAR(300) NULL,
			version_no INT UNSIGNED NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_posts_type_slug (type, slug),
			KEY idx_posts_public (type, publication_status, published_at),
			KEY idx_posts_category (category_id),
			FULLTEXT KEY ft_posts_search (title, excerpt),
			CONSTRAINT fk_posts_category FOREIGN KEY (category_id) REFERENCES content_categories (id) ON DELETE SET NULL,
			CONSTRAINT fk_posts_cover FOREIGN KEY (cover_media_id) REFERENCES media_assets (id) ON DELETE RESTRICT,
			CONSTRAINT fk_posts_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL,
			CONSTRAINT fk_posts_reviewer FOREIGN KEY (reviewer_id) REFERENCES users (id) ON DELETE SET NULL,
			CONSTRAINT fk_posts_source FOREIGN KEY (source_id) REFERENCES source_documents (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE post_revisions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			version_no INT UNSIGNED NOT NULL,
			snapshot_json LONGTEXT NOT NULL,
			edited_by BIGINT UNSIGNED NULL,
			reason VARCHAR(255) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_post_revisions (post_id, version_no),
			CONSTRAINT fk_post_revisions_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE,
			CONSTRAINT fk_post_revisions_editor FOREIGN KEY (edited_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		// Pengalihan slug lama (301) untuk semua jenis konten publik.
		$this->db->query("CREATE TABLE slug_redirects (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			content_type VARCHAR(30) NOT NULL,
			old_slug VARCHAR(220) NOT NULL,
			new_slug VARCHAR(220) NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_slug_redirects (content_type, old_slug)
		) $t");

		// type: office, potential, facility, boundary
		$this->db->query("CREATE TABLE map_features (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			type VARCHAR(20) NOT NULL,
			title VARCHAR(200) NOT NULL,
			geometry_json LONGTEXT NOT NULL,
			source_note VARCHAR(255) NULL,
			source_id BIGINT UNSIGNED NULL,
			source_date DATE NULL,
			is_demo TINYINT(1) NOT NULL DEFAULT 0,
			verification_status VARCHAR(20) NOT NULL DEFAULT 'unverified',
			publication_status VARCHAR(20) NOT NULL DEFAULT 'draft',
			reviewed_by BIGINT UNSIGNED NULL,
			reviewed_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_map_features_public (type, publication_status, verification_status),
			CONSTRAINT fk_map_features_source FOREIGN KEY (source_id) REFERENCES source_documents (id) ON DELETE SET NULL,
			CONSTRAINT fk_map_features_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE potentials (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			category_id BIGINT UNSIGNED NOT NULL,
			title VARCHAR(200) NOT NULL,
			slug VARCHAR(220) NOT NULL,
			summary VARCHAR(500) NOT NULL,
			body_html LONGTEXT NOT NULL,
			cover_media_id BIGINT UNSIGNED NULL,
			map_feature_id BIGINT UNSIGNED NULL,
			public_contact VARCHAR(255) NULL,
			contact_permission TINYINT(1) NOT NULL DEFAULT 0,
			source_id BIGINT UNSIGNED NULL,
			source_year SMALLINT UNSIGNED NULL,
			verification_status VARCHAR(20) NOT NULL DEFAULT 'unverified',
			publication_status VARCHAR(20) NOT NULL DEFAULT 'draft',
			sort_order INT NOT NULL DEFAULT 0,
			author_id BIGINT UNSIGNED NULL,
			published_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_potentials_slug (slug),
			KEY idx_potentials_public (publication_status, category_id),
			CONSTRAINT fk_potentials_category FOREIGN KEY (category_id) REFERENCES content_categories (id) ON DELETE RESTRICT,
			CONSTRAINT fk_potentials_cover FOREIGN KEY (cover_media_id) REFERENCES media_assets (id) ON DELETE RESTRICT,
			CONSTRAINT fk_potentials_map FOREIGN KEY (map_feature_id) REFERENCES map_features (id) ON DELETE SET NULL,
			CONSTRAINT fk_potentials_source FOREIGN KEY (source_id) REFERENCES source_documents (id) ON DELETE SET NULL,
			CONSTRAINT fk_potentials_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE potential_media (
			potential_id BIGINT UNSIGNED NOT NULL,
			media_asset_id BIGINT UNSIGNED NOT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY (potential_id, media_asset_id),
			CONSTRAINT fk_potential_media_potential FOREIGN KEY (potential_id) REFERENCES potentials (id) ON DELETE CASCADE,
			CONSTRAINT fk_potential_media_media FOREIGN KEY (media_asset_id) REFERENCES media_assets (id) ON DELETE RESTRICT
		) $t");

		$this->db->query("CREATE TABLE events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(200) NOT NULL,
			slug VARCHAR(220) NOT NULL,
			summary VARCHAR(500) NULL,
			description_html LONGTEXT NULL,
			starts_at DATETIME NOT NULL,
			ends_at DATETIME NULL,
			location_text VARCHAR(255) NULL,
			poster_media_id BIGINT UNSIGNED NULL,
			organizer VARCHAR(150) NULL,
			publication_status VARCHAR(20) NOT NULL DEFAULT 'draft',
			author_id BIGINT UNSIGNED NULL,
			published_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_events_slug (slug),
			KEY idx_events_public (publication_status, starts_at),
			CONSTRAINT fk_events_poster FOREIGN KEY (poster_media_id) REFERENCES media_assets (id) ON DELETE RESTRICT,
			CONSTRAINT fk_events_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE galleries (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(200) NOT NULL,
			slug VARCHAR(220) NOT NULL,
			description VARCHAR(1000) NULL,
			publication_status VARCHAR(20) NOT NULL DEFAULT 'draft',
			published_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_galleries_slug (slug)
		) $t");

		$this->db->query("CREATE TABLE gallery_items (
			gallery_id BIGINT UNSIGNED NOT NULL,
			media_asset_id BIGINT UNSIGNED NOT NULL,
			caption VARCHAR(255) NULL,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY (gallery_id, media_asset_id),
			CONSTRAINT fk_gallery_items_gallery FOREIGN KEY (gallery_id) REFERENCES galleries (id) ON DELETE CASCADE,
			CONSTRAINT fk_gallery_items_media FOREIGN KEY (media_asset_id) REFERENCES media_assets (id) ON DELETE RESTRICT
		) $t");

		$this->db->query("CREATE TABLE public_documents (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(200) NOT NULL,
			category_id BIGINT UNSIGNED NULL,
			source_year SMALLINT UNSIGNED NULL,
			description VARCHAR(500) NULL,
			media_asset_id BIGINT UNSIGNED NOT NULL,
			version_label VARCHAR(50) NOT NULL,
			source_id BIGINT UNSIGNED NULL,
			publication_status VARCHAR(20) NOT NULL DEFAULT 'draft',
			approved_by BIGINT UNSIGNED NULL,
			published_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY idx_public_documents_public (publication_status, source_year),
			CONSTRAINT fk_public_documents_category FOREIGN KEY (category_id) REFERENCES content_categories (id) ON DELETE SET NULL,
			CONSTRAINT fk_public_documents_media FOREIGN KEY (media_asset_id) REFERENCES media_assets (id) ON DELETE RESTRICT,
			CONSTRAINT fk_public_documents_source FOREIGN KEY (source_id) REFERENCES source_documents (id) ON DELETE SET NULL,
			CONSTRAINT fk_public_documents_approver FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE navigation_items (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			menu_key VARCHAR(30) NOT NULL,
			parent_id BIGINT UNSIGNED NULL,
			label VARCHAR(80) NOT NULL,
			target_url VARCHAR(500) NOT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_navigation_items_menu (menu_key, parent_id, sort_order),
			CONSTRAINT fk_navigation_items_parent FOREIGN KEY (parent_id) REFERENCES navigation_items (id) ON DELETE CASCADE
		) $t");

		// animation_mode: image, video, three
		$this->db->query("CREATE TABLE hero_slides (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(200) NOT NULL,
			subtitle VARCHAR(300) NULL,
			image_media_id BIGINT UNSIGNED NULL,
			video_media_id BIGINT UNSIGNED NULL,
			cta_primary_json LONGTEXT NULL,
			cta_secondary_json LONGTEXT NULL,
			animation_mode VARCHAR(10) NOT NULL DEFAULT 'three',
			sort_order INT NOT NULL DEFAULT 0,
			active TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			CONSTRAINT fk_hero_slides_image FOREIGN KEY (image_media_id) REFERENCES media_assets (id) ON DELETE RESTRICT,
			CONSTRAINT fk_hero_slides_video FOREIGN KEY (video_media_id) REFERENCES media_assets (id) ON DELETE RESTRICT
		) $t");

		$this->db->query("CREATE TABLE village_profiles (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			village_name VARCHAR(100) NOT NULL,
			district VARCHAR(100) NOT NULL,
			regency VARCHAR(100) NOT NULL,
			province VARCHAR(100) NOT NULL,
			pum_code VARCHAR(30) NULL,
			summary TEXT NULL,
			history_html LONGTEXT NULL,
			vision_official TEXT NULL,
			vision_summary TEXT NULL,
			mission_json LONGTEXT NULL,
			greeting_html LONGTEXT NULL,
			office_address VARCHAR(255) NULL,
			contacts_json LONGTEXT NULL,
			service_hours_json LONGTEXT NULL,
			official_links_json LONGTEXT NULL,
			logo_media_id BIGINT UNSIGNED NULL,
			profile_media_id BIGINT UNSIGNED NULL,
			seo_description VARCHAR(300) NULL,
			source_year SMALLINT UNSIGNED NULL,
			source_note VARCHAR(500) NULL,
			review_note TEXT NULL,
			publication_status VARCHAR(20) NOT NULL DEFAULT 'draft',
			published_at DATETIME NULL,
			updated_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			CONSTRAINT fk_village_profiles_logo FOREIGN KEY (logo_media_id) REFERENCES media_assets (id) ON DELETE RESTRICT,
			CONSTRAINT fk_village_profiles_photo FOREIGN KEY (profile_media_id) REFERENCES media_assets (id) ON DELETE RESTRICT,
			CONSTRAINT fk_village_profiles_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE official_positions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(150) NOT NULL,
			parent_id BIGINT UNSIGNED NULL,
			unit_id BIGINT UNSIGNED NULL,
			duties_summary TEXT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			publication_status VARCHAR(20) NOT NULL DEFAULT 'draft',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_official_positions_parent (parent_id),
			CONSTRAINT fk_official_positions_parent FOREIGN KEY (parent_id) REFERENCES official_positions (id) ON DELETE RESTRICT,
			CONSTRAINT fk_official_positions_unit FOREIGN KEY (unit_id) REFERENCES organizational_units (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE officials (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			position_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(150) NOT NULL,
			photo_media_id BIGINT UNSIGNED NULL,
			term_start DATE NULL,
			term_end DATE NULL,
			source_id BIGINT UNSIGNED NULL,
			source_note VARCHAR(255) NULL,
			verification_status VARCHAR(20) NOT NULL DEFAULT 'unverified',
			publication_status VARCHAR(20) NOT NULL DEFAULT 'draft',
			sort_order INT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_officials_position (position_id),
			CONSTRAINT fk_officials_position FOREIGN KEY (position_id) REFERENCES official_positions (id) ON DELETE RESTRICT,
			CONSTRAINT fk_officials_photo FOREIGN KEY (photo_media_id) REFERENCES media_assets (id) ON DELETE RESTRICT,
			CONSTRAINT fk_officials_source FOREIGN KEY (source_id) REFERENCES source_documents (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE import_batches (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(20) NOT NULL,
			row_count INT UNSIGNED NOT NULL DEFAULT 0,
			accepted_count INT UNSIGNED NOT NULL DEFAULT 0,
			conflict_count INT UNSIGNED NOT NULL DEFAULT 0,
			empty_count INT UNSIGNED NOT NULL DEFAULT 0,
			parser_version VARCHAR(30) NOT NULL,
			error_message VARCHAR(500) NULL,
			started_by BIGINT UNSIGNED NULL,
			started_at DATETIME NOT NULL,
			completed_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY idx_import_batches_source (source_id),
			CONSTRAINT fk_import_batches_source FOREIGN KEY (source_id) REFERENCES source_documents (id) ON DELETE RESTRICT,
			CONSTRAINT fk_import_batches_started_by FOREIGN KEY (started_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		// validation_status: pending, accepted, rejected, corrected, conflict
		$this->db->query("CREATE TABLE source_observations (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source_id BIGINT UNSIGNED NOT NULL,
			batch_id BIGINT UNSIGNED NULL,
			field_key VARCHAR(120) NOT NULL,
			field_label VARCHAR(255) NULL,
			source_locator VARCHAR(191) NOT NULL,
			raw_value TEXT NULL,
			normalized_value VARCHAR(255) NULL,
			value_type VARCHAR(20) NOT NULL,
			unit VARCHAR(30) NULL,
			source_year SMALLINT UNSIGNED NULL,
			year_label VARCHAR(100) NULL,
			validation_status VARCHAR(20) NOT NULL DEFAULT 'pending',
			corrected_value VARCHAR(255) NULL,
			reviewer_id BIGINT UNSIGNED NULL,
			reviewed_at DATETIME NULL,
			review_note VARCHAR(500) NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_source_observations (source_id, source_locator, field_key),
			KEY idx_source_observations_field (field_key, source_year),
			KEY idx_source_observations_status (validation_status),
			CONSTRAINT fk_source_observations_source FOREIGN KEY (source_id) REFERENCES source_documents (id) ON DELETE RESTRICT,
			CONSTRAINT fk_source_observations_batch FOREIGN KEY (batch_id) REFERENCES import_batches (id) ON DELETE SET NULL,
			CONSTRAINT fk_source_observations_reviewer FOREIGN KEY (reviewer_id) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE data_issues (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			observation_id BIGINT UNSIGNED NULL,
			source_id BIGINT UNSIGNED NULL,
			issue_code VARCHAR(60) NOT NULL,
			title VARCHAR(200) NOT NULL,
			description TEXT NOT NULL,
			system_decision TEXT NULL,
			severity VARCHAR(20) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			resolution_note TEXT NULL,
			resolved_by BIGINT UNSIGNED NULL,
			resolved_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_data_issues_code (issue_code),
			KEY idx_data_issues_status (status),
			CONSTRAINT fk_data_issues_observation FOREIGN KEY (observation_id) REFERENCES source_observations (id) ON DELETE SET NULL,
			CONSTRAINT fk_data_issues_source FOREIGN KEY (source_id) REFERENCES source_documents (id) ON DELETE SET NULL,
			CONSTRAINT fk_data_issues_resolved_by FOREIGN KEY (resolved_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE statistic_indicators (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code VARCHAR(80) NOT NULL,
			label VARCHAR(200) NOT NULL,
			group_code VARCHAR(50) NOT NULL,
			unit VARCHAR(30) NOT NULL,
			value_type VARCHAR(20) NOT NULL,
			composition_group VARCHAR(50) NULL,
			chart_type VARCHAR(20) NOT NULL DEFAULT 'bar',
			definition VARCHAR(500) NOT NULL,
			display_order INT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_statistic_indicators_code (code),
			KEY idx_statistic_indicators_group (group_code, display_order)
		) $t");

		// area_id wajib (root desa sebagai wilayah) sehingga unique indikator/tahun/area null-safe.
		$this->db->query("CREATE TABLE statistic_values (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			indicator_id BIGINT UNSIGNED NOT NULL,
			source_year SMALLINT UNSIGNED NOT NULL,
			year_label VARCHAR(100) NULL,
			area_id BIGINT UNSIGNED NOT NULL,
			numeric_value DECIMAL(16,2) NULL,
			text_value VARCHAR(255) NULL,
			canonical_observation_id BIGINT UNSIGNED NULL,
			source_id BIGINT UNSIGNED NULL,
			verification_status VARCHAR(20) NOT NULL DEFAULT 'pending',
			publication_status VARCHAR(20) NOT NULL DEFAULT 'draft',
			reviewed_by BIGINT UNSIGNED NULL,
			reviewed_at DATETIME NULL,
			published_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_statistic_values (indicator_id, source_year, area_id),
			KEY idx_statistic_values_public (publication_status, verification_status, source_year),
			CONSTRAINT fk_statistic_values_indicator FOREIGN KEY (indicator_id) REFERENCES statistic_indicators (id) ON DELETE RESTRICT,
			CONSTRAINT fk_statistic_values_area FOREIGN KEY (area_id) REFERENCES administrative_areas (id) ON DELETE RESTRICT,
			CONSTRAINT fk_statistic_values_observation FOREIGN KEY (canonical_observation_id) REFERENCES source_observations (id) ON DELETE RESTRICT,
			CONSTRAINT fk_statistic_values_source FOREIGN KEY (source_id) REFERENCES source_documents (id) ON DELETE SET NULL,
			CONSTRAINT fk_statistic_values_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");
	}

	public function down()
	{
		$this->db->query('SET FOREIGN_KEY_CHECKS = 0');
		foreach (array('statistic_values', 'statistic_indicators', 'data_issues', 'source_observations', 'import_batches',
			'officials', 'official_positions', 'village_profiles', 'hero_slides', 'navigation_items', 'public_documents',
			'gallery_items', 'galleries', 'events', 'potential_media', 'potentials', 'map_features', 'slug_redirects',
			'post_revisions', 'posts', 'source_documents', 'content_categories', 'media_assets') as $table)
		{
			$this->db->query('DROP TABLE IF EXISTS `'.$table.'`');
		}
		$this->db->query('SET FOREIGN_KEY_CHECKS = 1');
	}
}
