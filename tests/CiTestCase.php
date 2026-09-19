<?php

use PHPUnit\Framework\TestCase;

/**
 * Basis pengujian yang memakai instance CodeIgniter dari bootstrap.
 * Setiap tes membersihkan data transaksional yang dibuatnya sendiri.
 */
abstract class CiTestCase extends TestCase {

	/** @var MY_Controller */
	protected $CI;

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI =& get_instance();
		$this->CI->load->model(array('User_model' => 'user_model', 'Ticket_model' => 'tickets'));
		$this->CI->load->library('Totp', NULL, 'totp');
		$this->CI->load->library('AuthService', NULL, 'auth');
		$this->CI->load->library('AuthorizationService', NULL, 'authz');
		$this->CI->load->library('TicketWorkflowService', NULL, 'workflow');
		$this->CI->load->library('TicketAccessService', NULL, 'ticket_access');
		$this->CI->load->library('SlaService', NULL, 'sla');
		$this->CI->load->library('Idempotency', NULL, 'idempotency');
		$this->CI->load->library('SourceImportService', NULL, 'source_import');
		$this->CI->load->library('ExportService', NULL, 'exports');
		$this->CI->load->library('ContentService', NULL, 'content_service');
		$this->CI->clock->set(NULL);
		$this->CI->authz->flush();
	}

	protected function tearDown(): void
	{
		$this->CI->clock->set(NULL);
		parent::tearDown();
	}

	/** Bersihkan seluruh data tiket dan akun uji. */
	protected function truncate_service_data()
	{
		$db = $this->CI->db;
		$db->query('SET FOREIGN_KEY_CHECKS = 0');
		foreach (array('ticket_escalations', 'ticket_sla_pauses', 'ticket_sla_instances', 'ticket_resolution_episodes',
			'ticket_feedback', 'ticket_references', 'ticket_attachments', 'ticket_status_history', 'ticket_messages',
			'ticket_assignments', 'ticket_conflicts', 'anonymous_access_grants', 'ticket_access_secrets',
			'ticket_private_contacts', 'tickets', 'idempotency_keys', 'notifications', 'notification_outbox',
			'rate_limits', 'audit_logs', 'private_files') as $table)
		{
			// DELETE, bukan TRUNCATE: akun aplikasi sengaja tidak memiliki hak DDL.
			if ($db->query('DELETE FROM '.$table) === FALSE)
			{
				throw new RuntimeException('Gagal membersihkan tabel '.$table.': '.$db->error()['message']);
			}
		}
		$db->query('DELETE FROM user_unit_scopes');
		$db->query('DELETE FROM user_roles WHERE user_id IN (SELECT id FROM users WHERE username LIKE "%.test")');
		$db->query('DELETE FROM resident_profiles WHERE user_id IN (SELECT id FROM users WHERE username LIKE "%.test")');
		$db->query('DELETE FROM users WHERE username LIKE "%.test"');
		$db->query('SET FOREIGN_KEY_CHECKS = 1');
	}

	/** Buat pengguna uji dengan role tertentu. */
	protected function make_user($username, array $roles, $status = 'active')
	{
		$id = $this->CI->user_model->create(array(
			'public_id' => $this->CI->crypto->public_id(),
			'username' => $username,
			'display_name' => 'Pengguna '.$username,
			'email' => str_replace('.', '-', $username).'@contoh.test',
			'password_hash' => password_hash('KataSandiUjiCoba2026', PASSWORD_ARGON2ID),
			'account_status' => $status,
			'email_verified_at' => utc_now(),
			'registration_channel' => 'test',
		));
		foreach ($roles as $role)
		{
			$this->CI->user_model->assign_role($id, $role, NULL);
		}
		if (in_array('resident', $roles, TRUE))
		{
			$this->CI->db->insert('resident_profiles', array(
				'user_id' => $id, 'display_name' => 'Pengguna '.$username, 'verification_status' => 'verified',
				'created_at' => utc_now(), 'updated_at' => utc_now(),
			));
		}
		$this->CI->authz->flush($id);
		return $this->CI->user_model->find($id);
	}

	protected function add_unit_scope($user_id, $unit_code, $scope_type = 'monitor')
	{
		$unit = $this->CI->db->get_where('organizational_units', array('code' => $unit_code))->row();
		$this->CI->db->insert('user_unit_scopes', array(
			'user_id' => (int) $user_id, 'unit_id' => (int) $unit->id, 'scope_type' => $scope_type, 'created_at' => utc_now(),
		));
		$this->CI->authz->flush($user_id);
	}

	protected function category_id($code)
	{
		return (int) $this->CI->db->select('id')->get_where('ticket_categories', array('code' => $code))->row('id');
	}

	/** Buat tiket via service (alur nyata, bukan insert manual). */
	protected function submit_ticket(array $input = array(), array $context = array())
	{
		$defaults = array(
			'report_type' => 'complaint',
			'category_id' => $this->category_id('ADMINISTRASI'),
			'title' => 'Judul laporan pengujian otomatis',
			'description' => 'Uraian laporan pengujian otomatis yang panjangnya melebihi tiga puluh karakter.',
			'statement' => TRUE,
		);
		$ctx = array_merge(array(
			'intake_channel' => 'public_anonymous',
			'identity_mode' => 'anonymous',
			'reporter_user_id' => NULL,
			'created_by_user_id' => NULL,
			'actor_type' => 'anonymous',
		), $context);
		return $this->CI->workflow->submit(array_merge($defaults, $input), $ctx);
	}

	protected function staff_actor($user)
	{
		return array('kind' => 'staff', 'user_id' => (int) $user->id, 'type' => 'staff');
	}

	protected function reporter_actor($user = NULL)
	{
		return ($user === NULL)
			? array('kind' => 'reporter', 'user_id' => NULL, 'type' => 'anonymous')
			: array('kind' => 'reporter', 'user_id' => (int) $user->id, 'type' => 'resident');
	}
}
