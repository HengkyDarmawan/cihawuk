<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pengelolaan RBAC lewat dashboard dan fitur "login sebagai".
 *
 * - `admin_menu_overrides`: ganti label, grup, urutan, atau matikan item menu dashboard.
 *   Daftar item sendiri tetap di `config/admin_menu.php`, karena setiap item terikat ke
 *   route dan pemeriksaan izin di kode.
 * - `role_menu_hidden`: item menu yang disembunyikan untuk role tertentu. Item hanya hilang
 *   bila SEMUA role pengguna menyembunyikannya. Menyembunyikan menu bukan kontrol akses.
 * - `permissions.is_custom`: izin buatan dashboard (boleh dihapus) dibedakan dari izin
 *   bawaan `config/rbac.php` (tidak boleh dihapus karena diperiksa kode).
 * - `impersonator_user_id` pada sesi dan audit: siapa yang sebenarnya bertindak ketika
 *   super admin login sebagai pengguna lain.
 */
class Migration_Create_rbac_menu_and_impersonation extends CI_Migration {

	public function up()
	{
		$t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->db->query("CREATE TABLE admin_menu_overrides (
			menu_key VARCHAR(60) NOT NULL,
			label VARCHAR(80) NULL,
			group_label VARCHAR(80) NULL,
			sort_order INT NULL,
			active TINYINT(1) NOT NULL DEFAULT 1,
			updated_by BIGINT UNSIGNED NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (menu_key),
			CONSTRAINT fk_admin_menu_overrides_actor FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
		) $t");

		$this->db->query("CREATE TABLE role_menu_hidden (
			role_id BIGINT UNSIGNED NOT NULL,
			menu_key VARCHAR(60) NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (role_id, menu_key),
			CONSTRAINT fk_role_menu_hidden_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
		) $t");

		$this->db->query("ALTER TABLE permissions
			ADD COLUMN is_custom TINYINT(1) NOT NULL DEFAULT 0 AFTER description");

		$this->db->query("ALTER TABLE user_sessions
			ADD COLUMN impersonator_user_id BIGINT UNSIGNED NULL AFTER user_id,
			ADD CONSTRAINT fk_user_sessions_impersonator FOREIGN KEY (impersonator_user_id) REFERENCES users (id) ON DELETE SET NULL");

		$this->db->query("ALTER TABLE audit_logs
			ADD COLUMN impersonator_user_id BIGINT UNSIGNED NULL AFTER actor_user_id,
			ADD KEY idx_audit_logs_impersonator (impersonator_user_id),
			ADD CONSTRAINT fk_audit_logs_impersonator FOREIGN KEY (impersonator_user_id) REFERENCES users (id) ON DELETE SET NULL");
	}

	public function down()
	{
		$this->db->query('ALTER TABLE audit_logs DROP FOREIGN KEY fk_audit_logs_impersonator');
		$this->db->query('ALTER TABLE audit_logs DROP KEY idx_audit_logs_impersonator, DROP COLUMN impersonator_user_id');
		$this->db->query('ALTER TABLE user_sessions DROP FOREIGN KEY fk_user_sessions_impersonator');
		$this->db->query('ALTER TABLE user_sessions DROP COLUMN impersonator_user_id');
		$this->db->query('ALTER TABLE permissions DROP COLUMN is_custom');
		$this->db->query('DROP TABLE IF EXISTS `role_menu_hidden`');
		$this->db->query('DROP TABLE IF EXISTS `admin_menu_overrides`');
	}
}
