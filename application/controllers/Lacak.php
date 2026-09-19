<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pelacakan laporan anonim dengan nomor tiket + kode akses.
 * Tidak ada pencarian tiket berdasarkan nama/NIK; detail hanya untuk pemegang grant sesi.
 */
class Lacak extends Public_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->no_store = TRUE;
		$this->start_session();
		$this->load->model('Ticket_model', 'tickets');
		$this->load->library('TicketAccessService', NULL, 'ticket_access');
		$this->load->library('TicketWorkflowService', NULL, 'workflow');
		$this->layout_data['noindex'] = TRUE;
	}

	public function index()
	{
		if ($this->ticket_access->granted_ticket())
		{
			redirect(site_url('lacak/detail'), 'location', 303);
			return;
		}
		$this->render('site/lacak', array('page_title' => 'Lacak Laporan'), 'site');
	}

	public function verify()
	{
		$this->require_method('post');
		$code = strtoupper(trim((string) $this->input->post('public_code')));
		$access = (string) $this->input->post('access_code', FALSE);
		$ip = (string) $this->input->ip_address();

		$wait = max(
			$this->rate_limiter->retry_after('track_ip', $ip),
			$this->rate_limiter->retry_after('track_ticket', $code)
		);
		if ($wait > 0)
		{
			$this->too_many($wait);
			return;
		}

		$ticket = $this->ticket_access->verify($code, $access);
		if ( ! $ticket)
		{
			// Respons generik: tidak membedakan nomor tiket salah dan kode salah.
			$this->rate_limiter->hit('track_ip', $ip);
			$this->rate_limiter->hit('track_ticket', $code);
			$this->audit->log('ticket.track_failed', 'ticket', NULL, array('has_code' => $code !== ''));
			$this->output->set_status_header(401);
			$this->old_input = array('public_code' => $code);
			$this->render('site/lacak', array(
				'page_title' => 'Lacak Laporan',
				'track_error' => 'Nomor tiket dan kode akses tidak cocok. Periksa kembali keduanya.',
			), 'site');
			return;
		}

		$this->rate_limiter->clear('track_ticket', $code);
		$this->ticket_access->grant_session($ticket);
		$this->audit->log('ticket.track_opened', 'ticket', $ticket->public_code);
		redirect(site_url('lacak/detail'), 'location', 303);
	}

	protected function require_grant()
	{
		$ticket = $this->ticket_access->granted_ticket();
		if ( ! $ticket)
		{
			$this->flash('warning', 'Sesi pelacakan berakhir. Masukkan kembali nomor tiket dan kode akses.');
			redirect(site_url('lacak'), 'location', 303);
			exit;
		}
		return $ticket;
	}

	public function detail()
	{
		$ticket = $this->require_grant();
		$this->render('site/lacak_detail', $this->detail_data($ticket), 'site');
	}

	protected function detail_data($ticket)
	{
		// Hanya field yang boleh dibaca pelapor; record tidak diserialisasi utuh.
		$category = $this->tickets->category($ticket->category_id);
		return array(
			'page_title' => 'Laporan '.$ticket->public_code,
			'ticket' => array(
				'code' => $ticket->public_code,
				'status' => $ticket->status,
				'report_type' => $ticket->report_type,
				'category' => $category ? $category->name : '—',
				'title' => $ticket->title,
				'description' => $ticket->description,
				'location_text' => $ticket->location_text,
				'incident_date' => $ticket->incident_date,
				'submitted_at' => $ticket->submitted_at,
				'resolved_at' => $ticket->resolved_at,
				'confidentiality' => $ticket->confidentiality,
				'version' => (int) $ticket->version,
				'withdrawal_requested' => $ticket->withdrawal_requested_at !== NULL,
			),
			'messages' => $this->tickets->messages($ticket->id, 'reporter'),
			'attachments' => $this->tickets->attachments($ticket->id, 'reporter'),
			'history' => $this->tickets->history($ticket->id),
			'actions' => $this->workflow->reporter_actions($ticket),
			'logged_in' => $this->auth->check(),
		);
	}

	protected function actor()
	{
		return array('kind' => 'reporter', 'user_id' => NULL, 'type' => 'anonymous');
	}

	protected function run_action($action, array $data)
	{
		$ticket = $this->require_grant();
		try
		{
			$this->workflow->perform($ticket, $action, $data + array('version' => $ticket->version), $this->actor());
		}
		catch (DomainRuleException $e)
		{
			$this->form_errors = $e->errors;
			$this->output->set_status_header($e->http_status);
			$fresh = $this->tickets->find($ticket->id);
			$this->render('site/lacak_detail', $this->detail_data($fresh) + array('action_error' => $e->getMessage()), 'site');
			return FALSE;
		}
		return TRUE;
	}

	public function reply()
	{
		$this->require_method('post');
		$ticket = $this->require_grant();
		$action = ($ticket->status === 'needs_information') ? 'provide_information' : 'reply';
		if ($this->run_action($action, array('message' => $this->post_string('message', 5100))))
		{
			$this->flash('success', 'Balasan Anda terkirim ke petugas.');
			redirect(site_url('lacak/detail'), 'location', 303);
		}
	}

	public function confirm()
	{
		$this->require_method('post');
		$choice = (string) $this->input->post('choice');
		$action = ($choice === 'accept') ? 'accept_result' : 'request_followup';
		$data = array('message' => $this->post_string('message', 5100), 'rating' => (string) $this->input->post('rating'));
		if ($this->run_action($action, $data))
		{
			$this->flash('success', ($action === 'accept_result')
				? 'Terima kasih. Laporan Anda ditandai selesai.'
				: 'Permintaan tindak lanjut terkirim ke petugas.');
			redirect(site_url('lacak/detail'), 'location', 303);
		}
	}

	public function withdraw()
	{
		$this->require_method('post');
		$ticket = $this->require_grant();
		$action = in_array($ticket->status, array('submitted', 'verifying'), TRUE) ? 'withdraw' : 'request_withdrawal';
		if ($this->run_action($action, array('message' => $this->post_string('message', 1000))))
		{
			$this->flash('success', ($action === 'withdraw')
				? 'Laporan Anda ditarik. Riwayat penerimaan tetap tersimpan.'
				: 'Permintaan penarikan dicatat dan diteruskan ke petugas.');
			redirect(site_url('lacak/detail'), 'location', 303);
		}
	}

	public function leave()
	{
		$this->require_method('post');
		$this->ticket_access->revoke_session_grant();
		$this->flash('success', 'Anda keluar dari sesi pelacakan.');
		redirect(site_url('lacak'), 'location', 303);
	}

	/**
	 * Klaim laporan anonim ke akun warga: hanya dengan login, grant kode yang valid,
	 * dan persetujuan tegas. Hubungan akun baru terbentuk setelah tindakan ini.
	 */
	public function claim()
	{
		$this->require_method('post');
		$ticket = $this->require_grant();
		if ( ! $this->auth->check())
		{
			$this->flash('warning', 'Masuk terlebih dahulu untuk mengaitkan laporan ini ke akun Anda.');
			redirect(site_url('masuk'), 'location', 303);
			return;
		}
		if ( ! $this->input->post('agree'))
		{
			$this->flash('error', 'Centang persetujuan untuk mengaitkan laporan ke akun Anda.');
			redirect(site_url('lacak/detail'), 'location', 303);
			return;
		}
		if ($ticket->reporter_user_id !== NULL)
		{
			$this->flash('info', 'Laporan ini sudah terkait dengan sebuah akun.');
			redirect(site_url('lacak/detail'), 'location', 303);
			return;
		}
		$user = $this->auth->user();
		if ( ! in_array('resident', $this->user_model->role_codes($user->id), TRUE))
		{
			throw new AccessDeniedException('Only residents may claim tickets.');
		}
		db_transaction(function () use ($ticket, $user) {
			$this->tickets->update_with_version($ticket->id, (int) $ticket->version, array(
				'reporter_user_id' => (int) $user->id,
				'identity_mode' => 'masked',
			));
			$this->tickets->add_history($ticket->id, array(
				'from_status' => $ticket->status, 'to_status' => $ticket->status, 'action' => 'claim_by_account',
				'actor_user_id' => (int) $user->id, 'actor_type' => 'resident',
				'reason' => 'Pelapor mengaitkan laporan anonim ini ke akunnya setelah memasukkan kode akses.',
				'ticket_version' => (int) $ticket->version + 1,
			));
		});
		$this->audit->log('ticket.claimed_by_account', 'ticket', $ticket->public_code, array('identity_mode' => 'masked'));
		$this->ticket_access->revoke_session_grant();
		$this->flash('success', 'Laporan '.$ticket->public_code.' kini terhubung dengan akun Anda dan dapat dipantau dari dashboard.');
		redirect(site_url('warga/laporan/'.$ticket->public_code), 'location', 303);
	}
}
