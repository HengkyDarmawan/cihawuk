<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Log audit (append-only). Tidak ada tombol edit/hapus di aplikasi.
 * Peristiwa operasional ditampilkan terpisah sebagai bahan tindak lanjut.
 */
class Audit extends Admin_Controller {

	public function index()
	{
		$this->require_permission('audit.view');
		$action = mb_substr(trim((string) $this->input->get('aksi')), 0, 80);
		$entity = mb_substr(trim((string) $this->input->get('entitas')), 0, 50);
		$module = mb_substr(trim((string) $this->input->get('modul')), 0, 50);
		$request_id = mb_substr(trim((string) $this->input->get('request')), 0, 32);
		$page = max(1, (int) $this->input->get('hal'));
		$per_page = 50;

		$build = function () use ($action, $entity, $module, $request_id) {
			$this->db->from('audit_logs a')->join('users u', 'u.id = a.actor_user_id', 'left');
			if ($action !== '')
			{
				$this->db->like('a.action', $action);
			}
			if ($entity !== '')
			{
				$this->db->where('a.entity_type', $entity);
			}
			if ($module !== '')
			{
				$this->db->where('a.module_code', $module);
			}
			if ($request_id !== '')
			{
				$this->db->where('a.request_id', $request_id);
			}
		};
		$build();
		$total = (int) $this->db->count_all_results();
		$build();
		$rows = $this->db->select('a.*, u.display_name AS actor_name')
			->order_by('a.id', 'DESC')->limit($per_page, ($page - 1) * $per_page)->get()->result();

		// Daftar modul yang benar-benar muncul pada log, bukan daftar tetap di kode.
		$modules = array('' => 'Semua modul');
		foreach ($this->db->select('module_code')->distinct()->where('module_code IS NOT NULL', NULL, FALSE)
			->order_by('module_code')->get('audit_logs')->result() as $row)
		{
			$modules[$row->module_code] = $row->module_code;
		}

		$events = $this->db->select('e.*, u.display_name AS actor_name')->from('admin_activity_events e')
			->join('users u', 'u.id = e.actor_user_id', 'left')
			->order_by('e.id', 'DESC')->limit(10)->get()->result();

		$this->render('admin/audit', array(
			'page_title' => 'Log Audit',
			'nav_active' => 'audit',
			'rows' => $rows,
			'events' => $events,
			'entities' => array('' => 'Semua entitas', 'ticket' => 'Tiket', 'user' => 'Pengguna', 'private_file' => 'Berkas privat', 'content' => 'Konten', 'media' => 'Media', 'feature_module' => 'Modul', 'setting' => 'Pengaturan', 'export' => 'Ekspor'),
			'modules' => $modules,
			'filters' => array('aksi' => $action, 'entitas' => $entity, 'modul' => $module, 'request' => $request_id),
			'page' => $page,
			'pages' => max(1, (int) ceil($total / $per_page)),
			'total' => $total,
		), 'dashboard');
	}
}
