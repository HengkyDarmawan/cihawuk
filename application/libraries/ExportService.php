<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ekspor rekap operasional.
 *
 * - Izin dan lingkup diperiksa saat permintaan DAN saat eksekusi/unduh.
 * - Identitas pelapor tidak disertakan secara bawaan.
 * - Nilai pengguna diamankan dari formula injection (CSV/spreadsheet).
 * - Berkas hasil disimpan privat dengan nama acak dan masa berlaku terbatas.
 */
class ExportService {

	/** @var CI_Controller */
	protected $CI;

	public $ttl_seconds = 172800; // 2 hari

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->model(array('Ticket_query_model' => 'ticket_query', 'User_model' => 'user_model'));
		$this->CI->load->library('UploadService', NULL, 'uploads');
		$this->CI->load->library('SlaService', NULL, 'sla');
		$this->CI->load->library('AuthorizationService', NULL, 'authz');
	}

	public function report_types()
	{
		return array(
			'tickets' => 'Rekap laporan layanan',
			'sla' => 'Rekap ketepatan waktu (SLA)',
		);
	}

	/** Cegah formula injection pada CSV/spreadsheet. */
	public function safe_cell($value)
	{
		$value = (string) $value;
		if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value))
		{
			return "'".$value;
		}
		return $value;
	}

	public function request_export($user, $report_type, array $filters, $format = 'csv')
	{
		if ( ! isset($this->report_types()[$report_type]))
		{
			throw new DomainRuleException('Jenis rekap tidak dikenal.', 422);
		}
		if ( ! in_array($format, array('csv', 'pdf'), TRUE))
		{
			throw new DomainRuleException('Format ekspor tidak didukung.', 422);
		}
		if ( ! $this->CI->authz->user_can($user->id, 'tickets.export'))
		{
			throw new AccessDeniedException('Missing tickets.export');
		}
		$public_id = $this->CI->crypto->public_id();
		db_must($this->CI->db->insert('export_jobs', array(
			'public_id' => $public_id,
			'requested_by' => (int) $user->id,
			'report_type' => $report_type,
			'format' => $format,
			'filter_snapshot_json' => json_encode($filters, JSON_UNESCAPED_UNICODE),
			'permission_version' => (int) $user->auth_version,
			'status' => 'pending',
			'expires_at' => $this->CI->clock->plus_seconds($this->ttl_seconds),
			'created_at' => utc_now(),
		)), 'export_jobs.insert');
		$this->CI->audit->log('export.requested', 'export', $public_id, array('report_type' => $report_type, 'format' => $format));
		return $public_id;
	}

	public function find($public_id)
	{
		return $this->CI->db->get_where('export_jobs', array('public_id' => (string) $public_id))->row();
	}

	public function listing($user_id, $limit = 20)
	{
		return $this->CI->db->select('e.*, f.byte_size')
			->from('export_jobs e')->join('private_files f', 'f.id = e.private_file_id', 'left')
			->where('e.requested_by', (int) $user_id)
			->order_by('e.id', 'DESC')->limit((int) $limit)->get()->result();
	}

	/** Proses antrean ekspor (dipanggil job CLI). */
	public function process_pending($limit = 5)
	{
		$rows = $this->CI->db->where('status', 'pending')->order_by('id')->limit((int) $limit)->get('export_jobs')->result();
		$done = 0;
		$skipped = 0;
		foreach ($rows as $row)
		{
			$this->CI->db->where(array('id' => (int) $row->id, 'status' => 'pending'))->update('export_jobs', array('status' => 'processing'));
			if ($this->CI->db->affected_rows() !== 1)
			{
				continue;
			}
			$requester = $this->CI->user_model->find($row->requested_by);
			// Periksa ulang izin saat eksekusi: izin bisa dicabut setelah permintaan.
			if ( ! $requester OR $requester->account_status !== 'active' OR ! $this->CI->authz->user_can($requester->id, 'tickets.export'))
			{
				$this->CI->db->where('id', (int) $row->id)->update('export_jobs', array(
					'status' => 'denied', 'error_message' => 'Izin ekspor tidak lagi berlaku saat eksekusi.', 'completed_at' => utc_now(),
				));
				$this->CI->audit->log('export.denied_on_run', 'export', $row->public_id, array(), (int) $row->requested_by);
				$skipped++;
				continue;
			}
			try
			{
				$this->generate($row, $requester);
				$done++;
			}
			catch (Throwable $e)
			{
				log_message('error', 'Export '.$row->public_id.' failed: '.$e->getMessage());
				$this->CI->db->where('id', (int) $row->id)->update('export_jobs', array(
					'status' => 'failed', 'error_message' => substr($e->getMessage(), 0, 200), 'completed_at' => utc_now(),
				));
			}
		}
		return array('selesai' => $done, 'ditolak' => $skipped, 'antre' => count($rows));
	}

	protected function generate($job, $requester)
	{
		$filters = json_decode($job->filter_snapshot_json, TRUE) ?: array();
		$root = $this->CI->uploads->private_root();
		$relative = 'export/'.$this->CI->clock->now()->format('Y/m').'/'.$this->CI->crypto->random_hex(16);
		$dir = $root.'/'.dirname($relative);
		if ( ! is_dir($dir) && ! @mkdir($dir, 0750, TRUE))
		{
			throw new RuntimeException('Cannot create export directory');
		}
		$path = $root.'/'.$relative;
		$rows = 0;

		if ($job->format === 'csv')
		{
			$rows = $this->write_csv($job, $requester, $filters, $path);
			$mime = 'text/csv';
			$name = 'rekap-'.$job->report_type.'-'.$this->CI->clock->now()->setTimezone(local_tz())->format('Ymd-Hi').'.csv';
		}
		else
		{
			$rows = $this->write_pdf($job, $requester, $filters, $path);
			$mime = 'application/pdf';
			$name = 'rekap-'.$job->report_type.'-'.$this->CI->clock->now()->setTimezone(local_tz())->format('Ymd-Hi').'.pdf';
		}
		@chmod($path, 0640);

		db_must($this->CI->db->insert('private_files', array(
			'storage_key' => $relative,
			'original_name' => $name,
			'mime_type' => $mime,
			'byte_size' => (int) filesize($path),
			'checksum' => hash_file('sha256', $path),
			'scan_status' => 'not_scanned',
			'purpose' => 'export',
			'uploaded_by' => (int) $requester->id,
			'created_at' => utc_now(),
			'expires_at' => $this->CI->clock->plus_seconds($this->ttl_seconds),
		)), 'private_files.export');
		$file_id = (int) $this->CI->db->insert_id();

		$this->CI->db->where('id', (int) $job->id)->update('export_jobs', array(
			'status' => 'ready', 'row_count' => $rows, 'private_file_id' => $file_id, 'completed_at' => utc_now(),
		));
		$this->CI->notifications->notify($requester->id, 'export.ready',
			'Rekap yang Anda minta sudah siap diunduh.', 'export', $job->public_id, '/admin/ekspor');
	}

	protected function write_csv($job, $requester, array $filters, $path)
	{
		$handle = fopen($path, 'wb');
		if ($handle === FALSE)
		{
			throw new RuntimeException('Cannot write export file');
		}
		// BOM agar Excel membaca UTF-8 dengan benar.
		fwrite($handle, "\xEF\xBB\xBF");
		$meta = array(
			array('Rekap', $this->report_types()[$job->report_type]),
			array('Dibuat', format_wib(utc_now())),
			array('Diminta oleh', $requester->display_name),
			array('Filter', json_encode($filters, JSON_UNESCAPED_UNICODE)),
			array('Catatan', 'Tanpa identitas pelapor. Waktu ditampilkan dalam WIB. Lingkup data mengikuti izin pemohon.'),
			array(),
		);
		foreach ($meta as $line)
		{
			fputcsv($handle, array_map(array($this, 'safe_cell'), $line));
		}

		if ($job->report_type === 'sla')
		{
			fputcsv($handle, array('Nomor tiket', 'Status', 'Kategori', 'Diterima (WIB)', 'Tenggat verifikasi', 'Verifikasi tercapai', 'Tenggat respons awal', 'Respons awal tercapai', 'Terlambat?'));
		}
		else
		{
			fputcsv($handle, array('Nomor tiket', 'Jenis', 'Kategori', 'Status', 'Kanal', 'Prioritas', 'Unit', 'Penanggung jawab', 'Diterima (WIB)', 'Episode'));
		}

		$count = 0;
		$this->CI->ticket_query->each_for_export($requester->id, $filters, function ($row) use ($handle, $job, &$count) {
			if ($job->report_type === 'sla')
			{
				$ticket = $this->CI->db->get_where('tickets', array('id' => (int) $row->id))->row();
				$status = $this->CI->sla->status($ticket);
				$line = array(
					$row->public_code, config_label('ticket_statuses', $row->status), $row->category_name,
					format_wib($row->submitted_at, 'short'),
					$status ? format_wib($status['verification']['due_at'] ?? NULL, 'short') : '—',
					$status ? format_wib($status['verification']['met_at'] ?? NULL, 'short') : '—',
					$status ? format_wib($status['first_response']['due_at'] ?? NULL, 'short') : '—',
					$status ? format_wib($status['first_response']['met_at'] ?? NULL, 'short') : '—',
					($status && $this->CI->sla->is_overdue($ticket)) ? 'ya' : 'tidak',
				);
			}
			else
			{
				$line = array(
					$row->public_code,
					config_label('report_types', $row->report_type),
					$row->category_name,
					config_label('ticket_statuses', $row->status),
					config_label('intake_channels', $row->intake_channel),
					config_label('priorities', $row->priority),
					$row->unit_name,
					$row->assignee_name,
					format_wib($row->submitted_at, 'short'),
					$row->current_episode,
				);
			}
			fputcsv($handle, array_map(array($this, 'safe_cell'), $line));
			$count++;
		}, 500);

		fclose($handle);
		return $count;
	}

	protected function write_pdf($job, $requester, array $filters, $path)
	{
		$counts = $this->CI->ticket_query->status_counts($requester->id, $filters);
		$total = array_sum($counts);
		$html = $this->CI->load->view('pdf/rekap_laporan', array(
			'title' => $this->report_types()[$job->report_type],
			'generated_at' => format_wib(utc_now()),
			'requester' => $requester->display_name,
			'filters' => $filters,
			'counts' => $counts,
			'total' => $total,
			'overdue' => $this->CI->ticket_query->count_overdue($requester->id),
			'categories' => $this->CI->ticket_query->category_distribution($requester->id, 10),
		), TRUE);

		$options = new Dompdf\Options();
		$options->set('isRemoteEnabled', FALSE);   // tidak mengambil URL eksternal
		$options->set('isHtml5ParserEnabled', TRUE);
		$options->set('chroot', ROOTPATH.'application/views/pdf');
		$dompdf = new Dompdf\Dompdf($options);
		$dompdf->loadHtml($html, 'UTF-8');
		$dompdf->setPaper('A4', 'portrait');
		$dompdf->render();
		file_put_contents($path, $dompdf->output());
		return $total;
	}

	/** Unduh berkas ekspor setelah pemeriksaan izin terkini. */
	public function download($public_id, $user)
	{
		$job = $this->find($public_id);
		if ( ! $job OR (int) $job->requested_by !== (int) $user->id)
		{
			throw new DomainRuleException('Berkas ekspor tidak ditemukan.', 404);
		}
		if ( ! $this->CI->authz->user_can($user->id, 'tickets.export'))
		{
			throw new AccessDeniedException('Export permission revoked');
		}
		if ($job->status !== 'ready' OR $job->private_file_id === NULL)
		{
			throw new DomainRuleException('Berkas ekspor belum siap atau sudah kedaluwarsa.', 409);
		}
		if (strtotime($job->expires_at.' UTC') < $this->CI->clock->timestamp())
		{
			throw new DomainRuleException('Tautan ekspor sudah kedaluwarsa. Ajukan ulang bila masih diperlukan.', 410);
		}
		$file = $this->CI->uploads->file($job->private_file_id);
		$this->CI->audit->log('export.downloaded', 'export', $job->public_id, array('report_type' => $job->report_type));
		$this->CI->uploads->send_download($file);
	}
}
