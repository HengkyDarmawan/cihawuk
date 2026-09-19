<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Query dan penyimpanan tiket layanan. Pemeriksaan hak akses dilakukan
 * service/controller; model tidak pernah melonggarkan scope.
 */
class Ticket_model extends CI_Model {

	public function find($id)
	{
		return $this->db->get_where('tickets', array('id' => (int) $id))->row();
	}

	public function find_by_code($code)
	{
		$code = strtoupper(trim((string) $code));
		if ( ! preg_match('/^[A-Z0-9\-]{6,20}$/', $code))
		{
			return NULL;
		}
		return $this->db->get_where('tickets', array('public_code' => $code))->row();
	}

	/** Kunci baris tiket untuk transisi (harus di dalam transaction). */
	public function lock($id)
	{
		return $this->db->query('SELECT * FROM tickets WHERE id = ? FOR UPDATE', array((int) $id))->row();
	}

	public function category($id)
	{
		return $this->db->get_where('ticket_categories', array('id' => (int) $id))->row();
	}

	public function active_categories($report_type = NULL)
	{
		$this->db->where('active', 1);
		if ($report_type !== NULL)
		{
			$this->db->group_start()->where('report_type', $report_type)->or_where('report_type IS NULL', NULL, FALSE)->group_end();
		}
		return $this->db->order_by('sort_order')->get('ticket_categories')->result();
	}

	public function insert(array $data)
	{
		$now = utc_now();
		$data['created_at'] = $now;
		$data['updated_at'] = $now;
		db_must($this->db->insert('tickets', $data), 'tickets.insert');
		return (int) $this->db->insert_id();
	}

	/**
	 * Update dengan optimistic locking. Melempar VersionConflictException bila
	 * versi tiket sudah berubah (dua petugas menyimpan bersamaan).
	 */
	public function update_with_version($id, $expected_version, array $data)
	{
		$data['version'] = (int) $expected_version + 1;
		$data['updated_at'] = utc_now();
		db_must($this->db->where('id', (int) $id)->where('version', (int) $expected_version)->update('tickets', $data), 'tickets.update');
		if ($this->db->affected_rows() !== 1)
		{
			throw new VersionConflictException('Ticket '.$id.' version mismatch');
		}
		return (int) $data['version'];
	}

	public function add_message($ticket_id, array $data)
	{
		$data['ticket_id'] = (int) $ticket_id;
		$data['created_at'] = $data['created_at'] ?? utc_now();
		db_must($this->db->insert('ticket_messages', $data), 'ticket_messages.insert');
		return (int) $this->db->insert_id();
	}

	public function add_history($ticket_id, array $data)
	{
		$data['ticket_id'] = (int) $ticket_id;
		$data['created_at'] = $data['created_at'] ?? utc_now();
		db_must($this->db->insert('ticket_status_history', $data), 'ticket_status_history.insert');
		return (int) $this->db->insert_id();
	}

	/** @param string $audience reporter|staff */
	public function messages($ticket_id, $audience = 'staff')
	{
		$this->db->select('m.*, u.display_name AS actor_name')
			->from('ticket_messages m')
			->join('users u', 'u.id = m.actor_user_id', 'left')
			->where('m.ticket_id', (int) $ticket_id);
		if ($audience === 'reporter')
		{
			$this->db->where('m.visibility', 'reporter');
		}
		return $this->db->order_by('m.created_at')->order_by('m.id')->get()->result();
	}

	public function history($ticket_id)
	{
		return $this->db->select('h.*, u.display_name AS actor_name')
			->from('ticket_status_history h')
			->join('users u', 'u.id = h.actor_user_id', 'left')
			->where('h.ticket_id', (int) $ticket_id)
			->order_by('h.created_at')->order_by('h.id')
			->get()->result();
	}

	public function attachments($ticket_id, $visibility = NULL)
	{
		$this->db->select('a.*, f.original_name, f.mime_type, f.byte_size, f.scan_status')
			->from('ticket_attachments a')
			->join('private_files f', 'f.id = a.private_file_id')
			->where('a.ticket_id', (int) $ticket_id);
		if ($visibility !== NULL)
		{
			$this->db->where('a.visibility', $visibility);
		}
		return $this->db->order_by('a.id')->get()->result();
	}

	public function attachment_by_file($private_file_id)
	{
		return $this->db->get_where('ticket_attachments', array('private_file_id' => (int) $private_file_id))->row();
	}

	public function assignments($ticket_id)
	{
		return $this->db->select('a.*, u.display_name AS assignee_name, b.display_name AS assigned_by_name, o.name AS unit_name')
			->from('ticket_assignments a')
			->join('users u', 'u.id = a.assignee_id', 'left')
			->join('users b', 'b.id = a.assigned_by', 'left')
			->join('organizational_units o', 'o.id = a.unit_id', 'left')
			->where('a.ticket_id', (int) $ticket_id)
			->order_by('a.assigned_at', 'DESC')->get()->result();
	}

	public function references($ticket_id)
	{
		return $this->db->where('ticket_id', (int) $ticket_id)->order_by('id')->get('ticket_references')->result();
	}

	public function conflicts($ticket_id)
	{
		return $this->db->select('c.*, u.display_name AS user_name, d.display_name AS declared_by_name')
			->from('ticket_conflicts c')
			->join('users u', 'u.id = c.user_id')
			->join('users d', 'd.id = c.declared_by', 'left')
			->where('c.ticket_id', (int) $ticket_id)->get()->result();
	}

	public function episode($ticket_id, $episode_no)
	{
		return $this->db->get_where('ticket_resolution_episodes', array('ticket_id' => (int) $ticket_id, 'episode_no' => (int) $episode_no))->row();
	}

	public function sla_instance($ticket_id, $episode_no)
	{
		return $this->db->get_where('ticket_sla_instances', array('ticket_id' => (int) $ticket_id, 'episode_no' => (int) $episode_no))->row();
	}

	public function feedback($ticket_id, $episode_no)
	{
		return $this->db->get_where('ticket_feedback', array('ticket_id' => (int) $ticket_id, 'handling_episode' => (int) $episode_no))->row();
	}

	/** Daftar laporan milik satu warga. */
	public function for_reporter($user_id, array $filters = array(), $limit = 20, $offset = 0)
	{
		$this->reporter_query($user_id, $filters);
		return $this->db->select('t.*, c.name AS category_name')
			->order_by('t.submitted_at', 'DESC')->limit((int) $limit, (int) $offset)->get()->result();
	}

	public function count_for_reporter($user_id, array $filters = array())
	{
		$this->reporter_query($user_id, $filters);
		return (int) $this->db->count_all_results();
	}

	protected function reporter_query($user_id, array $filters)
	{
		$this->db->from('tickets t')->join('ticket_categories c', 'c.id = t.category_id')
			->where('t.reporter_user_id', (int) $user_id);
		if ( ! empty($filters['status']) && isset(app_config('ticket_statuses', array())[$filters['status']]))
		{
			$this->db->where('t.status', $filters['status']);
		}
		if ( ! empty($filters['report_type']) && isset(app_config('report_types', array())[$filters['report_type']]))
		{
			$this->db->where('t.report_type', $filters['report_type']);
		}
		if ( ! empty($filters['from']))
		{
			$this->db->where('t.submitted_at >=', $filters['from']);
		}
		if ( ! empty($filters['to']))
		{
			$this->db->where('t.submitted_at <=', $filters['to']);
		}
	}

	public function reporter_summary($user_id)
	{
		$rows = $this->db->select('status, COUNT(*) AS total')->where('reporter_user_id', (int) $user_id)
			->group_by('status')->get('tickets')->result();
		$summary = array('total' => 0, 'active' => 0, 'awaiting' => 0, 'resolved' => 0);
		$terminal = app_config('ticket_terminal_statuses', array());
		foreach ($rows as $row)
		{
			$summary['total'] += (int) $row->total;
			if (in_array($row->status, array('awaiting_confirmation', 'needs_information'), TRUE))
			{
				$summary['awaiting'] += (int) $row->total;
			}
			if ($row->status === 'resolved')
			{
				$summary['resolved'] += (int) $row->total;
			}
			if ( ! in_array($row->status, $terminal, TRUE))
			{
				$summary['active'] += (int) $row->total;
			}
		}
		return $summary;
	}

	/** Pencarian akun warga untuk loket: hasil dibatasi dan dimasking di controller. */
	public function search_residents($keyword, $limit = 8)
	{
		$keyword = trim((string) $keyword);
		if (mb_strlen($keyword) < 3)
		{
			return array();
		}
		return $this->db->select('u.id, u.public_id, u.display_name, u.username, u.email, u.phone')
			->from('users u')
			->join('user_roles ur', 'ur.user_id = u.id')
			->join('roles r', 'r.id = ur.role_id')
			->where('r.code', 'resident')
			->where('u.account_status', 'active')
			->group_start()->like('u.username', $keyword)->or_like('u.display_name', $keyword)->group_end()
			->order_by('u.display_name')->limit((int) $limit)->get()->result();
	}
}
