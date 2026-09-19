<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Laporan milik warga yang login. Kepemilikan selalu diambil dari sesi;
 * field reporter/status/assignee yang dikirim browser diabaikan.
 */
class Laporan extends Resident_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->model('Ticket_model', 'tickets');
		$this->load->library('TicketWorkflowService', NULL, 'workflow');
		$this->load->library('Idempotency', NULL, 'idempotency');
		$this->layout_data['nav_active'] = 'laporan';
	}

	public function index()
	{
		$filters = array(
			'status' => (string) $this->input->get('status'),
			'report_type' => (string) $this->input->get('jenis'),
		);
		$page = max(1, (int) $this->input->get('hal'));
		$per_page = 10;
		$total = $this->tickets->count_for_reporter($this->user->id, $filters);
		$this->render('warga/laporan_index', array(
			'page_title' => 'Laporan Saya',
			'tickets' => $this->tickets->for_reporter($this->user->id, $filters, $per_page, ($page - 1) * $per_page),
			'filters' => $filters,
			'statuses' => app_config('ticket_statuses'),
			'report_types' => app_config('report_types'),
			'page' => $page,
			'pages' => max(1, (int) ceil($total / $per_page)),
			'total' => $total,
		), 'dashboard');
	}

	public function create()
	{
		$this->layout_data['nav_active'] = 'laporan-buat';
		$this->render('warga/laporan_create', $this->form_data(), 'dashboard');
	}

	protected function form_data(array $extra = array())
	{
		$categories = array();
		foreach ($this->tickets->active_categories() as $cat)
		{
			$categories[] = array(
				'value' => (string) $cat->id,
				'label' => $cat->name.((int) $cat->is_sensitive === 1 ? ' (otomatis rahasia)' : ''),
				'data' => array('type' => (string) $cat->report_type),
			);
		}
		return array_merge(array(
			'page_title' => 'Buat Laporan',
			'categories' => $categories,
			'report_types' => app_config('report_types'),
			'field_rules' => $this->settings->get('tickets.field_rules', array()),
			'upload_rules' => app_config('ticket_upload'),
			'idempotency_key' => $this->crypto->random_hex(16),
		), $extra);
	}

	public function store()
	{
		$this->require_method('post');
		$wait = $this->rate_limiter->retry_after('resident_report', (string) $this->user->id);
		if ($wait > 0)
		{
			$this->too_many($wait);
			return;
		}

		$input = array(
			'report_type' => (string) $this->input->post('report_type'),
			'category_id' => (int) $this->input->post('category_id'),
			'title' => $this->post_string('title', 200),
			'description' => $this->post_string('description', 10100),
			'incident_date' => (string) $this->input->post('incident_date'),
			'location_text' => $this->post_string('location_text', 300),
			'latitude' => (string) $this->input->post('latitude'),
			'longitude' => (string) $this->input->post('longitude'),
			'confidential' => (bool) $this->input->post('confidential'),
			'statement' => (bool) $this->input->post('statement'),
		);
		$this->old_input = $input;
		$key = (string) $this->input->post('idempotency_key');
		if ( ! $this->idempotency->valid_key($key))
		{
			$key = $this->crypto->random_hex(16);
		}
		$this->rate_limiter->hit('resident_report', (string) $this->user->id);

		try
		{
			$result = $this->workflow->submit($input, array(
				'intake_channel' => 'resident_dashboard',
				// Identitas pelapor selalu dari sesi, tidak pernah dari POST.
				'identity_mode' => $this->input->post('hide_identity') ? 'masked' : 'identified',
				'reporter_user_id' => (int) $this->user->id,
				'created_by_user_id' => NULL,
				'actor_type' => 'resident',
				'attachments_field' => 'lampiran',
				'idempotency' => array('key' => $key, 'scope' => 'user:'.$this->user->id),
			));
		}
		catch (DomainRuleException $e)
		{
			$this->form_errors = $e->errors;
			$this->output->set_status_header($e->http_status);
			$this->layout_data['nav_active'] = 'laporan-buat';
			$this->render('warga/laporan_create', $this->form_data(array('submit_error' => $e->getMessage(), 'idempotency_key' => $key)), 'dashboard');
			return;
		}

		$this->flash('success', $result['replay']
			? 'Laporan ini sudah tersimpan sebelumnya dengan nomor '.$result['ticket']->public_code.'.'
			: 'Laporan Anda terkirim dengan nomor '.$result['ticket']->public_code.'.');
		redirect(site_url('warga/laporan/'.$result['ticket']->public_code), 'location', 303);
	}

	/** Ambil tiket milik pengguna ini saja. */
	protected function own_ticket($code)
	{
		$ticket = $this->tickets->find_by_code($code);
		if ( ! $ticket OR $ticket->reporter_user_id === NULL OR (int) $ticket->reporter_user_id !== (int) $this->user->id)
		{
			// Tidak membedakan "tidak ada" dan "bukan milik Anda".
			throw new DomainRuleException('Laporan tidak ditemukan.', 404);
		}
		return $ticket;
	}

	public function show($code)
	{
		$ticket = $this->own_ticket($code);
		$this->render('warga/laporan_detail', $this->detail_data($ticket), 'dashboard');
	}

	protected function detail_data($ticket, array $extra = array())
	{
		$category = $this->tickets->category($ticket->category_id);
		return array_merge(array(
			'page_title' => 'Laporan '.$ticket->public_code,
			'ticket' => $ticket,
			'category' => $category,
			'messages' => $this->tickets->messages($ticket->id, 'reporter'),
			'history' => $this->tickets->history($ticket->id),
			'attachments' => $this->tickets->attachments($ticket->id, 'reporter'),
			'actions' => $this->workflow->reporter_actions($ticket),
		), $extra);
	}

	protected function actor()
	{
		return array('kind' => 'reporter', 'user_id' => (int) $this->user->id, 'type' => 'resident');
	}

	protected function run($ticket, $action, array $data, $success_message)
	{
		try
		{
			$this->workflow->perform($ticket, $action, $data + array('version' => $ticket->version), $this->actor());
		}
		catch (DomainRuleException $e)
		{
			$this->form_errors = $e->errors;
			$this->output->set_status_header($e->http_status);
			$fresh = $this->tickets->find($ticket->id);
			$this->render('warga/laporan_detail', $this->detail_data($fresh, array('action_error' => $e->getMessage())), 'dashboard');
			return;
		}
		$this->flash('success', $success_message);
		redirect(site_url('warga/laporan/'.$ticket->public_code), 'location', 303);
	}

	public function reply($code)
	{
		$this->require_method('post');
		$ticket = $this->own_ticket($code);
		$action = ($ticket->status === 'needs_information') ? 'provide_information' : 'reply';
		$this->run($ticket, $action, array('message' => $this->post_string('message', 5100)), 'Pesan Anda terkirim ke petugas.');
	}

	public function confirm($code)
	{
		$this->require_method('post');
		$ticket = $this->own_ticket($code);
		$choice = (string) $this->input->post('choice');
		$action = ($choice === 'accept') ? 'accept_result' : 'request_followup';
		$this->run($ticket, $action, array(
			'message' => $this->post_string('message', 5100),
			'rating' => (string) $this->input->post('rating'),
		), ($action === 'accept_result') ? 'Terima kasih, laporan ditandai selesai.' : 'Permintaan tindak lanjut terkirim.');
	}

	public function withdraw($code)
	{
		$this->require_method('post');
		$ticket = $this->own_ticket($code);
		$action = in_array($ticket->status, array('submitted', 'verifying'), TRUE) ? 'withdraw' : 'request_withdrawal';
		$this->run($ticket, $action, array('message' => $this->post_string('message', 1000)),
			($action === 'withdraw') ? 'Laporan ditarik.' : 'Permintaan penarikan dicatat.');
	}
}
