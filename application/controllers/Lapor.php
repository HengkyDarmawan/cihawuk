<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Formulir laporan anonim (tanpa akun) dan bukti penerimaannya.
 * Tidak meminta nama, NIK, telepon, atau email.
 */
class Lapor extends Public_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->no_store = TRUE;
		$this->start_session();
		$this->load->model('Ticket_model', 'tickets');
		$this->load->library('TicketWorkflowService', NULL, 'workflow');
		$this->load->library('TicketAccessService', NULL, 'ticket_access');
		$this->layout_data['noindex'] = TRUE;
	}

	/** Scope idempotency milik sesi ini (bukan identitas pengguna). */
	protected function idem_scope()
	{
		$scope = $this->session->userdata('idem_scope');
		if ( ! is_string($scope) OR strlen($scope) !== 32)
		{
			$scope = $this->crypto->random_hex(16);
			$this->session->set_userdata('idem_scope', $scope);
		}
		return $scope;
	}

	protected function form_data(array $extra = array())
	{
		$categories = array();
		foreach ($this->tickets->active_categories() as $cat)
		{
			$categories[] = array(
				'value' => (string) $cat->id,
				'label' => $cat->name.((int) $cat->is_sensitive === 1 ? ' (otomatis rahasia)' : ''),
				'data' => array('type' => (string) $cat->report_type, 'sensitive' => (int) $cat->is_sensitive, 'location' => (int) $cat->location_required),
			);
		}
		return array_merge(array(
			'page_title' => 'Buat Laporan',
			'categories' => $categories,
			'report_types' => $this->config->item('report_types', 'app'),
			'field_rules' => $this->settings->get('tickets.field_rules', array()),
			'upload_rules' => $this->config->item('ticket_upload', 'app'),
			'idempotency_key' => $this->crypto->random_hex(16),
			'logged_in' => $this->auth->check(),
		), $extra);
	}

	public function index()
	{
		$this->idem_scope();
		$this->render('site/lapor', $this->form_data(), 'site');
	}

	public function submit()
	{
		$this->require_method('post');

		// Honeypot: respons generik tanpa menyimpan apa pun.
		if (trim((string) $this->input->post('website')) !== '')
		{
			log_message('info', 'Honeypot triggered on public report form');
			redirect(site_url('lapor/berhasil'), 'location', 303);
			return;
		}

		$ip = (string) $this->input->ip_address();
		$wait = max(
			$this->rate_limiter->retry_after('public_report', $ip),
			$this->rate_limiter->retry_after('public_report_global', 'all')
		);
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
		if ( ! $this->idempotency_valid($key))
		{
			$key = $this->crypto->random_hex(16);
		}

		$this->rate_limiter->hit('public_report', $ip);
		$this->rate_limiter->hit('public_report_global', 'all');

		try
		{
			$result = $this->workflow->submit($input, array(
				'intake_channel' => 'public_anonymous',
				'identity_mode' => 'anonymous',
				'reporter_user_id' => NULL,
				'created_by_user_id' => NULL,
				'actor_type' => 'anonymous',
				'attachments_field' => 'lampiran',
				'idempotency' => array('key' => $key, 'scope' => $this->idem_scope()),
			));
		}
		catch (DomainRuleException $e)
		{
			$this->form_errors = $e->errors;
			$this->output->set_status_header($e->http_status);
			$this->render('site/lapor', $this->form_data(array('submit_error' => $e->getMessage(), 'idempotency_key' => $key)), 'site');
			return;
		}

		if ($result['replay'])
		{
			// Pengiriman ulang dengan kunci sama: tampilkan receipt yang sama bila masih ada.
			$this->flash('info', 'Laporan ini sudah diterima sebelumnya dengan nomor '.$result['ticket']->public_code.'.');
			redirect(site_url('lapor/berhasil'), 'location', 303);
			return;
		}

		$this->ticket_access->store_receipt($result['ticket'], $result['access_code'], $key);
		redirect(site_url('lapor/berhasil'), 'location', 303);
	}

	protected function idempotency_valid($key)
	{
		$this->load->library('Idempotency', NULL, 'idempotency');
		return $this->idempotency->valid_key($key);
	}

	public function receipt()
	{
		$receipt = $this->ticket_access->receipt();
		if ( ! $receipt)
		{
			$this->render('site/lapor_receipt_expired', array('page_title' => 'Bukti penerimaan'), 'site');
			return;
		}
		$this->render('site/lapor_receipt', array(
			'page_title' => 'Laporan diterima',
			'receipt' => $receipt,
			'formatted_code' => $this->ticket_access->format_code($receipt['access_code']),
			'report_type_label' => config_label('report_types', $receipt['report_type']),
		), 'site');
	}

	public function receipt_done()
	{
		$this->require_method('post');
		$this->ticket_access->clear_receipt();
		$this->flash('success', 'Bukti penerimaan ditutup. Simpan kode akses Anda di tempat aman.');
		redirect(site_url('lacak'), 'location', 303);
	}
}
