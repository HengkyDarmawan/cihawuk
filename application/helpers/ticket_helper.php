<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Helper tampilan tiket: timeline gabungan riwayat status dan pesan. */

if ( ! function_exists('ticket_timeline'))
{
	/**
	 * Gabungkan riwayat status dan pesan menjadi satu urutan waktu.
	 *
	 * @param string $audience reporter|staff — pelapor tidak melihat catatan internal
	 *                        maupun istilah internal (prioritas, unit, dsb).
	 * @return array daftar item {at, icon, title, body, meta, visibility}
	 */
	function ticket_timeline(array $history, array $messages, $audience = 'reporter')
	{
		$statuses = app_config('ticket_statuses', array());
		$items = array();

		$action_titles = array(
			'submit' => 'Laporan diterima sistem',
			'start_verification' => 'Petugas memulai verifikasi',
			'request_information' => 'Petugas meminta kelengkapan',
			'assign' => 'Laporan diteruskan ke petugas',
			'reject' => 'Laporan dinyatakan tidak dapat diproses',
			'refer' => 'Laporan dirujuk ke layanan lain',
			'accept_work' => 'Petugas mulai menangani',
			'propose_resolution' => 'Petugas menyampaikan hasil',
			'close_by_policy' => 'Laporan ditutup oleh petugas berwenang',
			'resume_work' => 'Penanganan dilanjutkan',
			'reopen' => 'Laporan dibuka kembali',
			'accept_result' => 'Pelapor menerima hasil',
			'request_followup' => 'Pelapor meminta tindak lanjut',
			'provide_information' => 'Pelapor melengkapi informasi',
			'withdraw' => 'Laporan ditarik pelapor',
			'request_withdrawal' => 'Pelapor meminta penarikan',
			'claim_by_account' => 'Laporan dikaitkan ke akun pelapor',
			'follow_up' => 'Catatan tindak lanjut',
			'reply' => 'Balasan pelapor',
		);

		foreach ($history as $row)
		{
			// Riwayat tanpa perubahan status ditampilkan melalui pesannya sendiri.
			if ($row->from_status === $row->to_status && in_array($row->action, array('follow_up', 'reply'), TRUE))
			{
				continue;
			}
			$title = $action_titles[$row->action] ?? ('Status menjadi '.($statuses[$row->to_status]['label'] ?? $row->to_status));
			$meta = array();
			if ($audience === 'staff' && $row->actor_name)
			{
				$meta[] = $row->actor_name;
			}
			elseif ($row->actor_type === 'staff')
			{
				$meta[] = 'Petugas desa';
			}
			$items[] = array(
				'at' => $row->created_at,
				'icon' => $statuses[$row->to_status]['icon'] ?? 'circle',
				'title' => $title,
				'body' => ($audience === 'staff' OR $row->action !== 'follow_up') ? $row->reason : NULL,
				'meta' => implode(' · ', $meta),
				'visibility' => 'reporter',
				'status' => $row->to_status,
			);
		}

		$kind_titles = array(
			'question' => 'Pertanyaan petugas',
			'result' => 'Hasil penanganan',
			'decision' => 'Keputusan petugas',
			'follow_up' => 'Tindak lanjut petugas',
			'reply' => 'Balasan pelapor',
			'information' => 'Kelengkapan dari pelapor',
			'confirmation' => 'Tanggapan pelapor',
			'followup_request' => 'Permintaan tindak lanjut',
			'withdrawal' => 'Penarikan laporan',
			'withdrawal_request' => 'Permintaan penarikan',
			'reopen' => 'Pembukaan kembali',
		);
		foreach ($messages as $msg)
		{
			if ($audience === 'reporter' && $msg->visibility !== 'reporter')
			{
				continue;
			}
			$who = in_array($msg->actor_type, array('resident', 'anonymous'), TRUE) ? 'Pelapor' : 'Petugas desa';
			if ($audience === 'staff' && $msg->actor_name && $msg->actor_type === 'staff')
			{
				$who = $msg->actor_name;
			}
			$items[] = array(
				'at' => $msg->created_at,
				'icon' => ($msg->visibility === 'internal') ? 'lock' : 'message-square',
				'title' => $kind_titles[$msg->message_kind] ?? 'Pesan',
				'body' => $msg->message,
				'meta' => $who.(($msg->visibility === 'internal') ? ' · catatan internal' : ''),
				'visibility' => $msg->visibility,
				'status' => NULL,
			);
		}

		usort($items, function ($a, $b) {
			return strcmp($a['at'], $b['at']);
		});
		return $items;
	}
}

if ( ! function_exists('ticket_timeline_html'))
{
	function ticket_timeline_html(array $items, $audience = 'reporter')
	{
		if (empty($items))
		{
			return '<p class="text-muted">Belum ada aktivitas.</p>';
		}
		$html = '<ol class="timeline">';
		foreach ($items as $item)
		{
			$internal = ($item['visibility'] === 'internal');
			$html .= '<li>';
			$html .= '<span class="tl-dot'.($internal ? ' is-internal' : '').'">'.icon($item['icon']).'</span>';
			$html .= '<div class="tl-title">'.e($item['title']).'</div>';
			$html .= '<div class="tl-meta">'.e(format_wib($item['at'])).($item['meta'] !== '' ? ' · '.e($item['meta']) : '').'</div>';
			if ( ! empty($item['body']))
			{
				$html .= '<div class="tl-body'.($internal ? ' is-internal' : ($audience === 'reporter' ? ' is-reporter' : '')).'">'.e($item['body']).'</div>';
			}
			$html .= '</li>';
		}
		return $html.'</ol>';
	}
}

if ( ! function_exists('ticket_attachment_list'))
{
	function ticket_attachment_list(array $attachments, $note = TRUE)
	{
		if (empty($attachments))
		{
			return '<p class="text-muted mb-0">Tidak ada lampiran.</p>';
		}
		$html = '<ul class="list-unstyled mb-0">';
		foreach ($attachments as $file)
		{
			$html .= '<li class="mb-2 d-flex gap-2 align-items-center">'.icon('paperclip')
				.'<a href="'.e(site_url('berkas/privat/'.(int) $file->private_file_id)).'">'.e($file->original_name).'</a>'
				.'<span class="text-muted small">'.e(format_bytes_id($file->byte_size)).'</span>';
			if ($file->visibility === 'internal')
			{
				$html .= '<span class="chip-flag is-warning">internal</span>';
			}
			$html .= '</li>';
		}
		$html .= '</ul>';
		if ($note)
		{
			$html .= '<p class="form-text mb-0">Berkas diunduh sebagai lampiran dan tidak dipindai antivirus otomatis; buka dengan aplikasi tepercaya.</p>';
		}
		return $html;
	}
}
