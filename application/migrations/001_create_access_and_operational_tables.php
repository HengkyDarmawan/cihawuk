<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Akun, akses, sesi, audit, dan tabel operasional dasar.
 * Target: MariaDB 10.4 / MySQL 8 dengan InnoDB utf8mb4. JSON disimpan LONGTEXT
 * dan divalidasi aplikasi. Semua waktu DATETIME dalam UTC.
 */
class Migration_Create_access_and_operational_tables extends CI_Migration {

	public function up()
	{
		$t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->db->query("CREATE TABLE users (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			username VARCHAR(50) NOT NULL,
			display_name VARCHAR(100) NOT NULL,
			email VARCHAR(191) NULL,
			phone VARCHAR(20) NULL,
			password_hash VARCHAR(255) NULL,
			account_status VARCHAR(30) NOT NULL DEFAULT 'pending_activation',
			email_verified_at DATETIME NULL,
			phone_verified_at DATETIME NULL,
			must_change_password TINYINT(1) NOT NULL DEFAULT 0,
			auth_version INT UNSIGNED NOT NULL DEFAULT 1,
			last_login_at DATETIME NULL,
			registration_channel VARCHAR(30) NOT NULL DEFAULT 'self',
			created_by BIGINT UNSIGNED NULL,
			status_reason VARCHAR(255) NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_users_public_id (public_id),
			UNIQUE KEY uq_users_username (username),
			UNIQUE KEY uq_users_email (email),
			KEY idx_users_status (account_status),
			CONSTRAINT fk_users_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE roles (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code VARCHAR(50) NOT NULL,
			name VARCHAR(100) NOT NULL,
			description VARCHAR(255) NULL,
			is_system TINYINT(1) NOT NULL DEFAULT 0,
			is_staff TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_roles_code (code)
		) $t");

		$this->db->query("CREATE TABLE permissions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code VARCHAR(60) NOT NULL,
			description VARCHAR(255) NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_permissions_code (code)
		) $t");

		$this->db->query("CREATE TABLE user_roles (
			user_id BIGINT UNSIGNED NOT NULL,
			role_id BIGINT UNSIGNED NOT NULL,
			assigned_by BIGINT UNSIGNED NULL,
			assigned_at DATETIME NOT NULL,
			PRIMARY KEY (user_id, role_id),
			KEY idx_user_roles_role (role_id),
			CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
			CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT,
			CONSTRAINT fk_user_roles_assigned_by FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE role_permissions (
			role_id BIGINT UNSIGNED NOT NULL,
			permission_id BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY (role_id, permission_id),
			KEY idx_role_permissions_perm (permission_id),
			CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
			CONSTRAINT fk_role_permissions_perm FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
		) $t");

		$this->db->query("CREATE TABLE organizational_units (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code VARCHAR(50) NOT NULL,
			name VARCHAR(150) NOT NULL,
			parent_id BIGINT UNSIGNED NULL,
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_units_code (code),
			KEY idx_units_parent (parent_id),
			CONSTRAINT fk_units_parent FOREIGN KEY (parent_id) REFERENCES organizational_units (id) ON DELETE RESTRICT
		) $t");

		// scope_type: member (anggota unit), monitor (memantau unit), all (seluruh unit)
		$this->db->query("CREATE TABLE user_unit_scopes (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			unit_id BIGINT UNSIGNED NOT NULL,
			scope_type VARCHAR(20) NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_user_unit_scope (user_id, unit_id, scope_type),
			KEY idx_user_unit_scopes_unit (unit_id),
			CONSTRAINT fk_uus_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
			CONSTRAINT fk_uus_unit FOREIGN KEY (unit_id) REFERENCES organizational_units (id) ON DELETE RESTRICT
		) $t");

		// Dusun/RW/RT. Nama tidak boleh direka; status verifikasi wajib.
		$this->db->query("CREATE TABLE administrative_areas (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			type VARCHAR(20) NOT NULL,
			code VARCHAR(50) NULL,
			name VARCHAR(150) NOT NULL,
			parent_id BIGINT UNSIGNED NULL,
			verification_status VARCHAR(20) NOT NULL DEFAULT 'unverified',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_admin_areas_code (code),
			KEY idx_admin_areas_parent (parent_id),
			CONSTRAINT fk_admin_areas_parent FOREIGN KEY (parent_id) REFERENCES administrative_areas (id) ON DELETE RESTRICT
		) $t");

		$this->db->query("CREATE TABLE resident_profiles (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			display_name VARCHAR(100) NOT NULL,
			address VARCHAR(255) NULL,
			hamlet_id BIGINT UNSIGNED NULL,
			rt VARCHAR(5) NULL,
			rw VARCHAR(5) NULL,
			verification_status VARCHAR(20) NOT NULL DEFAULT 'unverified',
			verified_by BIGINT UNSIGNED NULL,
			verified_at DATETIME NULL,
			review_reason VARCHAR(255) NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_resident_profiles_user (user_id),
			KEY idx_resident_profiles_status (verification_status),
			CONSTRAINT fk_resident_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
			CONSTRAINT fk_resident_profiles_hamlet FOREIGN KEY (hamlet_id) REFERENCES administrative_areas (id) ON DELETE SET NULL,
			CONSTRAINT fk_resident_profiles_verified_by FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		// purpose: activation, reset, email_verify
		$this->db->query("CREATE TABLE account_tokens (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			purpose VARCHAR(30) NOT NULL,
			selector CHAR(16) NOT NULL,
			token_hash CHAR(64) NOT NULL,
			delivery VARCHAR(20) NOT NULL DEFAULT 'email',
			expires_at DATETIME NOT NULL,
			used_at DATETIME NULL,
			revoked_at DATETIME NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_account_tokens_selector (selector),
			KEY idx_account_tokens_user_purpose (user_id, purpose),
			KEY idx_account_tokens_expires (expires_at),
			CONSTRAINT fk_account_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
			CONSTRAINT fk_account_tokens_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE user_mfa (
			user_id BIGINT UNSIGNED NOT NULL,
			secret_ciphertext TEXT NOT NULL,
			key_version INT UNSIGNED NOT NULL,
			enabled_at DATETIME NULL,
			last_used_step BIGINT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (user_id),
			CONSTRAINT fk_user_mfa_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
		) $t");

		$this->db->query("CREATE TABLE mfa_recovery_codes (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			code_hash CHAR(64) NOT NULL,
			used_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_mfa_recovery_hash (code_hash),
			KEY idx_mfa_recovery_user (user_id),
			CONSTRAINT fk_mfa_recovery_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
		) $t");

		// Skema driver sesi database CI 3.1.13 (sess_match_ip = FALSE).
		$this->db->query("CREATE TABLE ci_sessions (
			id VARCHAR(128) NOT NULL,
			ip_address VARCHAR(45) NOT NULL,
			timestamp INT UNSIGNED NOT NULL DEFAULT 0,
			data BLOB NOT NULL,
			PRIMARY KEY (id),
			KEY idx_ci_sessions_timestamp (timestamp)
		) $t");

		$this->db->query("CREATE TABLE user_sessions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			session_fingerprint CHAR(64) NOT NULL,
			auth_version INT UNSIGNED NOT NULL,
			area VARCHAR(20) NOT NULL,
			device_label VARCHAR(150) NULL,
			created_at DATETIME NOT NULL,
			last_seen_at DATETIME NOT NULL,
			expires_at DATETIME NOT NULL,
			revoked_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_user_sessions_fp (session_fingerprint),
			KEY idx_user_sessions_user (user_id, revoked_at),
			CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
		) $t");

		$this->db->query("CREATE TABLE remember_tokens (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			selector CHAR(16) NOT NULL,
			validator_hash CHAR(64) NOT NULL,
			created_at DATETIME NOT NULL,
			expires_at DATETIME NOT NULL,
			revoked_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_remember_selector (selector),
			KEY idx_remember_user (user_id),
			CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
		) $t");

		$this->db->query("CREATE TABLE rate_limits (
			bucket_key CHAR(64) NOT NULL,
			window_start DATETIME NOT NULL,
			hits INT UNSIGNED NOT NULL DEFAULT 0,
			blocked_until DATETIME NULL,
			expires_at DATETIME NOT NULL,
			PRIMARY KEY (bucket_key),
			KEY idx_rate_limits_expires (expires_at)
		) $t");

		$this->db->query("CREATE TABLE audit_logs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			actor_user_id BIGINT UNSIGNED NULL,
			actor_label VARCHAR(100) NULL,
			action VARCHAR(80) NOT NULL,
			entity_type VARCHAR(50) NULL,
			entity_id VARCHAR(64) NULL,
			safe_metadata_json LONGTEXT NULL,
			request_id CHAR(32) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_audit_actor (actor_user_id, created_at),
			KEY idx_audit_entity (entity_type, entity_id),
			KEY idx_audit_action (action, created_at),
			CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE app_settings (
			`key` VARCHAR(100) NOT NULL,
			value_json LONGTEXT NOT NULL,
			group_code VARCHAR(50) NOT NULL,
			is_public TINYINT(1) NOT NULL DEFAULT 0,
			updated_by BIGINT UNSIGNED NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (`key`),
			KEY idx_app_settings_group (group_code),
			CONSTRAINT fk_app_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE job_locks (
			job_key VARCHAR(100) NOT NULL,
			locked_until DATETIME NOT NULL,
			owner_token CHAR(32) NOT NULL,
			PRIMARY KEY (job_key)
		) $t");

		$this->db->query("CREATE TABLE idempotency_keys (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			actor_scope_hash CHAR(64) NOT NULL,
			action VARCHAR(50) NOT NULL,
			key_hash CHAR(64) NOT NULL,
			request_hash CHAR(64) NOT NULL,
			result_type VARCHAR(50) NULL,
			result_id BIGINT UNSIGNED NULL,
			state VARCHAR(20) NOT NULL,
			created_at DATETIME NOT NULL,
			expires_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_idempotency (actor_scope_hash, action, key_hash),
			KEY idx_idempotency_expires (expires_at)
		) $t");

		// scan_status: not_scanned, quarantined, clean, infected
		$this->db->query("CREATE TABLE private_files (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			storage_key VARCHAR(120) NOT NULL,
			original_name VARCHAR(255) NOT NULL,
			mime_type VARCHAR(100) NOT NULL,
			byte_size BIGINT UNSIGNED NOT NULL,
			checksum CHAR(64) NOT NULL,
			scan_status VARCHAR(20) NOT NULL DEFAULT 'not_scanned',
			purpose VARCHAR(30) NOT NULL,
			uploaded_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			expires_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_private_files_key (storage_key),
			KEY idx_private_files_expires (expires_at),
			CONSTRAINT fk_private_files_uploader FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE notifications (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			recipient_user_id BIGINT UNSIGNED NOT NULL,
			type VARCHAR(50) NOT NULL,
			entity_type VARCHAR(50) NULL,
			entity_id VARCHAR(64) NULL,
			safe_summary VARCHAR(255) NOT NULL,
			link_path VARCHAR(255) NULL,
			read_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_notifications_recipient (recipient_user_id, read_at, created_at),
			CONSTRAINT fk_notifications_recipient FOREIGN KEY (recipient_user_id) REFERENCES users (id) ON DELETE CASCADE
		) $t");

		// status: pending, sent, failed, not_configured, skipped
		$this->db->query("CREATE TABLE notification_outbox (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			recipient_user_id BIGINT UNSIGNED NULL,
			channel VARCHAR(20) NOT NULL,
			template_key VARCHAR(60) NOT NULL,
			entity_type VARCHAR(50) NULL,
			entity_id VARCHAR(64) NULL,
			payload_json LONGTEXT NOT NULL,
			dedupe_key VARCHAR(191) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			attempts INT UNSIGNED NOT NULL DEFAULT 0,
			last_error VARCHAR(255) NULL,
			next_attempt_at DATETIME NOT NULL,
			sent_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_outbox_dedupe (dedupe_key),
			KEY idx_outbox_status (status, next_attempt_at),
			CONSTRAINT fk_outbox_recipient FOREIGN KEY (recipient_user_id) REFERENCES users (id) ON DELETE SET NULL
		) $t");
	}

	public function down()
	{
		$this->db->query('SET FOREIGN_KEY_CHECKS = 0');
		foreach (array('notification_outbox', 'notifications', 'private_files', 'idempotency_keys', 'job_locks',
			'app_settings', 'audit_logs', 'rate_limits', 'remember_tokens', 'user_sessions', 'ci_sessions',
			'mfa_recovery_codes', 'user_mfa', 'account_tokens', 'resident_profiles', 'administrative_areas',
			'user_unit_scopes', 'organizational_units', 'role_permissions', 'user_roles', 'permissions', 'roles', 'users') as $table)
		{
			$this->db->query('DROP TABLE IF EXISTS `'.$table.'`');
		}
		$this->db->query('SET FOREIGN_KEY_CHECKS = 1');
	}
}
