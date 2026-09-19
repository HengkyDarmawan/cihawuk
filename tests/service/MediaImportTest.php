<?php

require_once __DIR__.'/../CiTestCase.php';

/**
 * Impor media dari berkas di disk (UploadService::store_media_file).
 *
 * Jalur ini dibuat karena store_media_asset() bergantung pada move_uploaded_file(), yang
 * menolak berkas apa pun yang bukan hasil unggahan HTTP, sehingga seeder dan CLI tidak dapat
 * memasukkan media sama sekali. Pemeriksaannya harus tetap sama ketatnya.
 */
class MediaImportTest extends CiTestCase {

	/** @var string */
	protected $dir;

	/** @var array */
	protected $media_ids = array();

	protected function setUp(): void
	{
		parent::setUp();
		$this->CI->load->library('UploadService', NULL, 'uploads');
		$this->dir = str_replace(chr(92), '/', sys_get_temp_dir()).'/chw-media-test';
		if ( ! is_dir($this->dir))
		{
			mkdir($this->dir, 0777, TRUE);
		}
	}

	protected function tearDown(): void
	{
		foreach ($this->media_ids as $id)
		{
			$media = $this->CI->db->get_where('media_assets', array('id' => (int) $id))->row();
			if ($media)
			{
				$this->CI->db->where('media_asset_id', (int) $id)->delete('media_derivatives');
				$this->CI->db->where('id', (int) $id)->delete('media_assets');
				$this->CI->db->where('id', (int) $media->private_file_id)->delete('private_files');
			}
		}
		$this->media_ids = array();
		foreach (glob($this->dir.'/*') ?: array() as $f)
		{
			@unlink($f);
		}
		parent::tearDown();
	}

	protected function make_jpeg($name, $w = 900, $h = 600)
	{
		$im = imagecreatetruecolor($w, $h);
		imagefill($im, 0, 0, imagecolorallocate($im, 40, 90, 60));
		$path = $this->dir.'/'.$name;
		imagejpeg($im, $path, 85);
		imagedestroy($im);
		return $path;
	}

	public function test_file_from_disk_is_stored_with_derivatives(): void
	{
		$path = $this->make_jpeg('lanskap.jpg');
		$id = $this->CI->uploads->store_media_file($path, 'lanskap.jpg', array(
			'alt_text' => 'Lanskap contoh untuk pengujian',
			'rights_status' => 'licensed',
			'license_note' => 'CC BY-SA 4.0',
			'source_credit' => 'Fotografer contoh',
		), NULL);
		$this->media_ids[] = $id;

		$media = $this->CI->db->get_where('media_assets', array('id' => (int) $id))->row();
		$this->assertNotNull($media);
		$this->assertSame('image/jpeg', $media->mime_type);
		// Hak yang jelas berarti media siap tampil publik.
		$this->assertSame('published', $media->publication_status);
		$this->assertSame(900, (int) $media->width);
		// Berkas asli tidak dipindahkan, sehingga sumbernya tetap ada.
		$this->assertFileExists($path);
		$this->assertCount(3, $this->CI->uploads->derivatives($id));
	}

	public function test_unknown_rights_stay_draft(): void
	{
		$path = $this->make_jpeg('tanpa-hak.jpg');
		$id = $this->CI->uploads->store_media_file($path, 'tanpa-hak.jpg', array(
			'rights_status' => 'unknown',
		), NULL);
		$this->media_ids[] = $id;

		$media = $this->CI->db->get_where('media_assets', array('id' => (int) $id))->row();
		$this->assertSame('unknown', $media->rights_status);
		$this->assertSame('draft', $media->publication_status,
			'Media tanpa kejelasan hak tidak boleh langsung publik.');
	}

	public function test_disguised_script_is_rejected(): void
	{
		$path = $this->dir.'/jahat.jpg';
		file_put_contents($path, "<?php echo 'halo'; ?>");
		try
		{
			$this->CI->uploads->store_media_file($path, 'jahat.jpg', array(
				'alt_text' => 'Bukan gambar', 'rights_status' => 'owned',
			), NULL);
			$this->fail('Berkas yang isinya bukan gambar harus ditolak.');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(422, $e->http_status);
		}
	}

	public function test_missing_file_is_rejected(): void
	{
		try
		{
			$this->CI->uploads->store_media_file($this->dir.'/tidak-ada.jpg', 'tidak-ada.jpg', array(
				'rights_status' => 'owned',
			), NULL);
			$this->fail('Berkas yang tidak ada harus ditolak.');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(422, $e->http_status);
		}
	}

	public function test_failed_insert_leaves_no_orphan_private_file(): void
	{
		$before = (int) $this->CI->db->where('purpose', 'media_original')->count_all_results('private_files');
		$path = $this->make_jpeg('alt-kosong.jpg');
		try
		{
			// Hak jelas tetapi teks alternatif kosong: ditolak sebelum berkas ditulis.
			$this->CI->uploads->store_media_file($path, 'alt-kosong.jpg', array(
				'alt_text' => '', 'rights_status' => 'owned',
			), NULL);
			$this->fail('Teks alternatif wajib untuk media yang siap publik.');
		}
		catch (DomainRuleException $e)
		{
			$this->assertSame(422, $e->http_status);
		}
		$after = (int) $this->CI->db->where('purpose', 'media_original')->count_all_results('private_files');
		$this->assertSame($before, $after, 'Kegagalan tidak boleh meninggalkan berkas privat tanpa media.');
	}
}
