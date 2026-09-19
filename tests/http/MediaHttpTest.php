<?php

require_once __DIR__."/HttpTestCase.php";

/**
 * Tahap 1 v1.2 (jalur HTTP nyata): unggah media menghasilkan berkas asli privat
 * dan derivative publik; teks alternatif wajib sebelum media dapat terbit;
 * guard modul menutup route modul yang nonaktif walaupun URL-nya diketahui.
 */
class MediaHttpTest extends HttpTestCase {

	const PASSWORD = 'KataSandiUjiCoba2026';

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->db->query('DELETE FROM media_usages');
		$this->CI->db->query('DELETE FROM media_derivatives');
		$this->CI->db->query('DELETE FROM media_assets');
		$this->CI->db->query('DELETE FROM feature_module_histories');
		$this->CI->db->query("DELETE FROM private_files WHERE purpose = 'media_original'");
	}

	protected function tearDown(): void
	{
		$this->CI->db->where('code', 'news')->update('feature_modules', array('state' => 'active'));
		parent::tearDown();
	}

	protected function png_file($width = 900, $height = 600)
	{
		$image = imagecreatetruecolor($width, $height);
		imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 23, 75, 58));
		$path = sys_get_temp_dir().'/chw-http-media-'.bin2hex(random_bytes(6)).'.png';
		imagepng($image, $path);
		imagedestroy($image);
		return $path;
	}

	protected function upload($browser, $path, array $fields)
	{
		$page = $this->get('admin/media', $browser);
		$post = array_merge(array(
			'csrf_chw' => $this->csrf_from($page['body']),
			'berkas[]' => new CURLFile($path, 'image/png', basename($path)),
		), $fields);
		return $this->request('POST', 'admin/media', $browser, $post);
	}

	public function test_upload_stores_private_original_and_public_derivatives(): void
	{
		$this->make_user('editor.media.test', array('content_editor'));
		$this->assertSame(303, $this->login('editor.media.test', self::PASSWORD, 'ed')['status']);

		$path = $this->png_file();
		$this->assertSame(303, $this->upload('ed', $path, array(
			'alt_text' => 'Foto uji pipeline media', 'rights_status' => 'owned', 'source_credit' => 'Pengujian',
		))['status']);
		@unlink($path);

		$media = $this->CI->db->order_by('id', 'DESC')->get('media_assets')->row();
		$this->assertNotNull($media);
		$this->assertNotNull($media->private_file_id, 'Berkas asli tercatat sebagai berkas privat');
		$this->assertSame('published', $media->publication_status);

		$private = $this->CI->db->get_where('private_files', array('id' => $media->private_file_id))->row();
		$this->assertSame('media_original', $private->purpose);
		$this->assertStringNotContainsString('public', str_replace('\\', '/', $private->storage_key));

		$derivatives = $this->CI->db->where('media_asset_id', $media->id)->order_by('variant')->get('media_derivatives')->result();
		$this->assertCount(3, $derivatives, 'public, thumb dan webp dibuat');

		// Derivative dapat diakses publik; berkas asli tidak memiliki URL publik.
		$this->assertSame(200, $this->get('media/'.$media->storage_key, 'anon')['status']);
		$this->assertContains($this->get('storage/private/'.$private->storage_key, 'anon')['status'], array(403, 404));

		foreach ($derivatives as $derivative)
		{
			@unlink(FCPATH.'media/'.$derivative->storage_key);
		}
		$root = rtrim((string) app_env('STORAGE_PRIVATE_PATH', ''), '/\\');
		@unlink($root.'/'.$private->storage_key);
	}

	public function test_alt_text_is_required_before_media_can_be_published(): void
	{
		$this->make_user('editor.alt.test', array('content_editor'));
		$this->assertSame(303, $this->login('editor.alt.test', self::PASSWORD, 'alt')['status']);

		$path = $this->png_file(300, 200);
		$response = $this->upload('alt', $path, array('alt_text' => '', 'rights_status' => 'owned'));
		@unlink($path);

		$this->assertSame(303, $response['status']);
		$this->assertSame(0, $this->CI->db->count_all('media_assets'), 'Media tidak tersimpan saat alt text kosong');
		$this->assertSame(0, $this->CI->db->where('purpose', 'media_original')->count_all_results('private_files'));
	}

	public function test_disabled_module_blocks_public_and_admin_routes(): void
	{
		$this->make_user('admin.modul.test', array('super_admin'));
		$this->assertSame(303, $this->login('admin.modul.test', self::PASSWORD, 'sa')['status']);
		$this->assertSame(200, $this->get('berita', 'anon')['status']);

		$page = $this->get('admin/pengaturan/modul', 'sa');
		$this->assertSame(200, $page['status']);
		$change = $this->request('POST', 'admin/pengaturan/modul', 'sa', http_build_query(array(
			'csrf_chw' => $this->csrf_from($page['body']),
			'code' => 'news', 'state' => 'disabled', 'reason' => 'Uji guard route modul berita',
		)));
		$this->assertSame(303, $change['status']);

		// URL langsung tetap tertutup, bukan sekadar menu yang disembunyikan.
		$this->assertSame(404, $this->get('berita', 'anon')['status']);
		$this->assertSame(404, $this->get('admin/konten/berita', 'sa')['status']);

		$history = $this->CI->db->where('module_code', 'news')->order_by('id', 'DESC')->get('feature_module_histories')->row();
		$this->assertSame('disabled', $history->to_state);
		$this->assertStringContainsString('guard route', $history->reason);
	}
}
