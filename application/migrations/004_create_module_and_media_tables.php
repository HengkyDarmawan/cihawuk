<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tahap 1 spesifikasi v1.2:
 * - feature module beserta histori perubahan state (modul-backend §6),
 * - pelengkap audit log: jejak jaringan ter-hash dan peristiwa operasional (modul-backend §18.5),
 * - pipeline media: original privat, derivative publik dan pencatatan pemakaian (modul-backend §14),
 * - penanda versi preset role agar seed dapat menambah permission baru tanpa menimpa hak yang diberikan pengelola.
 *
 * state modul: disabled, internal_only, public_readonly, active, maintenance.
 */
class Migration_Create_module_and_media_tables extends CI_Migration {

	public function up()
	{
		$t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->db->query("CREATE TABLE feature_modules (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code VARCHAR(50) NOT NULL,
			name VARCHAR(120) NOT NULL,
			description VARCHAR(255) NULL,
			state VARCHAR(20) NOT NULL DEFAULT 'disabled',
			depends_on_json LONGTEXT NULL,
			public_route_prefix VARCHAR(60) NULL,
			admin_route_prefix VARCHAR(60) NULL,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			effective_from DATETIME NULL,
			updated_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_feature_modules_code (code),
			KEY idx_feature_modules_state (state),
			CONSTRAINT fk_feature_modules_actor FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE feature_module_histories (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			module_code VARCHAR(50) NOT NULL,
			from_state VARCHAR(20) NULL,
			to_state VARCHAR(20) NOT NULL,
			reason VARCHAR(500) NOT NULL,
			actor_user_id BIGINT UNSIGNED NULL,
			effective_at DATETIME NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_module_histories_code (module_code, created_at),
			CONSTRAINT fk_module_histories_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		// Peristiwa operasional untuk dashboard maintenance (bukan pengganti audit_logs).
		$this->db->query("CREATE TABLE admin_activity_events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			severity VARCHAR(20) NOT NULL DEFAULT 'info',
			module_code VARCHAR(50) NULL,
			message VARCHAR(255) NOT NULL,
			context_json LONGTEXT NULL,
			actor_user_id BIGINT UNSIGNED NULL,
			occurred_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_activity_module (module_code, occurred_at),
			KEY idx_activity_severity (severity, occurred_at),
			CONSTRAINT fk_activity_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		// Jejak jaringan disimpan sebagai HMAC, bukan IP mentah; user agent hanya ringkasan pendek.
		$this->db->query("ALTER TABLE audit_logs
			ADD COLUMN module_code VARCHAR(50) NULL AFTER action,
			ADD COLUMN ip_hash CHAR(64) NULL AFTER request_id,
			ADD COLUMN user_agent_digest VARCHAR(120) NULL AFTER ip_hash,
			ADD KEY idx_audit_module (module_code, created_at)");

		$this->db->query("ALTER TABLE roles
			ADD COLUMN preset_version INT UNSIGNED NOT NULL DEFAULT 0 AFTER is_system");

		// Media: berkas asli disimpan privat, hanya derivative yang dilayani dari public/media.
		$this->db->query("ALTER TABLE media_assets
			ADD COLUMN private_file_id BIGINT UNSIGNED NULL AFTER storage_key,
			ADD COLUMN caption VARCHAR(500) NULL AFTER alt_text,
			ADD COLUMN source_year SMALLINT UNSIGNED NULL AFTER source_credit,
			ADD COLUMN people_shown VARCHAR(255) NULL AFTER source_year,
			ADD COLUMN verification_status VARCHAR(20) NOT NULL DEFAULT 'unverified' AFTER rights_status,
			MODIFY COLUMN storage_key VARCHAR(120) NULL,
			ADD CONSTRAINT fk_media_assets_private_file FOREIGN KEY (private_file_id) REFERENCES private_files (id) ON DELETE RESTRICT");

		$this->db->query("CREATE TABLE media_derivatives (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			media_asset_id BIGINT UNSIGNED NOT NULL,
			variant VARCHAR(30) NOT NULL,
			storage_key VARCHAR(160) NOT NULL,
			mime_type VARCHAR(100) NOT NULL,
			width INT UNSIGNED NULL,
			height INT UNSIGNED NULL,
			byte_size BIGINT UNSIGNED NOT NULL,
			checksum CHAR(64) NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_media_derivatives (media_asset_id, variant),
			UNIQUE KEY uq_media_derivatives_key (storage_key),
			CONSTRAINT fk_media_derivatives_asset FOREIGN KEY (media_asset_id) REFERENCES media_assets (id) ON DELETE CASCADE
		) $t");

		$this->db->query("CREATE TABLE media_usages (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			media_asset_id BIGINT UNSIGNED NOT NULL,
			object_type VARCHAR(50) NOT NULL,
			object_id VARCHAR(64) NOT NULL,
			field_name VARCHAR(60) NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_media_usages (media_asset_id, object_type, object_id, field_name),
			KEY idx_media_usages_object (object_type, object_id),
			CONSTRAINT fk_media_usages_asset FOREIGN KEY (media_asset_id) REFERENCES media_assets (id) ON DELETE CASCADE
		) $t");
	}

	public function down()
	{
		$this->db->query('SET FOREIGN_KEY_CHECKS = 0');
		foreach (array('media_usages', 'media_derivatives', 'admin_activity_events', 'feature_module_histories', 'feature_modules') as $table)
		{
			$this->db->query('DROP TABLE IF EXISTS `'.$table.'`');
		}
		$this->db->query('SET FOREIGN_KEY_CHECKS = 1');

		$this->db->query("ALTER TABLE media_assets
			DROP FOREIGN KEY fk_media_assets_private_file");
		$this->db->query("ALTER TABLE media_assets
			DROP COLUMN private_file_id,
			DROP COLUMN caption,
			DROP COLUMN source_year,
			DROP COLUMN people_shown,
			DROP COLUMN verification_status,
			MODIFY COLUMN storage_key VARCHAR(120) NOT NULL");

		$this->db->query("ALTER TABLE roles DROP COLUMN preset_version");

		$this->db->query("ALTER TABLE audit_logs
			DROP KEY idx_audit_module,
			DROP COLUMN module_code,
			DROP COLUMN ip_hash,
			DROP COLUMN user_agent_digest");
	}
}
