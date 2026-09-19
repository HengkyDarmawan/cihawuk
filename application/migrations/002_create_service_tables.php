<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Layanan tiket: kategori, SLA, tiket dan seluruh jejak penanganan.
 * FK histori memakai RESTRICT/SET NULL agar jejak tidak terhapus berantai.
 */
class Migration_Create_service_tables extends CI_Migration {

	public function up()
	{
		$t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->db->query("CREATE TABLE business_calendars (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(100) NOT NULL,
			timezone VARCHAR(50) NOT NULL DEFAULT 'Asia/Jakarta',
			weekly_schedule_json LONGTEXT NOT NULL,
			is_example TINYINT(1) NOT NULL DEFAULT 0,
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id)
		) $t");

		$this->db->query("CREATE TABLE business_holidays (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			calendar_id BIGINT UNSIGNED NOT NULL,
			date DATE NOT NULL,
			label VARCHAR(150) NOT NULL,
			is_working_override TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_holiday_calendar_date (calendar_id, date),
			CONSTRAINT fk_holidays_calendar FOREIGN KEY (calendar_id) REFERENCES business_calendars (id) ON DELETE CASCADE
		) $t");

		$this->db->query("CREATE TABLE sla_policies (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code VARCHAR(50) NOT NULL,
			name VARCHAR(100) NOT NULL,
			calendar_id BIGINT UNSIGNED NOT NULL,
			verification_days INT UNSIGNED NOT NULL,
			first_response_days INT UNSIGNED NOT NULL,
			confirmation_days INT UNSIGNED NOT NULL,
			resolution_days INT UNSIGNED NULL,
			pause_rules_json LONGTEXT NOT NULL,
			auto_close_enabled TINYINT(1) NOT NULL DEFAULT 0,
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_sla_policies_code (code),
			CONSTRAINT fk_sla_policies_calendar FOREIGN KEY (calendar_id) REFERENCES business_calendars (id) ON DELETE RESTRICT
		) $t");

		$this->db->query("CREATE TABLE ticket_categories (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code VARCHAR(50) NOT NULL,
			name VARCHAR(100) NOT NULL,
			description VARCHAR(255) NULL,
			report_type VARCHAR(30) NULL,
			default_unit_id BIGINT UNSIGNED NULL,
			is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
			location_required TINYINT(1) NOT NULL DEFAULT 0,
			sla_policy_id BIGINT UNSIGNED NULL,
			sort_order INT NOT NULL DEFAULT 0,
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_ticket_categories_code (code),
			CONSTRAINT fk_ticket_categories_unit FOREIGN KEY (default_unit_id) REFERENCES organizational_units (id) ON DELETE SET NULL,
			CONSTRAINT fk_ticket_categories_sla FOREIGN KEY (sla_policy_id) REFERENCES sla_policies (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE tickets (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_code VARCHAR(20) NOT NULL,
			report_type VARCHAR(30) NOT NULL,
			category_id BIGINT UNSIGNED NOT NULL,
			title VARCHAR(180) NOT NULL,
			description TEXT NOT NULL,
			incident_date DATE NULL,
			location_text VARCHAR(255) NULL,
			latitude DECIMAL(10,7) NULL,
			longitude DECIMAL(10,7) NULL,
			reporter_user_id BIGINT UNSIGNED NULL,
			created_by_user_id BIGINT UNSIGNED NULL,
			intake_channel VARCHAR(30) NOT NULL,
			identity_mode VARCHAR(20) NOT NULL,
			confidentiality VARCHAR(20) NOT NULL DEFAULT 'private',
			status VARCHAR(30) NOT NULL,
			priority VARCHAR(20) NOT NULL DEFAULT 'normal',
			assigned_unit_id BIGINT UNSIGNED NULL,
			assigned_user_id BIGINT UNSIGNED NULL,
			return_status VARCHAR(30) NULL,
			duplicate_of_ticket_id BIGINT UNSIGNED NULL,
			current_episode INT UNSIGNED NOT NULL DEFAULT 1,
			version INT UNSIGNED NOT NULL DEFAULT 1,
			withdrawal_requested_at DATETIME NULL,
			submitted_at DATETIME NOT NULL,
			resolved_at DATETIME NULL,
			withdrawn_at DATETIME NULL,
			closed_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_tickets_public_code (public_code),
			KEY idx_tickets_status_submitted (status, submitted_at),
			KEY idx_tickets_reporter_submitted (reporter_user_id, submitted_at),
			KEY idx_tickets_assignee_status (assigned_user_id, status),
			KEY idx_tickets_unit_status (assigned_unit_id, status),
			KEY idx_tickets_category_submitted (category_id, submitted_at),
			KEY idx_tickets_created_by (created_by_user_id),
			KEY idx_tickets_duplicate (duplicate_of_ticket_id),
			CONSTRAINT fk_tickets_category FOREIGN KEY (category_id) REFERENCES ticket_categories (id) ON DELETE RESTRICT,
			CONSTRAINT fk_tickets_reporter FOREIGN KEY (reporter_user_id) REFERENCES users (id) ON DELETE RESTRICT,
			CONSTRAINT fk_tickets_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE RESTRICT,
			CONSTRAINT fk_tickets_unit FOREIGN KEY (assigned_unit_id) REFERENCES organizational_units (id) ON DELETE RESTRICT,
			CONSTRAINT fk_tickets_assignee FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON DELETE RESTRICT,
			CONSTRAINT fk_tickets_duplicate FOREIGN KEY (duplicate_of_ticket_id) REFERENCES tickets (id) ON DELETE RESTRICT
		) $t");

		$this->db->query("CREATE TABLE ticket_private_contacts (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id BIGINT UNSIGNED NOT NULL,
			name_ciphertext TEXT NULL,
			email_ciphertext TEXT NULL,
			phone_ciphertext TEXT NULL,
			key_version INT UNSIGNED NOT NULL,
			recorded_by BIGINT UNSIGNED NULL,
			purpose VARCHAR(100) NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_ticket_private_contacts_ticket (ticket_id),
			CONSTRAINT fk_tpc_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE RESTRICT,
			CONSTRAINT fk_tpc_recorded_by FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE ticket_access_secrets (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id BIGINT UNSIGNED NOT NULL,
			secret_hash CHAR(64) NOT NULL,
			secret_version INT UNSIGNED NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			revoked_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_ticket_access_secrets_ticket (ticket_id),
			CONSTRAINT fk_tas_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE RESTRICT
		) $t");

		$this->db->query("CREATE TABLE anonymous_access_grants (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id BIGINT UNSIGNED NOT NULL,
			session_fingerprint CHAR(64) NOT NULL,
			secret_version INT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			expires_at DATETIME NOT NULL,
			revoked_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_anon_grants_fp (session_fingerprint),
			KEY idx_anon_grants_ticket (ticket_id),
			KEY idx_anon_grants_expires (expires_at),
			CONSTRAINT fk_anon_grants_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE
		) $t");

		$this->db->query("CREATE TABLE ticket_assignments (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id BIGINT UNSIGNED NOT NULL,
			unit_id BIGINT UNSIGNED NULL,
			assignee_id BIGINT UNSIGNED NOT NULL,
			assigned_by BIGINT UNSIGNED NULL,
			assigned_at DATETIME NOT NULL,
			ended_at DATETIME NULL,
			reason VARCHAR(500) NULL,
			PRIMARY KEY (id),
			KEY idx_ticket_assignments_ticket (ticket_id, assigned_at),
			KEY idx_ticket_assignments_assignee (assignee_id, ended_at),
			CONSTRAINT fk_ta_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE RESTRICT,
			CONSTRAINT fk_ta_unit FOREIGN KEY (unit_id) REFERENCES organizational_units (id) ON DELETE RESTRICT,
			CONSTRAINT fk_ta_assignee FOREIGN KEY (assignee_id) REFERENCES users (id) ON DELETE RESTRICT,
			CONSTRAINT fk_ta_assigned_by FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		// actor_type: resident, anonymous, staff, system. visibility: reporter, internal
		$this->db->query("CREATE TABLE ticket_messages (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id BIGINT UNSIGNED NOT NULL,
			actor_user_id BIGINT UNSIGNED NULL,
			actor_type VARCHAR(20) NOT NULL,
			message_kind VARCHAR(30) NOT NULL DEFAULT 'reply',
			message TEXT NOT NULL,
			visibility VARCHAR(20) NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_ticket_messages_ticket (ticket_id, created_at),
			CONSTRAINT fk_tm_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE RESTRICT,
			CONSTRAINT fk_tm_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE ticket_status_history (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id BIGINT UNSIGNED NOT NULL,
			from_status VARCHAR(30) NULL,
			to_status VARCHAR(30) NOT NULL,
			action VARCHAR(40) NOT NULL,
			actor_user_id BIGINT UNSIGNED NULL,
			actor_type VARCHAR(20) NOT NULL,
			reason_code VARCHAR(50) NULL,
			reason TEXT NULL,
			reporter_note TEXT NULL,
			ticket_version INT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_tsh_ticket (ticket_id, created_at),
			CONSTRAINT fk_tsh_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE RESTRICT,
			CONSTRAINT fk_tsh_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE ticket_attachments (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id BIGINT UNSIGNED NOT NULL,
			message_id BIGINT UNSIGNED NULL,
			private_file_id BIGINT UNSIGNED NOT NULL,
			visibility VARCHAR(20) NOT NULL,
			uploaded_by_user_id BIGINT UNSIGNED NULL,
			uploader_type VARCHAR(20) NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_ticket_attachments_file (private_file_id),
			KEY idx_ticket_attachments_ticket (ticket_id),
			CONSTRAINT fk_tatt_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE RESTRICT,
			CONSTRAINT fk_tatt_message FOREIGN KEY (message_id) REFERENCES ticket_messages (id) ON DELETE RESTRICT,
			CONSTRAINT fk_tatt_file FOREIGN KEY (private_file_id) REFERENCES private_files (id) ON DELETE RESTRICT,
			CONSTRAINT fk_tatt_uploader FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		// reference_type: guidance_only, actual_forwarding
		$this->db->query("CREATE TABLE ticket_references (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id BIGINT UNSIGNED NOT NULL,
			target_name VARCHAR(191) NOT NULL,
			target_url VARCHAR(500) NULL,
			reference_type VARCHAR(30) NOT NULL,
			external_reference VARCHAR(191) NULL,
			sent_at DATETIME NULL,
			recorded_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_ticket_references_ticket (ticket_id),
			CONSTRAINT fk_tref_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE RESTRICT,
			CONSTRAINT fk_tref_recorded_by FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE ticket_feedback (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id BIGINT UNSIGNED NOT NULL,
			handling_episode INT UNSIGNED NOT NULL,
			rating TINYINT UNSIGNED NULL,
			response VARCHAR(30) NOT NULL,
			comment TEXT NULL,
			actor_user_id BIGINT UNSIGNED NULL,
			actor_type VARCHAR(20) NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_ticket_feedback_episode (ticket_id, handling_episode),
			CONSTRAINT fk_tfb_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE RESTRICT,
			CONSTRAINT fk_tfb_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE ticket_resolution_episodes (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id BIGINT UNSIGNED NOT NULL,
			episode_no INT UNSIGNED NOT NULL,
			opened_at DATETIME NOT NULL,
			resolved_at DATETIME NULL,
			reopened_by BIGINT UNSIGNED NULL,
			reopen_reason TEXT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_episode_ticket_no (ticket_id, episode_no),
			CONSTRAINT fk_tre_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE RESTRICT,
			CONSTRAINT fk_tre_reopened_by FOREIGN KEY (reopened_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE ticket_sla_instances (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id BIGINT UNSIGNED NOT NULL,
			episode_no INT UNSIGNED NOT NULL,
			policy_snapshot_json LONGTEXT NOT NULL,
			verification_due_at DATETIME NULL,
			verification_met_at DATETIME NULL,
			first_response_due_at DATETIME NULL,
			first_response_met_at DATETIME NULL,
			confirmation_due_at DATETIME NULL,
			resolution_due_at DATETIME NULL,
			resolution_met_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_sla_ticket_episode (ticket_id, episode_no),
			KEY idx_sla_verification_due (verification_met_at, verification_due_at),
			KEY idx_sla_first_response_due (first_response_met_at, first_response_due_at),
			KEY idx_sla_confirmation_due (confirmation_due_at),
			CONSTRAINT fk_tsi_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE RESTRICT
		) $t");

		$this->db->query("CREATE TABLE ticket_sla_pauses (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			sla_instance_id BIGINT UNSIGNED NOT NULL,
			started_at DATETIME NOT NULL,
			ended_at DATETIME NULL,
			reason VARCHAR(255) NOT NULL,
			actor_user_id BIGINT UNSIGNED NULL,
			PRIMARY KEY (id),
			KEY idx_tsp_instance (sla_instance_id, ended_at),
			CONSTRAINT fk_tsp_instance FOREIGN KEY (sla_instance_id) REFERENCES ticket_sla_instances (id) ON DELETE RESTRICT,
			CONSTRAINT fk_tsp_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE ticket_escalations (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id BIGINT UNSIGNED NOT NULL,
			episode_no INT UNSIGNED NOT NULL,
			milestone VARCHAR(30) NOT NULL,
			level INT UNSIGNED NOT NULL,
			recipient_user_id BIGINT UNSIGNED NOT NULL,
			dedupe_key VARCHAR(191) NOT NULL,
			triggered_at DATETIME NOT NULL,
			acknowledged_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_ticket_escalations_dedupe (dedupe_key),
			KEY idx_ticket_escalations_ticket (ticket_id),
			CONSTRAINT fk_tesc_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE RESTRICT,
			CONSTRAINT fk_tesc_recipient FOREIGN KEY (recipient_user_id) REFERENCES users (id) ON DELETE RESTRICT
		) $t");

		// Konflik kepentingan: petugas terlapor tidak boleh menangani/melihat tiket.
		$this->db->query("CREATE TABLE ticket_conflicts (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			declared_by BIGINT UNSIGNED NULL,
			reason VARCHAR(500) NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_ticket_conflicts (ticket_id, user_id),
			KEY idx_ticket_conflicts_user (user_id),
			CONSTRAINT fk_tc_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE RESTRICT,
			CONSTRAINT fk_tc_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
			CONSTRAINT fk_tc_declared_by FOREIGN KEY (declared_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE export_jobs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			public_id CHAR(26) NOT NULL,
			requested_by BIGINT UNSIGNED NOT NULL,
			report_type VARCHAR(50) NOT NULL,
			format VARCHAR(10) NOT NULL,
			filter_snapshot_json LONGTEXT NOT NULL,
			permission_version INT UNSIGNED NOT NULL,
			status VARCHAR(20) NOT NULL,
			row_count INT UNSIGNED NULL,
			error_message VARCHAR(255) NULL,
			private_file_id BIGINT UNSIGNED NULL,
			expires_at DATETIME NOT NULL,
			created_at DATETIME NOT NULL,
			completed_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_export_jobs_public (public_id),
			KEY idx_export_jobs_status (status, created_at),
			KEY idx_export_jobs_requester (requested_by, created_at),
			CONSTRAINT fk_export_jobs_requester FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE RESTRICT,
			CONSTRAINT fk_export_jobs_file FOREIGN KEY (private_file_id) REFERENCES private_files (id) ON DELETE SET NULL
		) $t");
	}

	public function down()
	{
		$this->db->query('SET FOREIGN_KEY_CHECKS = 0');
		foreach (array('export_jobs', 'ticket_conflicts', 'ticket_escalations', 'ticket_sla_pauses', 'ticket_sla_instances',
			'ticket_resolution_episodes', 'ticket_feedback', 'ticket_references', 'ticket_attachments', 'ticket_status_history',
			'ticket_messages', 'ticket_assignments', 'anonymous_access_grants', 'ticket_access_secrets', 'ticket_private_contacts',
			'tickets', 'ticket_categories', 'sla_policies', 'business_holidays', 'business_calendars') as $table)
		{
			$this->db->query('DROP TABLE IF EXISTS `'.$table.'`');
		}
		$this->db->query('SET FOREIGN_KEY_CHECKS = 1');
	}
}
