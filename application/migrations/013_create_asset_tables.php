<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tahap 10 spesifikasi v1.2 (prompt-master 18.4 dan 19.6): aset, unit fisik, QR, audit.
 *
 * Dua tingkat data dipertahankan: `asset_registers` menyimpan identitas administrasi dan
 * keuangan, `asset_units` menyimpan barang fisik yang benar-benar diperiksa atau ditempeli
 * QR. Satu register dapat mewakili nol, satu, atau banyak unit; pemecahan unit adalah
 * usulan yang direview, bukan hasil regex atas angka volume.
 *
 * Nilai perolehan, dokumen kepemilikan, dan nomor seri sensitif tidak pernah keluar ke
 * halaman QR publik. Token QR disimpan sebagai digest sehingga token asli tidak ada di
 * basis data maupun log.
 */
class Migration_Create_asset_tables extends CI_Migration {

	public function up()
	{
		$t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->db->query("CREATE TABLE asset_categories (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code VARCHAR(40) NOT NULL,
			name VARCHAR(180) NOT NULL,
			parent_id BIGINT UNSIGNED NULL,
			asset_class VARCHAR(40) NOT NULL,
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_asset_categories_code (code),
			CONSTRAINT fk_asset_categories_parent FOREIGN KEY (parent_id) REFERENCES asset_categories (id) ON DELETE RESTRICT
		) $t");

		$this->db->query("CREATE TABLE asset_locations (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code VARCHAR(40) NOT NULL,
			name VARCHAR(180) NOT NULL,
			location_type VARCHAR(30) NOT NULL DEFAULT 'room',
			parent_id BIGINT UNSIGNED NULL,
			address VARCHAR(400) NULL,
			latitude DECIMAL(10,7) NULL,
			longitude DECIMAL(10,7) NULL,
			is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_asset_locations_code (code),
			CONSTRAINT fk_asset_locations_parent FOREIGN KEY (parent_id) REFERENCES asset_locations (id) ON DELETE RESTRICT
		) $t");

		$this->db->query("CREATE TABLE asset_registers (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			legacy_asset_code VARCHAR(60) NULL,
			category_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(220) NOT NULL,
			description VARCHAR(1000) NULL,
			source_volume_raw VARCHAR(120) NULL,
			acquisition_year SMALLINT UNSIGNED NULL,
			acquisition_source VARCHAR(180) NULL,
			acquisition_value DECIMAL(18,2) NULL,
			ownership_status VARCHAR(30) NOT NULL DEFAULT 'owned',
			lifecycle_status VARCHAR(30) NOT NULL DEFAULT 'draft',
			source_document_id BIGINT UNSIGNED NULL,
			source_locator VARCHAR(255) NULL,
			verification_status VARCHAR(20) NOT NULL DEFAULT 'unverified',
			version INT UNSIGNED NOT NULL DEFAULT 1,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_asset_registers_public (public_id),
			UNIQUE KEY uq_asset_registers_legacy (legacy_asset_code),
			KEY idx_asset_registers_category (category_id, lifecycle_status),
			CONSTRAINT fk_asset_registers_category FOREIGN KEY (category_id) REFERENCES asset_categories (id) ON DELETE RESTRICT,
			CONSTRAINT fk_asset_registers_source FOREIGN KEY (source_document_id) REFERENCES source_documents (id) ON DELETE SET NULL,
			CONSTRAINT fk_asset_registers_author FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE asset_units (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			register_id BIGINT UNSIGNED NOT NULL,
			asset_tag VARCHAR(60) NOT NULL,
			unit_sequence INT UNSIGNED NULL,
			serial_number_ciphertext TEXT NULL,
			brand VARCHAR(120) NULL,
			model VARCHAR(120) NULL,
			location_id BIGINT UNSIGNED NULL,
			custodian_unit_id BIGINT UNSIGNED NULL,
			custodian_user_id BIGINT UNSIGNED NULL,
			lifecycle_status VARCHAR(30) NOT NULL DEFAULT 'draft',
			condition_status VARCHAR(30) NOT NULL DEFAULT 'not_assessed',
			primary_media_id BIGINT UNSIGNED NULL,
			public_note VARCHAR(500) NULL,
			activated_at DATETIME NULL,
			deactivated_at DATETIME NULL,
			version INT UNSIGNED NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_asset_units_public (public_id),
			UNIQUE KEY uq_asset_units_tag (asset_tag),
			KEY idx_asset_units_register (register_id, lifecycle_status),
			KEY idx_asset_units_location (location_id, condition_status),
			CONSTRAINT fk_asset_units_register FOREIGN KEY (register_id) REFERENCES asset_registers (id) ON DELETE RESTRICT,
			CONSTRAINT fk_asset_units_location FOREIGN KEY (location_id) REFERENCES asset_locations (id) ON DELETE SET NULL,
			CONSTRAINT fk_asset_units_custodian_unit FOREIGN KEY (custodian_unit_id) REFERENCES organizational_units (id) ON DELETE SET NULL,
			CONSTRAINT fk_asset_units_custodian_user FOREIGN KEY (custodian_user_id) REFERENCES users (id) ON DELETE SET NULL,
			CONSTRAINT fk_asset_units_media FOREIGN KEY (primary_media_id) REFERENCES media_assets (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE asset_unit_media (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			asset_unit_id BIGINT UNSIGNED NOT NULL,
			media_asset_id BIGINT UNSIGNED NULL,
			private_file_id BIGINT UNSIGNED NULL,
			media_role VARCHAR(30) NOT NULL DEFAULT 'documentation',
			audit_finding_id BIGINT UNSIGNED NULL,
			captured_at DATETIME NULL,
			uploaded_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_asset_unit_media_unit (asset_unit_id, media_role),
			CONSTRAINT fk_asset_unit_media_unit FOREIGN KEY (asset_unit_id) REFERENCES asset_units (id) ON DELETE CASCADE,
			CONSTRAINT fk_asset_unit_media_media FOREIGN KEY (media_asset_id) REFERENCES media_assets (id) ON DELETE SET NULL,
			CONSTRAINT fk_asset_unit_media_private FOREIGN KEY (private_file_id) REFERENCES private_files (id) ON DELETE SET NULL,
			CONSTRAINT fk_asset_unit_media_uploader FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE asset_documents (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			asset_register_id BIGINT UNSIGNED NULL,
			asset_unit_id BIGINT UNSIGNED NULL,
			document_type VARCHAR(40) NOT NULL,
			document_number_ciphertext TEXT NULL,
			private_file_id BIGINT UNSIGNED NULL,
			issued_at DATE NULL,
			expires_at DATE NULL,
			verification_status VARCHAR(20) NOT NULL DEFAULT 'unverified',
			uploaded_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_asset_documents_register (asset_register_id),
			KEY idx_asset_documents_unit (asset_unit_id),
			CONSTRAINT fk_asset_documents_register FOREIGN KEY (asset_register_id) REFERENCES asset_registers (id) ON DELETE CASCADE,
			CONSTRAINT fk_asset_documents_unit FOREIGN KEY (asset_unit_id) REFERENCES asset_units (id) ON DELETE CASCADE,
			CONSTRAINT fk_asset_documents_private FOREIGN KEY (private_file_id) REFERENCES private_files (id) ON DELETE SET NULL,
			CONSTRAINT fk_asset_documents_uploader FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE asset_qr_tokens (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			asset_unit_id BIGINT UNSIGNED NOT NULL,
			token_digest CHAR(64) NOT NULL,
			token_version INT UNSIGNED NOT NULL DEFAULT 1,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			issued_at DATETIME NOT NULL,
			revoked_at DATETIME NULL,
			revoked_reason VARCHAR(255) NULL,
			last_scanned_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_asset_qr_digest (token_digest),
			KEY idx_asset_qr_unit (asset_unit_id, status),
			CONSTRAINT fk_asset_qr_unit FOREIGN KEY (asset_unit_id) REFERENCES asset_units (id) ON DELETE CASCADE
		) $t");

		$this->db->query("CREATE TABLE asset_label_batches (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			template_code VARCHAR(40) NOT NULL DEFAULT 'label.a4_24',
			requested_by BIGINT UNSIGNED NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			item_count INT UNSIGNED NOT NULL DEFAULT 0,
			start_offset INT UNSIGNED NOT NULL DEFAULT 0,
			reprint_reason VARCHAR(255) NULL,
			private_file_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			printed_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_asset_label_batches_public (public_id),
			CONSTRAINT fk_asset_label_batches_user FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL,
			CONSTRAINT fk_asset_label_batches_file FOREIGN KEY (private_file_id) REFERENCES private_files (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE asset_label_batch_items (
			batch_id BIGINT UNSIGNED NOT NULL,
			asset_unit_id BIGINT UNSIGNED NOT NULL,
			qr_token_id BIGINT UNSIGNED NOT NULL,
			copy_count INT UNSIGNED NOT NULL DEFAULT 1,
			position_order INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (batch_id, asset_unit_id),
			KEY idx_asset_label_items_unit (asset_unit_id),
			CONSTRAINT fk_asset_label_items_batch FOREIGN KEY (batch_id) REFERENCES asset_label_batches (id) ON DELETE CASCADE,
			CONSTRAINT fk_asset_label_items_unit FOREIGN KEY (asset_unit_id) REFERENCES asset_units (id) ON DELETE CASCADE,
			CONSTRAINT fk_asset_label_items_token FOREIGN KEY (qr_token_id) REFERENCES asset_qr_tokens (id) ON DELETE RESTRICT
		) $t");

		// Append-only: perubahan master dan historinya berada dalam satu transaksi.
		$this->db->query("CREATE TABLE asset_status_events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			asset_unit_id BIGINT UNSIGNED NOT NULL,
			event_type VARCHAR(40) NOT NULL,
			from_lifecycle VARCHAR(30) NULL,
			to_lifecycle VARCHAR(30) NULL,
			from_condition VARCHAR(30) NULL,
			to_condition VARCHAR(30) NULL,
			effective_at DATETIME NOT NULL,
			reason VARCHAR(500) NOT NULL,
			actor_user_id BIGINT UNSIGNED NULL,
			supporting_file_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_asset_status_events_unit (asset_unit_id, effective_at),
			CONSTRAINT fk_asset_status_events_unit FOREIGN KEY (asset_unit_id) REFERENCES asset_units (id) ON DELETE CASCADE,
			CONSTRAINT fk_asset_status_events_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL,
			CONSTRAINT fk_asset_status_events_file FOREIGN KEY (supporting_file_id) REFERENCES private_files (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE asset_movements (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			asset_unit_id BIGINT UNSIGNED NOT NULL,
			from_location_id BIGINT UNSIGNED NULL,
			to_location_id BIGINT UNSIGNED NOT NULL,
			from_custodian_unit_id BIGINT UNSIGNED NULL,
			to_custodian_unit_id BIGINT UNSIGNED NULL,
			moved_at DATETIME NOT NULL,
			reason VARCHAR(500) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'requested',
			requested_by BIGINT UNSIGNED NULL,
			approved_by BIGINT UNSIGNED NULL,
			accepted_by BIGINT UNSIGNED NULL,
			supporting_file_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_asset_movements_public (public_id),
			KEY idx_asset_movements_unit (asset_unit_id, status),
			CONSTRAINT fk_asset_movements_unit FOREIGN KEY (asset_unit_id) REFERENCES asset_units (id) ON DELETE CASCADE,
			CONSTRAINT fk_asset_movements_from FOREIGN KEY (from_location_id) REFERENCES asset_locations (id) ON DELETE SET NULL,
			CONSTRAINT fk_asset_movements_to FOREIGN KEY (to_location_id) REFERENCES asset_locations (id) ON DELETE RESTRICT,
			CONSTRAINT fk_asset_movements_requester FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL,
			CONSTRAINT fk_asset_movements_approver FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL,
			CONSTRAINT fk_asset_movements_acceptor FOREIGN KEY (accepted_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE asset_loans (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			asset_unit_id BIGINT UNSIGNED NOT NULL,
			borrower_name_ciphertext TEXT NOT NULL,
			borrower_unit VARCHAR(180) NULL,
			purpose VARCHAR(500) NOT NULL,
			checked_out_at DATETIME NOT NULL,
			due_at DATETIME NULL,
			returned_at DATETIME NULL,
			checkout_condition VARCHAR(30) NOT NULL,
			return_condition VARCHAR(30) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'out',
			issued_by BIGINT UNSIGNED NULL,
			received_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_asset_loans_public (public_id),
			KEY idx_asset_loans_unit (asset_unit_id, status),
			CONSTRAINT fk_asset_loans_unit FOREIGN KEY (asset_unit_id) REFERENCES asset_units (id) ON DELETE CASCADE,
			CONSTRAINT fk_asset_loans_issuer FOREIGN KEY (issued_by) REFERENCES users (id) ON DELETE SET NULL,
			CONSTRAINT fk_asset_loans_receiver FOREIGN KEY (received_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE asset_maintenance (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			asset_unit_id BIGINT UNSIGNED NOT NULL,
			complaint VARCHAR(600) NOT NULL,
			action_taken VARCHAR(600) NULL,
			vendor VARCHAR(180) NULL,
			planned_at DATE NULL,
			started_at DATE NULL,
			completed_at DATE NULL,
			cost DECIMAL(18,2) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'planned',
			condition_before VARCHAR(30) NOT NULL,
			condition_after VARCHAR(30) NULL,
			private_file_id BIGINT UNSIGNED NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_asset_maintenance_public (public_id),
			KEY idx_asset_maintenance_unit (asset_unit_id, status),
			CONSTRAINT fk_asset_maintenance_unit FOREIGN KEY (asset_unit_id) REFERENCES asset_units (id) ON DELETE CASCADE,
			CONSTRAINT fk_asset_maintenance_file FOREIGN KEY (private_file_id) REFERENCES private_files (id) ON DELETE SET NULL,
			CONSTRAINT fk_asset_maintenance_author FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE asset_audit_sessions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			name VARCHAR(220) NOT NULL,
			scope_snapshot_json LONGTEXT NULL,
			starts_at DATETIME NOT NULL,
			ends_at DATETIME NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			created_by BIGINT UNSIGNED NULL,
			published_by BIGINT UNSIGNED NULL,
			closed_by BIGINT UNSIGNED NULL,
			closed_at DATETIME NULL,
			version INT UNSIGNED NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_asset_audit_sessions_public (public_id),
			KEY idx_asset_audit_sessions_status (status, starts_at),
			CONSTRAINT fk_asset_audit_sessions_author FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
			CONSTRAINT fk_asset_audit_sessions_publisher FOREIGN KEY (published_by) REFERENCES users (id) ON DELETE SET NULL,
			CONSTRAINT fk_asset_audit_sessions_closer FOREIGN KEY (closed_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		// Snapshot target dibekukan saat sesi diterbitkan dan tidak ikut berubah bila master berubah.
		$this->db->query("CREATE TABLE asset_audit_targets (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			audit_session_id BIGINT UNSIGNED NOT NULL,
			asset_unit_id BIGINT UNSIGNED NOT NULL,
			expected_location_id BIGINT UNSIGNED NULL,
			expected_condition VARCHAR(30) NOT NULL,
			expected_lifecycle VARCHAR(30) NOT NULL,
			asset_snapshot_json LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_asset_audit_targets (audit_session_id, asset_unit_id),
			CONSTRAINT fk_asset_audit_targets_session FOREIGN KEY (audit_session_id) REFERENCES asset_audit_sessions (id) ON DELETE CASCADE,
			CONSTRAINT fk_asset_audit_targets_unit FOREIGN KEY (asset_unit_id) REFERENCES asset_units (id) ON DELETE CASCADE,
			CONSTRAINT fk_asset_audit_targets_location FOREIGN KEY (expected_location_id) REFERENCES asset_locations (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE asset_audit_findings (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			audit_target_id BIGINT UNSIGNED NOT NULL,
			auditor_id BIGINT UNSIGNED NULL,
			scanned_qr_token_id BIGINT UNSIGNED NULL,
			existence_result VARCHAR(30) NOT NULL,
			observed_condition VARCHAR(30) NOT NULL,
			observed_location_id BIGINT UNSIGNED NULL,
			latitude DECIMAL(10,7) NULL,
			longitude DECIMAL(10,7) NULL,
			note VARCHAR(600) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			submitted_at DATETIME NULL,
			verified_by BIGINT UNSIGNED NULL,
			verified_at DATETIME NULL,
			verification_note VARCHAR(500) NULL,
			version INT UNSIGNED NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_asset_audit_findings_public (public_id),
			KEY idx_asset_audit_findings_target (audit_target_id, status),
			CONSTRAINT fk_asset_audit_findings_target FOREIGN KEY (audit_target_id) REFERENCES asset_audit_targets (id) ON DELETE CASCADE,
			CONSTRAINT fk_asset_audit_findings_auditor FOREIGN KEY (auditor_id) REFERENCES users (id) ON DELETE SET NULL,
			CONSTRAINT fk_asset_audit_findings_token FOREIGN KEY (scanned_qr_token_id) REFERENCES asset_qr_tokens (id) ON DELETE SET NULL,
			CONSTRAINT fk_asset_audit_findings_location FOREIGN KEY (observed_location_id) REFERENCES asset_locations (id) ON DELETE SET NULL,
			CONSTRAINT fk_asset_audit_findings_verifier FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE asset_audit_addenda (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			audit_session_id BIGINT UNSIGNED NOT NULL,
			finding_id BIGINT UNSIGNED NULL,
			reason VARCHAR(600) NOT NULL,
			change_snapshot_json LONGTEXT NULL,
			approved_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_asset_audit_addenda_session (audit_session_id),
			CONSTRAINT fk_asset_audit_addenda_session FOREIGN KEY (audit_session_id) REFERENCES asset_audit_sessions (id) ON DELETE CASCADE,
			CONSTRAINT fk_asset_audit_addenda_finding FOREIGN KEY (finding_id) REFERENCES asset_audit_findings (id) ON DELETE SET NULL,
			CONSTRAINT fk_asset_audit_addenda_approver FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		// Staging impor register aset. Baris mentah tidak pernah diubah.
		$this->db->query("CREATE TABLE asset_import_rows (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			import_batch_id BIGINT UNSIGNED NOT NULL,
			source_row_no INT UNSIGNED NOT NULL,
			raw_json LONGTEXT NOT NULL,
			legacy_code VARCHAR(60) NULL,
			proposed_register_json LONGTEXT NULL,
			proposed_units_json LONGTEXT NULL,
			validation_status VARCHAR(20) NOT NULL DEFAULT 'pending',
			review_note VARCHAR(600) NULL,
			reviewed_by BIGINT UNSIGNED NULL,
			committed_register_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_asset_import_rows (import_batch_id, source_row_no),
			KEY idx_asset_import_rows_status (validation_status),
			CONSTRAINT fk_asset_import_rows_batch FOREIGN KEY (import_batch_id) REFERENCES import_batches (id) ON DELETE CASCADE,
			CONSTRAINT fk_asset_import_rows_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL,
			CONSTRAINT fk_asset_import_rows_register FOREIGN KEY (committed_register_id) REFERENCES asset_registers (id) ON DELETE SET NULL
		) $t");
	}

	public function down()
	{
		$this->db->query('SET FOREIGN_KEY_CHECKS = 0');
		foreach (array('asset_import_rows', 'asset_audit_addenda', 'asset_audit_findings', 'asset_audit_targets',
			'asset_audit_sessions', 'asset_maintenance', 'asset_loans', 'asset_movements', 'asset_status_events',
			'asset_label_batch_items', 'asset_label_batches', 'asset_qr_tokens', 'asset_documents',
			'asset_unit_media', 'asset_units', 'asset_registers', 'asset_locations', 'asset_categories') as $table)
		{
			$this->db->query('DROP TABLE IF EXISTS `'.$table.'`');
		}
		$this->db->query('SET FOREIGN_KEY_CHECKS = 1');
	}
}
