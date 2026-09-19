<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Penanganan laporan oleh pengelola: daftar berlingkup, detail, verifikasi,
 * disposisi, tindak lanjut, transisi status, identitas, dan input loket.
 */
class Laporan extends Admin_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->model(array('Ticket_model' => 'tickets', 'Ticket_query_model' => 'ticket_query'));
		$this->load->library('TicketWorkflowService', NULL, 'workflow');
		$this->load->library('TicketAccessService', NULL, 'ticket_access');
		$this->load->library('SlaService', NULL, 'sla');
		$this->load->library('Idempotency', NULL, 'idempotency');
		$this->layout_data['nav_active'] = 'laporan';
	}

	protected function require_list_access()
	{
		$this->require_any(array('tickets.verify', 'tickets.monitor_scope', 'tickets.work_assigned'));
	}

	public function index()
	{
		$this->require_list_access();
		$this->render('admin/laporan_index', array(
			'page_title' => 'Laporan',
			'statuses' => app_config('ticket_statuses'),
			'report_types' => app_config('report_types'),
			'channels' => app_config('intake_channels'),
			'priorities' => app_config('priorities'),
			'categories' => $this->tickets->active_categories(),
			'keyword' => mb_substr((string) $this->input->get('q'), 0, 100),
			'preset' => (string) $this->input->get('preset'),
			'extra_css' => array('vendor/datatables/css/dataTables.bootstrap4.min.css'),
			'extra_js' => array('vendor/datatables/js/dataTables.min.js', 'vendor/datatables/js/dataTables.bootstrap4.min.js', 'vendor/sweetalert2/sweetalert2.min.js'),
		), 'dashboard');
	}

	/** Endpoint DataTables (GET, read-only). */
	public function data()
	{
		$this->require_list_access();
		$this->require_method('get');

		$draw = (int) $this->input->get('draw');
		$start = max(0, (int) $this->input->get('start'));
		$length = (int) $this->input->get('length');
		$length = in_array($length, array(10, 25, 50, 100), TRUE) ? $length : 25;
		$order = $this->input->get('order');
		$order_column = isset($order[0]['column']) ? (int) $order[0]['column'] : 5;
		$order_dir = isset($order[0]['dir']) && strtolower($order[0]['dir']) === 'asc' ? 'asc' : 'desc';
		$search = $this->input->get('search');

		$filters = array(
			'keyword' => mb_substr((string) ($search['value'] ?? $this->input->get('q')), 0, 100),
			'status' => (string) $this->input->get('status'),
			'report_type' => (string) $this->input->get('report_type'),
			'category_id' => (int) $this->input->get('category_id'),
			'channel' => (string) $this->input->get('channel'),
			'priority' => (string) $this->input->get('priority'),
			'from' => $this->valid_date((string) $this->input->get('from')),
			'to' => $this->valid_date((string) $this->input->get('to')),
			'overdue' => $this->input->get('overdue') ? 1 : 0,
			'mine' => $this->input->get('mine') ? 1 : 0,
			'unassigned' => $this->input->get('unassigned') ? 1 : 0,
		);

		$uid = (int) $this->user->id;
		$total = $this->ticket_query->count_all($uid, array());
		$filtered = $this->ticket_query->count_all($uid, $filters);
		$rows = $this->ticket_query->listing($uid, $filters, $length, $start, $order_column, $order_dir);

		$data = array();
		foreach ($rows as $row)
		{
			$sla = $this->sla->status($row);
			$flags = '';
			if ($row->confidentiality === 'restricted')
			{
				$flags .= '<span class="chip-flag is-warning">rahasia</span> ';
			}
			if ($row->identity_mode === 'anonymous')
			{
				$flags .= '<span class="chip-flag">anonim</span> ';
			}
			if ((int) $row->current_episode > 1)
			{
				$flags .= '<span class="chip-flag is-info">episode '.(int) $row->current_episode.'</span> ';
			}
			if ($sla !== NULL)
			{
				foreach (array('verification', 'first_response', 'resolution') as $milestone)
				{
					if ( ! empty($sla[$milestone]['overdue']))
					{
						$flags .= '<span class="chip-flag is-danger">terlambat</span> ';
						break;
					}
				}
			}
			$data[] = array(
				'<a class="font-weight-bold" href="'.site_url('admin/laporan/'.rawurlencode($row->public_code)).'">'.e($row->public_code).'</a>',
				'<div>'.e($row->title).'</div><div class="small text-muted">'.e($row->category_name).'</div>'.$flags,
				e(config_label('report_types', $row->report_type)),
				ticket_status_badge($row->status, 'staff'),
				$row->assignee_name ? e($row->assignee_name).($row->unit_name ? '<div class="small text-muted">'.e($row->unit_name).'</div>' : '') : '<span class="text-muted">belum ditugaskan</span>',
				'<span title="'.e(format_wib($row->submitted_at)).'">'.e(format_wib($row->submitted_at, 'short')).'</span>',
			);
		}

		$this->json(200, array(
			'success' => TRUE,
			'draw' => $draw,
			'recordsTotal' => $total,
			'recordsFiltered' => $filtered,
			'data' => $data,
		));
	}

	protected function valid_date($value)
	{
		return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
	}

	// ------------------------------------------------------------ Detail

	protected function load_ticket($code)
	{
		$ticket = $this->tickets->find_by_code($code);
		if ( ! $ticket)
		{
			throw new DomainRuleException('Laporan tidak ditemukan.', 404);
		}
		$abilities = $this->authz->ticket_abilities($this->user->id, $ticket);
		if ( ! $abilities['view'])
		{
			throw new AccessDeniedException('No access to ticket '.$ticket->public_code);
		}
		return array($ticket, $abilities);
	}

	public function show($code)
	{
		list($ticket, $abilities) = $this->load_ticket($code);
		$this->render('admin/laporan_detail', $this->detail_data($ticket, $abilities), 'dashboard');
	}

	protected function detail_data($ticket, array $abilities, array $extra = array())
	{
		$category = $this->tickets->category($ticket->category_id);
		$reporter = NULL;
		if ($ticket->reporter_user_id !== NULL)
		{
			$reporter = $this->user_model->find($ticket->reporter_user_id);
		}
		$identity_revealed = (bool) $this->session->userdata('identity_reveal_'.$ticket->id);
		$assignable = array();
		if ($abilities['assign'])
		{
			foreach ($this->user_model->active_users_with_permission('tickets.work_assigned') as $candidate)
			{
				if ($this->authz->can_be_assignee($candidate->id, $ticket))
				{
					$assignable[(string) $candidate->id] = $candidate->display_name;
				}
			}
		}
		$units = array();
		foreach ($this->db->where('active', 1)->order_by('name')->get('organizational_units')->result() as $unit)
		{
			$units[(string) $unit->id] = $unit->name;
		}

		return array_merge(array(
			'page_title' => 'Laporan '.$ticket->public_code,
			'ticket' => $ticket,
			'category' => $category,
			'abilities' => $abilities,
			'actions' => $this->workflow->staff_actions($ticket, $abilities),
			'messages' => $this->tickets->messages($ticket->id, 'staff'),
			'history' => $this->tickets->history($ticket->id),
			'attachments' => $this->tickets->attachments($ticket->id),
			'assignments' => $this->tickets->assignments($ticket->id),
			'references' => $this->tickets->references($ticket->id),
			'conflicts' => $this->tickets->conflicts($ticket->id),
			'feedback' => $this->tickets->feedback($ticket->id, $ticket->current_episode),
			'sla' => $this->sla->status($ticket),
			'reporter' => $reporter,
			'identity_revealed' => $identity_revealed,
			'private_contact' => $identity_revealed ? $this->private_contact($ticket) : NULL,
			'assignable' => $assignable,
			'units' => $units,
			'rejection_reasons' => app_config('rejection_reasons'),
			'priorities' => app_config('priorities'),
			'extra_js' => array('vendor/sweetalert2/sweetalert2.min.js'),
		), $extra);
	}

	protected function private_contact($ticket)
	{
		$row = $this->db->get_where('ticket_private_contacts', array('ticket_id' => (int) $ticket->id))->row();
		if ( ! $row)
		{
			return NULL;
		}
		return array(
			'name' => $row->name_ciphertext ? $this->crypto->decrypt($row->name_ciphertext) : NULL,
			'email' => $row->email_ciphertext ? $this->crypto->decrypt($row->email_ciphertext) : NULL,
			'phone' => $row->phone_ciphertext ? $this->crypto->decrypt($row->phone_ciphertext) : NULL,
			'purpose' => $row->purpose,
		);
	}

	protected function actor()
	{
		return array('kind' => 'staff', 'user_id' => (int) $this->user->id, 'type' => 'staff');
	}

	protected function run($ticket, $abilities, $action, array $data, $message)
	{
		try
		{
			$this->workflow->perform($ticket, $action, $data + array('version' => (int) $this->input->post('version')), $this->actor());
		}
		catch (DomainRuleException $e)
		{
			$this->form_errors = $e->errors;
			$this->output->set_status_header($e->http_status);
			$fresh = $this->tickets->find($ticket->id);
			$this->render('admin/laporan_detail', $this->detail_data($fresh, $abilities, array('action_error' => $e->getMessage())), 'dashboard');
			return;
		}
		$this->flash('success', $message);
		redirect(site_url('admin/laporan/'.$ticket->public_code), 'location', 303);
	}

	public function verify($code)
	{
		$this->require_method('post');
		$this->require_permission('tickets.verify');
		list($ticket, $abilities) = $this->load_ticket($code);
		$this->run($ticket, $abilities, 'start_verification', array(), 'Laporan masuk tahap verifikasi.');
	}

	public function assign($code)
	{
		$this->require_method('post');
		$this->require_permission('tickets.assign');
		list($ticket, $abilities) = $this->load_ticket($code);
		if ( ! $abilities['assign'])
		{
			throw new AccessDeniedException('Cannot assign this ticket');
		}
		$this->run($ticket, $abilities, 'assign', array(
			'assignee_id' => (int) $this->input->post('assignee_id'),
			'unit_id' => (int) $this->input->post('unit_id'),
			'reason' => $this->post_string('reason', 500),
		), 'Penugasan laporan diperbarui.');
	}

	public function follow_up($code)
	{
		$this->require_method('post');
		list($ticket, $abilities) = $this->load_ticket($code);
		$visibility = ($this->input->post('visibility') === 'internal') ? 'internal' : 'reporter';
		if ($visibility === 'internal' && ! $abilities['internal_notes'])
		{
			throw new AccessDeniedException('No internal note permission');
		}
		$this->run($ticket, $abilities, 'follow_up', array(
			'message' => $this->post_string('message', 5100),
			'visibility' => $visibility,
		), ($visibility === 'internal') ? 'Catatan internal tersimpan.' : 'Tindak lanjut terkirim ke pelapor.');
	}

	/** Transisi status: hanya aksi dari allowlist, bukan status bebas. */
	public function transition($code)
	{
		$this->require_method('post');
		list($ticket, $abilities) = $this->load_ticket($code);
		$action = (string) $this->input->post('action');
		$available = $this->workflow->staff_actions($ticket, $abilities);
		if ( ! in_array($action, $available, TRUE))
		{
			throw new AccessDeniedException('Action '.$action.' not available');
		}
		$permissions = array(
			'reject' => 'tickets.verify', 'refer' => 'tickets.verify', 'request_information' => NULL,
			'accept_work' => 'tickets.work_assigned', 'propose_resolution' => NULL,
			'close_by_policy' => 'tickets.close', 'reopen' => 'tickets.reopen', 'resume_work' => NULL,
		);
		if ( ! empty($permissions[$action]))
		{
			$this->require_permission($permissions[$action]);
		}

		$data = array(
			'message' => $this->post_string('message', 5100),
			'reason_code' => (string) $this->input->post('reason_code'),
			'duplicate_of' => (string) $this->input->post('duplicate_of'),
			'target_name' => $this->post_string('target_name', 191),
			'target_url' => trim((string) $this->input->post('target_url')),
			'reference_type' => (string) $this->input->post('reference_type'),
			'external_reference' => $this->post_string('external_reference', 191),
		);
		$labels = array(
			'request_information' => 'Permintaan kelengkapan terkirim ke pelapor.',
			'reject' => 'Laporan ditandai tidak dapat diproses.',
			'refer' => 'Laporan dirujuk ke layanan lain.',
			'accept_work' => 'Anda mulai menangani laporan ini.',
			'propose_resolution' => 'Hasil penanganan dikirim untuk ditanggapi pelapor.',
			'close_by_policy' => 'Laporan ditutup sesuai kebijakan.',
			'resume_work' => 'Penanganan dilanjutkan.',
			'reopen' => 'Laporan dibuka kembali pada episode baru.',
		);
		$this->run($ticket, $abilities, $action, $data, $labels[$action] ?? 'Status laporan diperbarui.');
	}

	public function priority($code)
	{
		$this->require_method('post');
		list($ticket, $abilities) = $this->load_ticket($code);
		if ( ! ($abilities['verify'] OR $abilities['assign'] OR $abilities['monitor']))
		{
			throw new AccessDeniedException('No priority permission');
		}
		$priority = (string) $this->input->post('priority');
		if ( ! isset(app_config('priorities', array())[$priority]))
		{
			throw new DomainRuleException('Prioritas tidak valid.', 422);
		}
		$version = (int) $this->input->post('version');
		$this->tickets->update_with_version($ticket->id, $version, array('priority' => $priority));
		$this->audit->log('ticket.priority_changed', 'ticket', $ticket->public_code, array('priority' => $priority));
		$this->flash('success', 'Prioritas internal diperbarui.');
		redirect(site_url('admin/laporan/'.$ticket->public_code), 'location', 303);
	}

	/** Pembukaan identitas pelapor: butuh izin khusus, alasan, dan tercatat audit. */
	public function reveal_identity($code)
	{
		$this->require_method('post');
		$this->require_permission('tickets.view_identity');
		list($ticket, $abilities) = $this->load_ticket($code);
		if ($ticket->identity_mode === 'anonymous')
		{
			throw new DomainRuleException('Laporan ini dikirim tanpa identitas; sistem tidak menyimpan identitas pelapor.', 409);
		}
		$reason = $this->post_string('reason', 500);
		if (mb_strlen(trim($reason)) < 10)
		{
			throw new DomainRuleException('Tuliskan alasan pembukaan identitas (minimal 10 karakter).', 422, array('reason' => 'Alasan wajib diisi.'));
		}
		if ($ticket->identity_mode === 'masked' && ! $this->authz->can('tickets.handle_confidential'))
		{
			throw new AccessDeniedException('Masked identity requires confidential handling permission');
		}
		$this->session->set_userdata('identity_reveal_'.$ticket->id, TRUE);
		$this->audit->log('ticket.identity_revealed', 'ticket', $ticket->public_code, array(
			'identity_mode' => $ticket->identity_mode, 'reason' => mb_substr($reason, 0, 200),
		));
		$this->flash('warning', 'Identitas pelapor dibuka untuk sesi ini. Akses ini tercatat pada log audit.');
		redirect(site_url('admin/laporan/'.$ticket->public_code), 'location', 303);
	}

	/** Catat konflik kepentingan sehingga petugas terkait tidak dapat menangani/melihat. */
	public function conflict($code)
	{
		$this->require_method('post');
		$this->require_any(array('tickets.assign', 'tickets.monitor_scope', 'tickets.verify'));
		list($ticket, $abilities) = $this->load_ticket($code);
		$user_id = (int) $this->input->post('user_id');
		$reason = $this->post_string('reason', 500);
		$target = $this->user_model->find($user_id);
		if ( ! $target OR mb_strlen(trim($reason)) < 10)
		{
			throw new DomainRuleException('Pilih petugas dan tuliskan alasan konflik kepentingan (minimal 10 karakter).', 422);
		}
		db_transaction(function () use ($ticket, $user_id, $reason) {
			db_must($this->db->query(
				'INSERT IGNORE INTO ticket_conflicts (ticket_id, user_id, declared_by, reason, created_at) VALUES (?, ?, ?, ?, ?)',
				array((int) $ticket->id, $user_id, (int) $this->user->id, $reason, utc_now())
			), 'ticket_conflicts.insert');
			if ((int) $ticket->assigned_user_id === $user_id)
			{
				$this->tickets->update_with_version($ticket->id, (int) $ticket->version, array('assigned_user_id' => NULL));
				$this->db->where('ticket_id', (int) $ticket->id)->where('ended_at IS NULL', NULL, FALSE)
					->update('ticket_assignments', array('ended_at' => utc_now()));
			}
		});
		$this->audit->log('ticket.conflict_declared', 'ticket', $ticket->public_code, array('user_id' => $user_id));
		$this->notifications->notify_permission_holders('tickets.monitor_scope', 'ticket.conflict',
			'Konflik kepentingan dicatat pada laporan '.$ticket->public_code.'. Perlu penugasan ulang.', 'ticket', $ticket->public_code, '/admin/laporan/'.$ticket->public_code);
		$this->flash('success', 'Konflik kepentingan dicatat. Petugas tersebut tidak dapat menangani laporan ini.');
		redirect(site_url('admin/laporan/'.$ticket->public_code), 'location', 303);
	}

	// ------------------------------------------------------------ Input loket

	public function create()
	{
		$this->require_permission('tickets.create_on_behalf');
		$this->layout_data['nav_active'] = 'laporan-buat';
		$this->render('admin/laporan_create', $this->create_data(), 'dashboard');
	}

	protected function create_data(array $extra = array())
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
			'page_title' => 'Input Laporan Loket',
			'nav_active' => 'laporan-buat',
			'categories' => $categories,
			'report_types' => app_config('report_types'),
			'field_rules' => $this->settings->get('tickets.field_rules', array()),
			'upload_rules' => app_config('ticket_upload'),
			'idempotency_key' => $this->crypto->random_hex(16),
			'extra_js' => array('vendor/sweetalert2/sweetalert2.min.js'),
		), $extra);
	}

	/** Pencarian akun warga untuk loket: hasil terbatas dan dimasking. */
	public function search_residents()
	{
		$this->require_method('get');
		$this->require_permission('tickets.create_on_behalf');
		$keyword = (string) $this->input->get('q');
		$results = array();
		foreach ($this->tickets->search_residents($keyword, 8) as $row)
		{
			$results[] = array(
				'id' => (int) $row->id,
				'label' => $row->display_name.' ('.$row->username.')',
				'contact' => $row->email ? mask_text(strtok($row->email, '@'), 2).'@…' : ($row->phone ? mask_text($row->phone, 5) : '—'),
			);
		}
		$this->audit->log('resident.search', 'user', NULL, array('results' => count($results)));
		$this->json(200, array('success' => TRUE, 'data' => $results));
	}

	public function store()
	{
		$this->require_method('post');
		$this->require_permission('tickets.create_on_behalf');

		$mode = (string) $this->input->post('reporter_mode');
		if ( ! in_array($mode, array('account', 'identified', 'anonymous'), TRUE))
		{
			throw new DomainRuleException('Pilih jenis pelapor.', 422, array('reporter_mode' => 'Pilihan tidak valid.'));
		}
		$reporter_user_id = NULL;
		$contact = array();
		if ($mode === 'account')
		{
			$reporter_user_id = (int) $this->input->post('reporter_user_id');
			$reporter = $reporter_user_id ? $this->user_model->find($reporter_user_id) : NULL;
			if ( ! $reporter OR $reporter->account_status !== 'active' OR ! in_array('resident', $this->user_model->role_codes($reporter_user_id), TRUE))
			{
				throw new DomainRuleException('Akun warga tidak ditemukan atau tidak aktif.', 422, array('reporter_user_id' => 'Pilih akun warga yang valid.'));
			}
		}
		elseif ($mode === 'identified')
		{
			$contact = array(
				'name' => $this->post_string('contact_name', 100),
				'phone' => $this->post_string('contact_phone', 30),
				'email' => $this->post_string('contact_email', 191),
			);
			if (trim($contact['name']) === '')
			{
				throw new DomainRuleException('Tuliskan nama pelapor atau pilih pelapor anonim.', 422, array('contact_name' => 'Nama wajib diisi bila pelapor bersedia memberi identitas.'));
			}
		}

		$input = array(
			'report_type' => (string) $this->input->post('report_type'),
			'category_id' => (int) $this->input->post('category_id'),
			'title' => $this->post_string('title', 200),
			'description' => $this->post_string('description', 10100),
			'incident_date' => (string) $this->input->post('incident_date'),
			'location_text' => $this->post_string('location_text', 300),
			'confidential' => (bool) $this->input->post('confidential'),
			'statement' => TRUE,
		);
		$this->old_input = $input;
		$key = (string) $this->input->post('idempotency_key');
		if ( ! $this->idempotency->valid_key($key))
		{
			$key = $this->crypto->random_hex(16);
		}

		try
		{
			$result = $this->workflow->submit($input, array(
				'intake_channel' => 'front_desk',
				'identity_mode' => ($mode === 'anonymous') ? 'anonymous' : 'identified',
				// Petugas pencatat bukan pemilik laporan.
				'reporter_user_id' => $reporter_user_id,
				'created_by_user_id' => (int) $this->user->id,
				'actor_type' => 'staff',
				'attachments_field' => 'lampiran',
				'contact' => $contact,
				'idempotency' => array('key' => $key, 'scope' => 'staff:'.$this->user->id),
			));
		}
		catch (DomainRuleException $e)
		{
			$this->form_errors = $e->errors;
			$this->output->set_status_header($e->http_status);
			$this->render('admin/laporan_create', $this->create_data(array('submit_error' => $e->getMessage(), 'idempotency_key' => $key)), 'dashboard');
			return;
		}

		if ($mode === 'anonymous' && ! empty($result['access_code']))
		{
			// Kode akses hanya ditampilkan sekali di sini untuk diberikan kepada pelapor.
			$this->session->set_flashdata('front_desk_receipt', array(
				'code' => $result['ticket']->public_code,
				'access_code' => $this->ticket_access->format_code($result['access_code']),
			));
		}
		$this->flash('success', 'Laporan loket tersimpan dengan nomor '.$result['ticket']->public_code.'.');
		redirect(site_url('admin/laporan/'.$result['ticket']->public_code), 'location', 303);
	}
}
