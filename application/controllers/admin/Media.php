<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Media publik: unggah, metadata (alt text, sumber, hak publikasi), dan arsip.
 * Media hanya dapat dipakai konten bila status haknya jelas.
 */
class Media extends Admin_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('UploadService', NULL, 'uploads');
		$this->load->library('ContentService', NULL, 'content_service');
		$this->layout_data['nav_active'] = 'media';
	}

	public function index()
	{
		$this->require_permission('content.edit');
		$page = max(1, (int) $this->input->get('hal'));
		$per_page = 24;
		$total = (int) $this->db->where('deleted_at IS NULL', NULL, FALSE)->count_all_results('media_assets');
		$items = $this->db->where('deleted_at IS NULL', NULL, FALSE)->order_by('id', 'DESC')
			->limit($per_page, ($page - 1) * $per_page)->get('media_assets')->result();
		$this->content_service->sync_media_usages();
		foreach ($items as $item)
		{
			$item->usage = $this->content_service->media_usage($item->id);
			$item->derivatives = $this->uploads->derivatives($item->id);
		}
		$this->render('admin/media', array(
			'page_title' => 'Media',
			'items' => $items,
			'page' => $page,
			'pages' => max(1, (int) ceil($total / $per_page)),
			'total' => $total,
			'rights_options' => array(
				'owned' => 'Milik desa',
				'licensed' => 'Berlisensi (tercatat)',
				'permission_granted' => 'Ada izin dari pemilik',
				'unknown' => 'Belum jelas (tidak dapat dipublikasikan)',
			),
			'extra_js' => array('vendor/sweetalert2/sweetalert2.min.js'),
		), 'dashboard');
	}

	public function upload()
	{
		$this->require_method('post');
		$this->require_permission('content.edit');
		try
		{
			$id = $this->uploads->store_media_asset('berkas', array(
				'alt_text' => $this->post_string('alt_text', 255),
				'caption' => $this->post_string('caption', 500),
				'source_credit' => $this->post_string('source_credit', 255),
				'source_year' => (int) $this->input->post('source_year'),
				'people_shown' => $this->post_string('people_shown', 255),
				'license_note' => $this->post_string('license_note', 255),
				'rights_status' => (string) $this->input->post('rights_status'),
				'is_placeholder' => (bool) $this->input->post('is_placeholder'),
			), (int) $this->user->id);
		}
		catch (DomainRuleException $e)
		{
			$this->form_errors = $e->errors;
			$this->flash('error', $e->getMessage());
			redirect(site_url('admin/media'), 'location', 303);
			return;
		}
		$this->audit->log('media.uploaded', 'media', (string) $id, array());
		$this->flash('success', 'Media terunggah. Lengkapi alt text dan status hak publikasi bila belum.');
		redirect(site_url('admin/media'), 'location', 303);
	}

	public function update($id)
	{
		$this->require_method('post');
		$this->require_permission('content.edit');
		$media = $this->db->get_where('media_assets', array('id' => (int) $id))->row();
		if ( ! $media OR $media->deleted_at !== NULL)
		{
			$this->not_found_response();
			return;
		}
		$rights = (string) $this->input->post('rights_status');
		if ( ! in_array($rights, array('owned', 'licensed', 'permission_granted', 'unknown'), TRUE))
		{
			throw new DomainRuleException('Status hak publikasi tidak valid.', 422);
		}
		$alt_text = $this->post_string('alt_text', 255);
		// Media tidak boleh terbit tanpa teks alternatif (kecuali dokumen PDF).
		if ($rights !== 'unknown' && trim($alt_text) === '' && strpos($media->mime_type, 'image/') === 0)
		{
			throw new DomainRuleException('Isi teks alternatif sebelum media dapat dipublikasikan.', 422, array('alt_text' => 'Teks alternatif wajib diisi.'));
		}
		db_must($this->db->where('id', (int) $id)->update('media_assets', array(
			'alt_text' => $alt_text,
			'caption' => $this->post_string('caption', 500) ?: NULL,
			'source_credit' => $this->post_string('source_credit', 255) ?: NULL,
			'people_shown' => $this->post_string('people_shown', 255) ?: NULL,
			'license_note' => $this->post_string('license_note', 255) ?: NULL,
			'rights_status' => $rights,
			'is_placeholder' => $this->input->post('is_placeholder') ? 1 : 0,
			'publication_status' => ($rights === 'unknown') ? 'draft' : 'published',
			'updated_at' => utc_now(),
		)), 'media_assets.update');
		$this->audit->log('media.updated', 'media', (string) $id, array('rights_status' => $rights));
		$this->content_service->invalidate_cache();
		$this->flash('success', 'Metadata media diperbarui.');
		redirect(site_url('admin/media'), 'location', 303);
	}

	public function delete($id)
	{
		$this->require_method('post');
		$this->require_permission('content.publish');
		$media = $this->db->get_where('media_assets', array('id' => (int) $id))->row();
		if ( ! $media OR $media->deleted_at !== NULL)
		{
			$this->not_found_response();
			return;
		}
		$usage = $this->content_service->media_usage($media->id);
		if ( ! empty($usage))
		{
			throw new DomainRuleException('Media masih dipakai konten lain ('.implode(', ', $usage).'). Ganti media pada konten tersebut terlebih dahulu.', 409);
		}
		$this->uploads->delete_media_asset($media);
		$this->audit->log('media.deleted', 'media', (string) $id, array('name' => $media->original_name));
		$this->content_service->invalidate_cache();
		$this->flash('success', 'Media dihapus dari daftar dan berkasnya dibuang.');
		redirect(site_url('admin/media'), 'location', 303);
	}
}
