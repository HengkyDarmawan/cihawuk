<?php

/**
 * Tahap 1 v1.2 (level service): pencatatan pemakaian media dan aturan arsip.
 * Jalur unggah nyata diuji pada MediaHttpTest karena `move_uploaded_file()`
 * hanya bekerja pada request HTTP sungguhan.
 */
class MediaPipelineTest extends CiTestCase {

	/** @var object */
	protected $editor;

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('UploadService', NULL, 'uploads');
		$this->truncate_service_data();
		$this->CI->db->query('DELETE FROM media_usages');
		$this->CI->db->query('DELETE FROM media_derivatives');
		$this->CI->db->query('DELETE FROM media_assets');
		$this->editor = $this->make_user('media.svc.test', array('content_editor'));
	}

	/** Media beserta satu derivative publik dan satu berkas asli privat. */
	protected function make_media()
	{
		$now = utc_now();
		$private_relative = 'media/uji/'.bin2hex(random_bytes(8));
		$private_path = $this->CI->uploads->private_root().'/'.$private_relative;
		@mkdir(dirname($private_path), 0750, TRUE);
		file_put_contents($private_path, 'berkas asli uji');

		$this->CI->db->insert('private_files', array(
			'storage_key' => $private_relative, 'original_name' => 'uji.png', 'mime_type' => 'image/png',
			'byte_size' => filesize($private_path), 'checksum' => hash_file('sha256', $private_path),
			'scan_status' => 'not_scanned', 'purpose' => 'media_original', 'uploaded_by' => (int) $this->editor->id,
			'created_at' => $now,
		));
		$private_id = (int) $this->CI->db->insert_id();

		$public_relative = 'uji/'.bin2hex(random_bytes(8)).'.png';
		$public_path = FCPATH.'media/'.$public_relative;
		@mkdir(dirname($public_path), 0755, TRUE);
		file_put_contents($public_path, 'derivative publik uji');

		$this->CI->db->insert('media_assets', array(
			'storage_key' => $public_relative, 'private_file_id' => $private_id, 'original_name' => 'uji.png',
			'mime_type' => 'image/png', 'byte_size' => filesize($private_path), 'checksum' => hash_file('sha256', $private_path),
			'alt_text' => 'Media uji', 'rights_status' => 'owned', 'publication_status' => 'published',
			'uploaded_by' => (int) $this->editor->id, 'created_at' => $now, 'updated_at' => $now,
		));
		$media_id = (int) $this->CI->db->insert_id();

		$this->CI->db->insert('media_derivatives', array(
			'media_asset_id' => $media_id, 'variant' => 'public', 'storage_key' => $public_relative,
			'mime_type' => 'image/png', 'width' => 100, 'height' => 80, 'byte_size' => filesize($public_path),
			'checksum' => hash_file('sha256', $public_path), 'created_at' => $now,
		));

		return array('id' => $media_id, 'private_path' => $private_path, 'public_path' => $public_path);
	}

	public function test_usage_is_recorded_from_real_references(): void
	{
		$media = $this->make_media();
		$now = utc_now();
		$slug = 'artikel-uji-media-'.bin2hex(random_bytes(3));
		$this->CI->db->insert('posts', array(
			'type' => 'news', 'title' => 'Artikel uji media', 'slug' => $slug,
			'excerpt' => 'Ringkas', 'body_html' => '<p>Isi</p>', 'cover_media_id' => $media['id'],
			'author_id' => (int) $this->editor->id, 'publication_status' => 'draft',
			'created_at' => $now, 'updated_at' => $now,
		));
		$post_id = (int) $this->CI->db->insert_id();

		$this->CI->content_service->sync_media_usages();
		$rows = $this->CI->db->where('media_asset_id', $media['id'])->get('media_usages')->result();
		$this->assertCount(1, $rows);
		$this->assertSame('posts', $rows[0]->object_type);
		$this->assertSame('cover_media_id', $rows[0]->field_name);
		$this->assertSame((string) $post_id, (string) $rows[0]->object_id);
		$this->assertNotEmpty($this->CI->content_service->media_usage($media['id']));

		// Rujukan hilang → baris pemakaian ikut hilang saat disinkronkan ulang.
		$this->CI->db->where('id', $post_id)->delete('posts');
		$this->CI->content_service->sync_media_usages();
		$this->assertSame(0, $this->CI->db->where('media_asset_id', $media['id'])->count_all_results('media_usages'));

		@unlink($media['private_path']);
		@unlink($media['public_path']);
	}

	public function test_archive_removes_public_copy_but_keeps_private_original(): void
	{
		$media = $this->make_media();
		$row = $this->CI->db->get_where('media_assets', array('id' => $media['id']))->row();

		$this->CI->uploads->delete_media_asset($row);

		$this->assertFileDoesNotExist($media['public_path'], 'Salinan publik harus dibuang');
		$this->assertFileExists($media['private_path'], 'Berkas asli tetap tersimpan privat');
		$this->assertSame(0, $this->CI->db->where('media_asset_id', $media['id'])->count_all_results('media_derivatives'));

		$archived = $this->CI->db->get_where('media_assets', array('id' => $media['id']))->row();
		$this->assertNotNull($archived->deleted_at);
		$this->assertNotNull($archived->private_file_id, 'Rujukan berkas asli tidak dihapus');

		@unlink($media['private_path']);
	}
}
