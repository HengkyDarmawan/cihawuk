<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Query daftar dan ringkasan tiket untuk area pengelola.
 * Seluruh query wajib melewati AuthorizationService::apply_ticket_scope().
 * Kolom sort dan filter memakai allowlist; keyword tidak pernah digabung ke raw SQL.
 */
class Ticket_query_model extends CI_Model {

	/** Kolom yang boleh dipakai untuk pengurutan DataTables. */
	protected $sortable = array(
		0 => 't.public_code',
		1 => 't.title',
		2 => 't.report_type',
		3 => 't.status',
		4 => 'assignee_name',
		5 => 't.submitted_at',
	);

	protected function base_query($user_id, array $filters)
	{
		$this->db->from('tickets t')
			->join('ticket_categories c', 'c.id = t.category_id')
			->join('users u', 'u.id = t.assigned_user_id', 'left')
			->join('organizational_units o', 'o.id = t.assigned_unit_id', 'left');

		if ($this->authz->apply_ticket_scope($this->db, $user_id) === FALSE)
		{
			// Tidak punya akses daftar sama sekali.
			$this->db->where('1 = 0', NULL, FALSE);
			return FALSE;
		}

		$statuses = app_config('ticket_statuses', array());
		if ( ! empty($filters['status']) && isset($statuses[$filters['status']]))
		{
			$this->db->where('t.status', $filters['status']);
		}
		if ( ! empty($filters['report_type']) && isset(app_config('report_types', array())[$filters['report_type']]))
		{
			$this->db->where('t.report_type', $filters['report_type']);
		}
		if ( ! empty($filters['category_id']))
		{
			$this->db->where('t.category_id', (int) $filters['category_id']);
		}
		if ( ! empty($filters['channel']) && isset(app_config('intake_channels', array())[$filters['channel']]))
		{
			$this->db->where('t.intake_channel', $filters['channel']);
		}
		if ( ! empty($filters['priority']) && isset(app_config('priorities', array())[$filters['priority']]))
		{
			$this->db->where('t.priority', $filters['priority']);
		}
		if ( ! empty($filters['assignee_id']))
		{
			$this->db->where('t.assigned_user_id', (int) $filters['assignee_id']);
		}
		if ( ! empty($filters['unit_id']))
		{
			$this->db->where('t.assigned_unit_id', (int) $filters['unit_id']);
		}
		if ( ! empty($filters['mine']))
		{
			$this->db->where('t.assigned_user_id', (int) $user_id);
		}
		if ( ! empty($filters['unassigned']))
		{
			$this->db->where('t.assigned_user_id IS NULL', NULL, FALSE);
		}
		if ( ! empty($filters['from']))
		{
			$this->db->where('t.submitted_at >=', $filters['from'].' 00:00:00');
		}
		if ( ! empty($filters['to']))
		{
			$this->db->where('t.submitted_at <=', $filters['to'].' 23:59:59');
		}
		if ( ! empty($filters['overdue']))
		{
			$now = utc_now();
			$this->db->where('EXISTS (SELECT 1 FROM ticket_sla_instances s WHERE s.ticket_id = t.id AND s.episode_no = t.current_episode
				AND ((s.verification_met_at IS NULL AND s.verification_due_at < '.$this->db->escape($now).')
				  OR (s.first_response_met_at IS NULL AND s.first_response_due_at < '.$this->db->escape($now).')
				  OR (s.resolution_met_at IS NULL AND s.resolution_due_at < '.$this->db->escape($now).')))', NULL, FALSE);
		}
		if ( ! empty($filters['keyword']))
		{
			$keyword = mb_substr(trim((string) $filters['keyword']), 0, 100);
			// Pencarian memakai Query Builder (binding/escaped), bukan raw SQL.
			$this->db->group_start()
				->like('t.public_code', $keyword)
				->or_like('t.title', $keyword)
				->group_end();
		}
		return TRUE;
	}

	public function count_all($user_id, array $filters = array())
	{
		$this->base_query($user_id, $filters);
		return (int) $this->db->count_all_results();
	}

	public function listing($user_id, array $filters, $limit, $offset, $order_column = 5, $order_dir = 'desc')
	{
		$this->base_query($user_id, $filters);
		$order = $this->sortable[$order_column] ?? 't.submitted_at';
		$dir = (strtolower($order_dir) === 'asc') ? 'ASC' : 'DESC';
		return $this->db->select('t.id, t.public_code, t.title, t.report_type, t.status, t.priority, t.confidentiality,
				t.identity_mode, t.intake_channel, t.submitted_at, t.current_episode, t.assigned_user_id,
				c.name AS category_name, u.display_name AS assignee_name, o.name AS unit_name')
			->order_by($order, $dir)
			->limit((int) $limit, (int) $offset)
			->get()->result();
	}

	/** Jumlah tiket per status dalam lingkup pengguna. */
	public function status_counts($user_id, array $filters = array())
	{
		$this->base_query($user_id, $filters);
		$rows = $this->db->select('t.status, COUNT(*) AS total')->group_by('t.status')->get()->result();
		$counts = array();
		foreach ($rows as $row)
		{
			$counts[$row->status] = (int) $row->total;
		}
		return $counts;
	}

	public function count_overdue($user_id)
	{
		return $this->count_all($user_id, array('overdue' => 1));
	}

	/** Beban kerja petugas dalam lingkup monitoring. */
	public function workload($user_id, $limit = 10)
	{
		$this->base_query($user_id, array());
		return $this->db->select("u.id, u.display_name, COUNT(*) AS total,
				SUM(CASE WHEN t.status IN ('assigned','in_progress','needs_information') THEN 1 ELSE 0 END) AS aktif", FALSE)
			->where('t.assigned_user_id IS NOT NULL', NULL, FALSE)
			->group_by('u.id')->order_by('aktif', 'DESC')->limit((int) $limit)->get()->result();
	}

	/** Tren jumlah laporan masuk per bulan (berdasarkan submitted_at). */
	public function monthly_trend($user_id, $months = 6)
	{
		$from = $this->clock->now()->modify('-'.((int) $months - 1).' months')->format('Y-m-01 00:00:00');
		$this->base_query($user_id, array());
		$rows = $this->db->select("DATE_FORMAT(CONVERT_TZ(t.submitted_at, '+00:00', '+07:00'), '%Y-%m') AS bulan, COUNT(*) AS total", FALSE)
			->where('t.submitted_at >=', $from)
			->group_by('bulan')->order_by('bulan')->get()->result();
		$series = array();
		for ($i = (int) $months - 1; $i >= 0; $i--)
		{
			$key = $this->clock->now()->setTimezone(local_tz())->modify('-'.$i.' months')->format('Y-m');
			$series[$key] = 0;
		}
		foreach ($rows as $row)
		{
			if (array_key_exists($row->bulan, $series))
			{
				$series[$row->bulan] = (int) $row->total;
			}
		}
		return $series;
	}

	/** Distribusi kategori (untuk ringkasan pimpinan). */
	public function category_distribution($user_id, $limit = 8)
	{
		$this->base_query($user_id, array());
		return $this->db->select('c.name, COUNT(*) AS total')->group_by('c.id')->order_by('total', 'DESC')->limit((int) $limit)->get()->result();
	}

	/** Tiket dengan tenggat terdekat untuk petugas. */
	public function upcoming_deadlines($user_id, $limit = 5)
	{
		$this->base_query($user_id, array('mine' => 1));
		return $this->db->select('t.public_code, t.title, t.status, s.first_response_due_at, s.resolution_due_at, s.verification_due_at')
			->join('ticket_sla_instances s', 's.ticket_id = t.id AND s.episode_no = t.current_episode', 'left')
			->where_not_in('t.status', app_config('ticket_terminal_statuses', array()))
			->order_by('COALESCE(s.first_response_due_at, s.resolution_due_at, s.verification_due_at) ASC', '', FALSE)
			->limit((int) $limit)->get()->result();
	}

	/** Rekap untuk ekspor: iterasi chunk agar tidak memuat seluruh tabel. */
	public function each_for_export($user_id, array $filters, callable $callback, $chunk = 500)
	{
		$offset = 0;
		do
		{
			$rows = $this->listing($user_id, $filters, $chunk, $offset, 5, 'asc');
			foreach ($rows as $row)
			{
				$callback($row);
			}
			$offset += $chunk;
		}
		while (count($rows) === $chunk);
	}
}
