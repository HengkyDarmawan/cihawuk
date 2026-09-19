<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ringkasan pengelola. Isi kartu mengikuti permission pengguna (§18.2),
 * dan seluruh angka dihitung dalam lingkup akses masing-masing.
 */
class Dashboard extends Admin_Controller {

	public function index()
	{
		$this->load->model(array('Ticket_query_model' => 'ticket_query', 'Ticket_model' => 'tickets'));
		$uid = (int) $this->user->id;
		$can = function ($p) { return $this->authz->can($p); };

		$data = array(
			'page_title' => 'Ringkasan',
			'nav_active' => 'dashboard',
			'has_ticket_access' => $this->authz->can_any(array('tickets.verify', 'tickets.monitor_scope', 'tickets.work_assigned')),
			'counts' => array(),
			'overdue' => 0,
			'workload' => array(),
			'deadlines' => array(),
			'trend' => array(),
			'categories' => array(),
			'pending_accounts' => 0,
			'content_stats' => NULL,
			'job_stats' => NULL,
			'recent_audit' => array(),
			'notifications' => $this->notifications->latest($uid, 5),
		);

		if ($data['has_ticket_access'])
		{
			$data['counts'] = $this->ticket_query->status_counts($uid);
			$data['overdue'] = $this->ticket_query->count_overdue($uid);
			$data['unassigned'] = $this->ticket_query->count_all($uid, array('unassigned' => 1, 'status' => 'verifying'));
			$data['mine_active'] = $this->ticket_query->count_all($uid, array('mine' => 1, 'status' => 'in_progress'));
			$data['mine_assigned'] = $this->ticket_query->count_all($uid, array('mine' => 1, 'status' => 'assigned'));
		}
		if ($can('tickets.work_assigned'))
		{
			$data['deadlines'] = $this->ticket_query->upcoming_deadlines($uid, 5);
		}
		if ($can('tickets.monitor_scope'))
		{
			$data['workload'] = $this->ticket_query->workload($uid, 8);
			$data['trend'] = $this->ticket_query->monthly_trend($uid, 6);
			$data['categories'] = $this->ticket_query->category_distribution($uid, 6);
		}
		if ($this->authz->can_any(array('residents.verify', 'users.manage')))
		{
			$data['pending_accounts'] = (int) $this->db->where('account_status', 'pending_activation')->count_all_results('users');
		}
		if ($can('content.edit'))
		{
			$data['content_stats'] = array(
				'draft' => (int) $this->db->where('publication_status', 'draft')->where('deleted_at IS NULL', NULL, FALSE)->count_all_results('posts'),
				'in_review' => (int) $this->db->where('publication_status', 'in_review')->where('deleted_at IS NULL', NULL, FALSE)->count_all_results('posts'),
				'scheduled' => (int) $this->db->where('publication_status', 'published')->where('published_at >', utc_now())->count_all_results('posts'),
				'data_issues' => (int) $this->db->where('status', 'open')->count_all_results('data_issues'),
			);
		}
		if ($can('settings.manage'))
		{
			$data['job_stats'] = array(
				'outbox_pending' => (int) $this->db->where('status', 'pending')->count_all_results('notification_outbox'),
				'outbox_failed' => (int) $this->db->where('status', 'failed')->count_all_results('notification_outbox'),
				'outbox_not_configured' => (int) $this->db->where('status', 'not_configured')->count_all_results('notification_outbox'),
				'mail_enabled' => $this->mailer->enabled(),
				'last_job' => $this->db->select('locked_until, job_key')->order_by('locked_until', 'DESC')->limit(1)->get('job_locks')->row(),
			);
		}
		if ($can('audit.view'))
		{
			$data['recent_audit'] = $this->db->select('a.*, u.display_name AS actor_name')
				->from('audit_logs a')->join('users u', 'u.id = a.actor_user_id', 'left')
				->order_by('a.id', 'DESC')->limit(8)->get()->result();
		}

		$this->render('admin/dashboard', $data, 'dashboard');
	}

	/** Mode pratinjau konten draft pada situs publik (khusus editor). */
	public function preview()
	{
		$this->require_method('post');
		$this->require_permission('content.edit');
		$enabled = (bool) $this->input->post('enable');
		$this->session->set_userdata('content_preview', $enabled);
		$this->flash('success', $enabled
			? 'Mode pratinjau aktif: konten draft ikut tampil saat Anda membuka situs publik.'
			: 'Mode pratinjau dimatikan.');
		redirect(site_url('admin'), 'location', 303);
	}
}
