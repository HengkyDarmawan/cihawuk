<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Alur publikasi halaman CMS (modul-backend §5, §17).
 *
 * draft → in_review → (changes_requested → draft) → approved → (scheduled) → published
 * published → unpublished → published, dan published/unpublished → archived.
 *
 * Frontend publik hanya membaca `cms_publication_snapshots` yang aktif. Mengedit draft tidak
 * pernah mengubah halaman publik; rollback membuat publikasi baru, bukan menghapus riwayat.
 */
class CmsPublicationService {

	/** @var CI_Controller */
	protected $CI;

	/** Transisi status yang diizinkan. */
	const ALLOWED = array(
		'submit_review' => array('from' => array('draft', 'changes_requested'), 'to' => 'in_review'),
		'request_changes' => array('from' => array('in_review'), 'to' => 'changes_requested'),
		'approve' => array('from' => array('in_review'), 'to' => 'approved'),
		'publish' => array('from' => array('approved', 'scheduled', 'unpublished', 'published'), 'to' => 'published'),
		'schedule' => array('from' => array('approved'), 'to' => 'scheduled'),
		'unpublish' => array('from' => array('published'), 'to' => 'unpublished'),
		'archive' => array('from' => array('published', 'unpublished', 'draft', 'changes_requested'), 'to' => 'archived'),
	);

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->library('CmsService', NULL, 'cms');
		$this->CI->load->library('ContentService', NULL, 'content_service');
		$this->CI->load->library('PublicCache', NULL, 'public_cache');
	}

	protected function guard($action, $page)
	{
		if ( ! isset(self::ALLOWED[$action]))
		{
			throw new DomainRuleException('Tindakan tidak dikenal.', 400);
		}
		if ( ! in_array($page->status, self::ALLOWED[$action]['from'], TRUE))
		{
			throw new DomainRuleException('Tindakan ini tidak tersedia pada status halaman saat ini ('.$page->status.').', 409);
		}
	}

	protected function set_status($page, $status, array $extra = array())
	{
		db_must($this->CI->db->where('id', (int) $page->id)->update('cms_pages', array_merge(array(
			'status' => $status,
			'updated_at' => utc_now(),
		), $extra)), 'cms_pages.status');
	}

	// ------------------------------------------------------------------
	// Review
	// ------------------------------------------------------------------

	public function submit_review($page, $user_id)
	{
		$this->guard('submit_review', $page);
		if ( ! $page->current_version_id)
		{
			throw new DomainRuleException('Halaman belum memiliki versi draft.', 409);
		}
		$now = utc_now();
		db_transaction(function () use ($page, $user_id, $now) {
			$this->set_status($page, 'in_review');
			db_must($this->CI->db->insert('content_review_requests', array(
				'object_type' => 'cms_page',
				'object_id' => (int) $page->id,
				'version_no' => (int) ($this->CI->cms->version($page->current_version_id)->version_no ?? 1),
				'status' => 'open',
				'submitted_by' => (int) $user_id,
				'submitted_at' => $now,
			)), 'content_review_requests.insert');
		});
		$this->CI->audit->log('cms.submitted_review', 'cms_page', $page->public_id, array(), FALSE, 'cms');
	}

	/** Penolakan wajib disertai komentar yang dapat ditindaklanjuti. */
	public function request_changes($page, $comment, $user_id)
	{
		$this->guard('request_changes', $page);
		$comment = trim((string) $comment);
		if (mb_strlen($comment) < 10)
		{
			throw new DomainRuleException('Tuliskan komentar perbaikan (minimal 10 karakter).', 422, array('comment' => 'Komentar wajib diisi.'));
		}
		$request = $this->open_request($page);
		$now = utc_now();
		db_transaction(function () use ($page, $request, $comment, $user_id, $now) {
			$this->set_status($page, 'changes_requested');
			if ($request)
			{
				db_must($this->CI->db->where('id', (int) $request->id)->update('content_review_requests', array(
					'status' => 'changes_requested', 'reviewed_by' => (int) $user_id, 'reviewed_at' => $now,
				)), 'content_review_requests.update');
				db_must($this->CI->db->insert('content_review_comments', array(
					'request_id' => (int) $request->id,
					'comment' => mb_substr($comment, 0, 1000),
					'resolution' => 'open',
					'actor_user_id' => (int) $user_id,
					'created_at' => $now,
				)), 'content_review_comments.insert');
			}
		});
		$this->CI->audit->log('cms.changes_requested', 'cms_page', $page->public_id, array(), FALSE, 'cms');
	}

	public function approve($page, $user_id)
	{
		$this->guard('approve', $page);
		$request = $this->open_request($page);
		$now = utc_now();
		db_transaction(function () use ($page, $request, $user_id, $now) {
			$this->set_status($page, 'approved');
			if ($request)
			{
				db_must($this->CI->db->where('id', (int) $request->id)->update('content_review_requests', array(
					'status' => 'approved', 'reviewed_by' => (int) $user_id, 'reviewed_at' => $now,
				)), 'content_review_requests.approve');
			}
		});
		$this->CI->audit->log('cms.approved', 'cms_page', $page->public_id, array(), FALSE, 'cms');
	}

	public function open_request($page)
	{
		return $this->CI->db->where(array('object_type' => 'cms_page', 'object_id' => (int) $page->id, 'status' => 'open'))
			->order_by('id', 'DESC')->limit(1)->get('content_review_requests')->row();
	}

	public function review_history($page, $limit = 10)
	{
		return $this->CI->db->select('r.*, u.display_name AS submitter, c.comment, c.created_at AS comment_at')
			->from('content_review_requests r')
			->join('users u', 'u.id = r.submitted_by', 'left')
			->join('content_review_comments c', 'c.request_id = r.id', 'left')
			->where(array('r.object_type' => 'cms_page', 'r.object_id' => (int) $page->id))
			->order_by('r.id', 'DESC')->limit((int) $limit)->get()->result();
	}

	// ------------------------------------------------------------------
	// Publikasi
	// ------------------------------------------------------------------

	/**
	 * Susun snapshot dari draft saat ini: versi halaman + section aktif beserta konfigurasinya.
	 * Nilai bisnis tidak ikut disalin; hanya rujukan yang dipakai saat render.
	 */
	public function build_snapshot($page)
	{
		$version = $page->current_version_id ? $this->CI->cms->version($page->current_version_id) : NULL;
		if ( ! $version)
		{
			throw new DomainRuleException('Halaman belum memiliki versi untuk diterbitkan.', 409);
		}
		$sections = array();
		foreach ($this->CI->cms->sections($page->id) as $section)
		{
			if ( ! $section->is_enabled OR $section->archived_at !== NULL)
			{
				continue;
			}
			if ( ! $this->CI->cms->section_available($section->section_type))
			{
				// Modul mati: section tidak ikut diterbitkan, tetapi tetap tersimpan sebagai draft.
				continue;
			}
			$sections[] = array(
				'public_id' => $section->public_id,
				'type' => $section->section_type,
				'title' => $section->title,
				'subtitle' => $section->subtitle,
				'layout' => $section->layout_variant,
				'config' => $section->config,
				'version_no' => (int) $section->version_no,
			);
		}

		return array(
			'page' => array(
				'public_id' => $page->public_id,
				'page_key' => $page->page_key,
				'version_no' => (int) $version->version_no,
				'title' => $version->title,
				'nav_title' => $version->nav_title,
				'slug' => $version->slug,
				'summary' => $version->summary,
				'template_code' => $version->template_code,
				'seo_title' => $version->seo_title,
				'seo_description' => $version->seo_description,
				'cover_media_id' => $version->cover_media_id ? (int) $version->cover_media_id : NULL,
				'search_indexable' => (int) $version->search_indexable,
			),
			'sections' => $sections,
		);
	}

	public function publish($page, $reason, $user_id)
	{
		$this->guard('publish', $page);
		$snapshot = $this->build_snapshot($page);
		$now = utc_now();
		$revision = NULL;

		db_transaction(function () use ($page, $snapshot, $reason, $user_id, $now, &$revision) {
			$revision = (int) ($this->CI->db->select_max('revision_no')
				->where(array('target_type' => 'page', 'target_id' => (int) $page->id))
				->get('cms_publication_snapshots')->row('revision_no') ?: 0) + 1;

			// Snapshot lama tetap tersimpan, hanya ditandai tidak aktif.
			db_must($this->CI->db->where(array('target_type' => 'page', 'target_id' => (int) $page->id))
				->where('superseded_at IS NULL', NULL, FALSE)
				->update('cms_publication_snapshots', array('superseded_at' => $now)), 'cms_snapshots.supersede');

			db_must($this->CI->db->insert('cms_publication_snapshots', array(
				'target_type' => 'page',
				'target_id' => (int) $page->id,
				'page_version_id' => (int) $page->current_version_id,
				'revision_no' => $revision,
				'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
				'reason' => ($reason === '' OR $reason === NULL) ? NULL : mb_substr((string) $reason, 0, 500),
				'published_by' => (int) $user_id,
				'published_at' => $now,
			)), 'cms_snapshots.insert');

			$this->set_status($page, 'published', array(
				'published_version_id' => (int) $page->current_version_id,
				'published_at' => $now,
			));
			$this->CI->db->where(array('object_type' => 'cms_page', 'object_id' => (int) $page->id, 'status' => 'pending'))
				->update('scheduled_publications', array('status' => 'done', 'executed_at' => $now));
		});

		$this->after_publication_change($page, 'cms.published', array('revision' => $revision));
		return $revision;
	}

	/** Penjadwalan menyimpan waktu WIB sebagai UTC dan idempotency key per halaman/versi/waktu. */
	public function schedule($page, $local_datetime, $reason, $user_id)
	{
		$this->guard('schedule', $page);
		$local = trim((string) $local_datetime);
		if ( ! preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}$/', $local))
		{
			throw new DomainRuleException('Format waktu tidak valid. Gunakan tanggal dan jam.', 422, array('run_at' => 'Waktu tidak valid.'));
		}
		$run_at = local_to_utc(str_replace('T', ' ', $local).':00');
		if ($run_at <= utc_now())
		{
			throw new DomainRuleException('Waktu penjadwalan harus di masa depan.', 422, array('run_at' => 'Pilih waktu yang akan datang.'));
		}
		$key = hash('sha256', 'cms_page:'.$page->id.':'.$page->current_version_id.':'.$run_at);
		$now = utc_now();
		db_transaction(function () use ($page, $run_at, $reason, $user_id, $key, $now) {
			$this->CI->db->query(
				'INSERT IGNORE INTO scheduled_publications
				 (object_type, object_id, action, run_at, status, idempotency_key, reason, requested_by, created_at)
				 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
				array('cms_page', (int) $page->id, 'publish', $run_at, 'pending', $key,
					($reason === '') ? NULL : mb_substr((string) $reason, 0, 500), (int) $user_id, $now)
			);
			$this->set_status($page, 'scheduled');
		});
		$this->CI->audit->log('cms.scheduled', 'cms_page', $page->public_id, array('run_at' => $run_at), FALSE, 'cms');
		return $run_at;
	}

	/**
	 * Jalankan publikasi terjadwal yang sudah jatuh tempo. Klaim baris bersifat atomik sehingga
	 * dua scheduler bersamaan tidak menerbitkan dua kali.
	 */
	public function run_due_schedules($limit = 20)
	{
		$now = utc_now();
		$summary = array('diterbitkan' => 0, 'gagal' => 0);
		$rows = $this->CI->db->where('status', 'pending')->where('run_at <=', $now)
			->order_by('id')->limit((int) $limit)->get('scheduled_publications')->result();

		foreach ($rows as $row)
		{
			$this->CI->db->where(array('id' => (int) $row->id, 'status' => 'pending'))
				->update('scheduled_publications', array('status' => 'running'));
			if ($this->CI->db->affected_rows() !== 1)
			{
				continue;
			}
			$page = $this->CI->db->get_where('cms_pages', array('id' => (int) $row->object_id))->row();
			try
			{
				if ( ! $page)
				{
					throw new DomainRuleException('Halaman tidak ditemukan.', 404);
				}
				$this->publish($page, $row->reason ?: 'Publikasi terjadwal', $row->requested_by);
				$this->CI->db->where('id', (int) $row->id)->update('scheduled_publications', array(
					'status' => 'done', 'executed_at' => utc_now(), 'last_error' => NULL,
				));
				$summary['diterbitkan']++;
			}
			catch (Throwable $e)
			{
				// Kegagalan job tidak boleh membuat status halaman menjadi terbit.
				$this->CI->db->where('id', (int) $row->id)->update('scheduled_publications', array(
					'status' => 'failed', 'executed_at' => utc_now(), 'last_error' => mb_substr($e->getMessage(), 0, 255),
				));
				$this->CI->audit->event('error', 'Publikasi terjadwal gagal untuk halaman #'.$row->object_id.'.', 'cms', array(
					'schedule_id' => (int) $row->id,
				), NULL);
				$summary['gagal']++;
			}
		}
		return $summary;
	}

	public function unpublish($page, $reason, $user_id)
	{
		$this->guard('unpublish', $page);
		$reason = trim((string) $reason);
		if (mb_strlen($reason) < 10)
		{
			throw new DomainRuleException('Tuliskan alasan penarikan publikasi (minimal 10 karakter).', 422, array('reason' => 'Alasan wajib diisi.'));
		}
		$now = utc_now();
		db_transaction(function () use ($page, $now) {
			db_must($this->CI->db->where(array('target_type' => 'page', 'target_id' => (int) $page->id))
				->where('superseded_at IS NULL', NULL, FALSE)
				->update('cms_publication_snapshots', array('superseded_at' => $now)), 'cms_snapshots.unpublish');
			$this->set_status($page, 'unpublished', array('published_at' => NULL));
		});
		$this->after_publication_change($page, 'cms.unpublished', array('reason' => mb_substr($reason, 0, 200)));
	}

	public function archive($page, $reason, $user_id)
	{
		$this->guard('archive', $page);
		if ($page->is_system)
		{
			throw new DomainRuleException('Halaman sistem tidak dapat diarsipkan.', 409);
		}
		$now = utc_now();
		db_transaction(function () use ($page, $now) {
			$this->CI->db->where(array('target_type' => 'page', 'target_id' => (int) $page->id))
				->where('superseded_at IS NULL', NULL, FALSE)
				->update('cms_publication_snapshots', array('superseded_at' => $now));
			$this->set_status($page, 'archived', array('archived_at' => $now, 'published_at' => NULL));
		});
		$this->after_publication_change($page, 'cms.archived', array('reason' => mb_substr((string) $reason, 0, 200)));
	}

	/** Rollback menerbitkan ulang isi snapshot lama sebagai revisi baru; riwayat tidak dihapus. */
	public function rollback($page, $snapshot_id, $reason, $user_id)
	{
		$reason = trim((string) $reason);
		if (mb_strlen($reason) < 10)
		{
			throw new DomainRuleException('Tuliskan alasan rollback (minimal 10 karakter).', 422, array('reason' => 'Alasan wajib diisi.'));
		}
		$source = $this->CI->db->get_where('cms_publication_snapshots', array(
			'id' => (int) $snapshot_id, 'target_type' => 'page', 'target_id' => (int) $page->id,
		))->row();
		if ( ! $source)
		{
			throw new DomainRuleException('Versi publikasi tidak ditemukan.', 404);
		}

		$now = utc_now();
		$revision = NULL;
		db_transaction(function () use ($page, $source, $reason, $user_id, $now, &$revision) {
			$revision = (int) ($this->CI->db->select_max('revision_no')
				->where(array('target_type' => 'page', 'target_id' => (int) $page->id))
				->get('cms_publication_snapshots')->row('revision_no') ?: 0) + 1;
			db_must($this->CI->db->where(array('target_type' => 'page', 'target_id' => (int) $page->id))
				->where('superseded_at IS NULL', NULL, FALSE)
				->update('cms_publication_snapshots', array('superseded_at' => $now)), 'cms_snapshots.supersede');
			db_must($this->CI->db->insert('cms_publication_snapshots', array(
				'target_type' => 'page',
				'target_id' => (int) $page->id,
				'page_version_id' => $source->page_version_id,
				'revision_no' => $revision,
				'snapshot_json' => $source->snapshot_json,
				'reason' => mb_substr($reason, 0, 500),
				'rolled_back_from' => (int) $source->id,
				'published_by' => (int) $user_id,
				'published_at' => $now,
			)), 'cms_snapshots.rollback');
			$this->set_status($page, 'published', array(
				'published_version_id' => $source->page_version_id,
				'published_at' => $now,
			));
		});
		$this->after_publication_change($page, 'cms.rolled_back', array(
			'revision' => $revision, 'from_revision' => (int) $source->revision_no,
		));
		return $revision;
	}

	protected function after_publication_change($page, $action, array $meta)
	{
		$this->CI->audit->log($action, 'cms_page', $page->public_id, $meta, FALSE, 'cms');
		$this->CI->audit->event('info', 'Halaman "'.$page->page_key.'": '.$action.'.', 'cms', $meta);
		// Invalidasi terarah: hanya halaman itu dan daftar turunannya (sitemap, pencarian),
		// bukan seluruh cache publik. Mengubah draft tidak menyentuh cache sama sekali.
		$this->CI->public_cache->invalidate_page($page->page_key);
	}

	// ------------------------------------------------------------------
	// Pembacaan publik dan preview
	// ------------------------------------------------------------------

	public function snapshots($page, $limit = 10)
	{
		return $this->CI->db->select('s.*, u.display_name AS publisher')
			->from('cms_publication_snapshots s')->join('users u', 'u.id = s.published_by', 'left')
			->where(array('s.target_type' => 'page', 's.target_id' => (int) $page->id))
			->order_by('s.revision_no', 'DESC')->limit((int) $limit)->get()->result();
	}

	/** Snapshot aktif untuk halaman publik; NULL bila belum/tidak lagi terbit. */
	public function published_layout($page_key)
	{
		$cached = $this->CI->public_cache->get('page', (string) $page_key);
		if (is_array($cached))
		{
			return $cached;
		}
		$row = $this->CI->db->select('s.snapshot_json, s.revision_no, s.published_at')
			->from('cms_publication_snapshots s')->join('cms_pages p', 'p.id = s.target_id')
			->where('p.page_key', (string) $page_key)
			->where('s.target_type', 'page')->where('s.superseded_at IS NULL', NULL, FALSE)
			->where('p.status', 'published')
			->order_by('s.revision_no', 'DESC')->limit(1)->get()->row();
		if ( ! $row)
		{
			return NULL;
		}
		$layout = json_decode((string) $row->snapshot_json, TRUE);
		if ( ! is_array($layout) OR empty($layout['page']))
		{
			return NULL;
		}
		$layout['revision_no'] = (int) $row->revision_no;
		$layout['published_at'] = $row->published_at;
		// Yang di-cache hanya susunan snapshot; data bisnis tetap diambil saat render.
		$this->CI->public_cache->set('page', (string) $page_key, $layout, 1800);
		return $layout;
	}

	/** Susunan draft untuk preview; sama bentuknya dengan snapshot agar template identik. */
	public function draft_layout($page)
	{
		$layout = $this->build_snapshot($page);
		$layout['preview'] = TRUE;
		return $layout;
	}

	/**
	 * Token preview bertanda tangan, berumur pendek, dan terikat halaman + pengguna.
	 * Tidak memberi akses selain membaca draft halaman tersebut.
	 */
	public function preview_token($page, $user_id, $ttl = 900)
	{
		$expires = $this->CI->clock->timestamp() + (int) $ttl;
		$payload = $page->public_id.'.'.(int) $user_id.'.'.$expires;
		return $payload.'.'.$this->CI->crypto->hmac($payload, 'token');
	}

	/** @return object|null halaman bila token sah */
	public function verify_preview_token($token, $user_id)
	{
		$parts = explode('.', (string) $token);
		if (count($parts) !== 4)
		{
			return NULL;
		}
		list($public_id, $token_user, $expires, $signature) = $parts;
		if ((int) $token_user !== (int) $user_id OR (int) $expires < $this->CI->clock->timestamp())
		{
			return NULL;
		}
		$expected = $this->CI->crypto->hmac($public_id.'.'.$token_user.'.'.$expires, 'token');
		if ( ! $this->CI->crypto->equals($expected, $signature))
		{
			return NULL;
		}
		return $this->CI->cms->page_by_public_id($public_id);
	}
}
