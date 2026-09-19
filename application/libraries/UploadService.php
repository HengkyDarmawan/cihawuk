<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Unggahan lampiran laporan ke penyimpanan privat di luar folder public.
 *
 * - Allowlist ekstensi + pemeriksaan MIME (finfo) + signature berkas.
 * - Gambar di-decode ulang (menghapus EXIF/lokasi) dengan batas dimensi.
 * - Nama berkas acak; nama asli hanya metadata.
 * - Tanpa pemindai malware: status scan_status = not_scanned (bukan "bersih").
 */
class UploadService {

	/** @var CI_Controller */
	protected $CI;

	/** @var array berkas staging yang belum dipakai (dihapus bila transaksi gagal) */
	protected $staged = array();

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->config->load('app', TRUE);
	}

	public function rules()
	{
		return $this->CI->config->item('ticket_upload', 'app');
	}

	public function private_root()
	{
		$path = trim((string) app_env('STORAGE_PRIVATE_PATH', ''));
		if ($path === '')
		{
			$path = ROOTPATH.'storage/private';
		}
		$real = realpath($path);
		if ($real === FALSE)
		{
			@mkdir($path, 0750, TRUE);
			$real = realpath($path);
		}
		if ($real === FALSE)
		{
			throw new RuntimeException('Private storage path is not available.');
		}
		return rtrim(str_replace('\\', '/', $real), '/');
	}

	/**
	 * Validasi dan simpan berkas dari $_FILES[$field] (multiple).
	 * Harus dipanggil di dalam transaction bisnis; berkas dihapus bila rollback.
	 *
	 * @return array daftar private_files.id
	 */
	public function store_ticket_attachments($field, $uploader_user_id = NULL, $uploader_type = 'anonymous')
	{
		$files = $this->normalize_files($field);
		if (empty($files))
		{
			return array();
		}
		$rules = $this->rules();
		if (count($files) > (int) $rules['max_files'])
		{
			throw new DomainRuleException('Maksimal '.(int) $rules['max_files'].' lampiran.', 422, array($field => 'Maksimal '.(int) $rules['max_files'].' lampiran.'));
		}
		$total = 0;
		$ids = array();
		foreach ($files as $file)
		{
			$total += (int) $file['size'];
			if ($total > (int) $rules['max_total_bytes'])
			{
				throw new DomainRuleException('Total lampiran melebihi '.format_bytes_id($rules['max_total_bytes']).'.', 422, array($field => 'Total lampiran terlalu besar.'));
			}
			$ids[] = $this->store_one($file, $uploader_user_id, $uploader_type);
		}
		return $ids;
	}

	protected function normalize_files($field)
	{
		if (empty($_FILES[$field]) OR ! is_array($_FILES[$field]['name']))
		{
			return array();
		}
		$out = array();
		foreach ($_FILES[$field]['name'] as $i => $name)
		{
			$error = (int) $_FILES[$field]['error'][$i];
			if ($error === UPLOAD_ERR_NO_FILE)
			{
				continue;
			}
			if ($error === UPLOAD_ERR_INI_SIZE OR $error === UPLOAD_ERR_FORM_SIZE)
			{
				throw new DomainRuleException('Ukuran berkas melebihi batas server.', 413, array($field => 'Berkas terlalu besar.'));
			}
			if ($error !== UPLOAD_ERR_OK)
			{
				throw new DomainRuleException('Berkas gagal diunggah. Coba ulangi.', 422, array($field => 'Berkas gagal diunggah.'));
			}
			$out[] = array(
				'name' => (string) $name,
				'tmp_name' => $_FILES[$field]['tmp_name'][$i],
				'size' => (int) $_FILES[$field]['size'][$i],
			);
		}
		return $out;
	}

	protected function store_one(array $file, $uploader_user_id, $uploader_type)
	{
		$rules = $this->rules();
		$field_error = 'lampiran';

		if ( ! is_uploaded_file($file['tmp_name']))
		{
			throw new DomainRuleException('Berkas tidak valid.', 422, array($field_error => 'Berkas tidak valid.'));
		}
		if ($file['size'] <= 0 OR $file['size'] > (int) $rules['max_file_bytes'])
		{
			throw new DomainRuleException('Setiap berkas maksimal '.format_bytes_id($rules['max_file_bytes']).'.', 422, array($field_error => 'Ukuran berkas melebihi batas.'));
		}

		$original = $this->sanitize_name($file['name']);
		$ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
		// Tolak nama dengan ekstensi ganda yang menyamar (mis. foto.php.jpg).
		if ( ! isset($rules['types'][$ext]) OR preg_match('/\.(php\d?|phtml|phar|pht|exe|bat|cmd|sh|js|html?|svg|htaccess)\./i', $original))
		{
			throw new DomainRuleException('Jenis berkas tidak diizinkan. Gunakan JPG, PNG, WebP, atau PDF.', 422, array($field_error => 'Jenis berkas tidak diizinkan.'));
		}
		$expected_mime = $rules['types'][$ext];

		$finfo = new finfo(FILEINFO_MIME_TYPE);
		$mime = (string) $finfo->file($file['tmp_name']);
		if ($mime !== $expected_mime)
		{
			throw new DomainRuleException('Isi berkas tidak sesuai dengan jenisnya.', 422, array($field_error => 'Isi berkas tidak sesuai ekstensinya.'));
		}
		if ( ! $this->signature_ok($file['tmp_name'], $expected_mime))
		{
			throw new DomainRuleException('Berkas tidak dikenali sebagai '.strtoupper($ext).' yang valid.', 422, array($field_error => 'Berkas tidak valid.'));
		}

		$root = $this->private_root();
		$relative = $this->CI->clock->now()->format('Y/m').'/'.$this->CI->crypto->random_hex(16);
		$target_dir = $root.'/'.dirname($relative);
		if ( ! is_dir($target_dir) && ! @mkdir($target_dir, 0750, TRUE))
		{
			throw new RuntimeException('Cannot create storage directory.');
		}
		$target = $root.'/'.$relative;

		if ($expected_mime === 'application/pdf')
		{
			if ( ! @move_uploaded_file($file['tmp_name'], $target))
			{
				throw new RuntimeException('Cannot move uploaded file.');
			}
			@chmod($target, 0640);
		}
		else
		{
			$this->reencode_image($file['tmp_name'], $target, $expected_mime, $rules);
		}
		$this->staged[] = $target;

		$size = (int) filesize($target);
		db_must($this->CI->db->insert('private_files', array(
			'storage_key' => $relative,
			'original_name' => mb_substr($original, 0, 255),
			'mime_type' => $expected_mime,
			'byte_size' => $size,
			'checksum' => hash_file('sha256', $target),
			'scan_status' => 'not_scanned',
			'purpose' => 'ticket_attachment',
			'uploaded_by' => $uploader_user_id,
			'created_at' => utc_now(),
		)), 'private_files.insert');
		return (int) $this->CI->db->insert_id();
	}

	protected function sanitize_name($name)
	{
		$name = preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $name);
		$name = str_replace(array('/', '\\'), '_', $name);
		return trim($name) === '' ? 'lampiran' : trim($name);
	}

	protected function signature_ok($path, $mime)
	{
		$fh = fopen($path, 'rb');
		if ($fh === FALSE)
		{
			return FALSE;
		}
		$head = fread($fh, 16);
		fclose($fh);
		switch ($mime)
		{
			case 'image/jpeg':
				return strncmp($head, "\xFF\xD8\xFF", 3) === 0;
			case 'image/png':
				return strncmp($head, "\x89PNG\x0D\x0A\x1A\x0A", 8) === 0;
			case 'image/webp':
				return strncmp($head, 'RIFF', 4) === 0 && substr($head, 8, 4) === 'WEBP';
			case 'application/pdf':
				return strncmp($head, '%PDF-', 5) === 0;
		}
		return FALSE;
	}

	/** Decode ulang gambar: menghapus metadata EXIF/lokasi dan payload aktif. */
	protected function reencode_image($source, $target, $mime, array $rules)
	{
		$info = @getimagesize($source);
		if ($info === FALSE)
		{
			throw new DomainRuleException('Gambar tidak dapat dibaca.', 422, array('lampiran' => 'Gambar tidak dapat dibaca.'));
		}
		list($width, $height) = $info;
		if ($width > (int) $rules['max_image_side'] OR $height > (int) $rules['max_image_side'] OR ($width * $height) > (int) $rules['max_image_pixels'])
		{
			throw new DomainRuleException('Dimensi gambar terlalu besar. Perkecil gambar lalu unggah kembali.', 422, array('lampiran' => 'Dimensi gambar terlalu besar.'));
		}
		$image = NULL;
		switch ($mime)
		{
			case 'image/jpeg': $image = @imagecreatefromjpeg($source); break;
			case 'image/png': $image = @imagecreatefrompng($source); break;
			case 'image/webp': $image = @imagecreatefromwebp($source); break;
		}
		if ($image === FALSE OR $image === NULL)
		{
			throw new DomainRuleException('Gambar tidak dapat diproses.', 422, array('lampiran' => 'Gambar tidak dapat diproses.'));
		}
		// Batasi sisi terpanjang agar penyimpanan dan pengiriman wajar.
		$max_side = 2400;
		if (max($width, $height) > $max_side)
		{
			$ratio = $max_side / max($width, $height);
			$resized = imagescale($image, (int) round($width * $ratio), (int) round($height * $ratio));
			if ($resized !== FALSE)
			{
				imagedestroy($image);
				$image = $resized;
			}
		}
		$ok = FALSE;
		switch ($mime)
		{
			case 'image/jpeg':
				$ok = imagejpeg($image, $target, 85);
				break;
			case 'image/png':
				imagealphablending($image, FALSE);
				imagesavealpha($image, TRUE);
				$ok = imagepng($image, $target, 6);
				break;
			case 'image/webp':
				imagealphablending($image, FALSE);
				imagesavealpha($image, TRUE);
				$ok = imagewebp($image, $target, 85);
				break;
		}
		imagedestroy($image);
		if ( ! $ok)
		{
			throw new RuntimeException('Cannot write processed image.');
		}
		@chmod($target, 0640);
	}

	/**
	 * Pipeline media (modul-backend §14.3): berkas asli disimpan privat, hanya derivative
	 * yang dilayani dari public/media. Gambar di-decode ulang sehingga metadata EXIF/lokasi hilang.
	 *
	 * @return int media_assets.id
	 */
	public function store_media_asset($field, array $meta, $user_id)
	{
		$files = $this->normalize_files($field);
		if (empty($files))
		{
			throw new DomainRuleException('Pilih berkas yang akan diunggah.', 422, array($field => 'Berkas wajib dipilih.'));
		}
		return $this->ingest_media($files[0], $meta, $user_id, $field, TRUE);
	}

	/**
	 * Impor media dari berkas yang sudah ada di disk (seeder, importer, perintah CLI).
	 *
	 * Dibutuhkan karena `store_media_asset()` bergantung pada `move_uploaded_file()`, yang
	 * menolak berkas apa pun yang bukan hasil unggahan HTTP. Seluruh pemeriksaan keamanan
	 * dijalankan sama persis; berkas sumber disalin, bukan dipindahkan.
	 */
	public function store_media_file($path, $original_name, array $meta, $user_id)
	{
		if ( ! is_string($path) OR ! is_file($path) OR ! is_readable($path))
		{
			throw new DomainRuleException('Berkas media tidak ditemukan atau tidak dapat dibaca.', 422,
				array('berkas' => 'Berkas tidak ditemukan: '.basename((string) $path)));
		}
		return $this->ingest_media(array(
			'name' => (string) $original_name,
			'tmp_name' => $path,
			'size' => (int) filesize($path),
		), $meta, $user_id, 'berkas', FALSE);
	}

	/**
	 * Inti penyimpanan media. Pemeriksaan jenis, MIME, tanda tangan, ukuran, dimensi dan
	 * status hak berlaku sama untuk unggahan HTTP maupun berkas di disk; yang berbeda hanya
	 * cara berkas aslinya dipindahkan.
	 */
	protected function ingest_media(array $file, array $meta, $user_id, $field, $uploaded)
	{
		// Satu media menulis beberapa baris dan beberapa berkas. Bila salah satu gagal,
		// baris dibatalkan dan berkas yang terlanjur ditulis ikut dibuang, supaya tidak
		// tertinggal berkas asli tanpa media_assets yang menunjuknya.
		$this->CI->db->trans_begin();
		try
		{
			$media_id = $this->ingest_media_inner($file, $meta, $user_id, $field, $uploaded);
		}
		catch (Throwable $e)
		{
			$this->CI->db->trans_rollback();
			$this->discard_staged();
			throw $e;
		}
		if ($this->CI->db->trans_status() === FALSE)
		{
			$this->CI->db->trans_rollback();
			$this->discard_staged();
			throw new RuntimeException('Cannot store media file.');
		}
		$this->CI->db->trans_commit();
		$this->commit_staged();
		return $media_id;
	}

	protected function ingest_media_inner(array $file, array $meta, $user_id, $field, $uploaded)
	{
		$types = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf');
		$max_bytes = 8 * 1024 * 1024;

		$original = $this->sanitize_name($file['name']);
		$ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
		if ( ! isset($types[$ext]) OR preg_match('/\.(php\d?|phtml|phar|pht|exe|bat|cmd|sh|js|html?|svg)\./i', $original))
		{
			throw new DomainRuleException('Jenis berkas tidak diizinkan. Gunakan JPG, PNG, WebP, atau PDF.', 422, array($field => 'Jenis berkas tidak diizinkan.'));
		}
		if ($file['size'] > $max_bytes)
		{
			throw new DomainRuleException('Ukuran media maksimal '.format_bytes_id($max_bytes).'.', 422, array($field => 'Berkas terlalu besar.'));
		}
		$finfo = new finfo(FILEINFO_MIME_TYPE);
		$mime = (string) $finfo->file($file['tmp_name']);
		if ($mime !== $types[$ext] OR ! $this->signature_ok($file['tmp_name'], $mime))
		{
			throw new DomainRuleException('Isi berkas tidak sesuai dengan jenisnya.', 422, array($field => 'Berkas tidak valid.'));
		}

		$rights = in_array($meta['rights_status'] ?? '', array('owned', 'licensed', 'permission_granted', 'unknown'), TRUE) ? $meta['rights_status'] : 'unknown';
		$alt_text = trim((string) ($meta['alt_text'] ?? ''));
		// Media dengan hak yang jelas berarti siap tampil publik; teks alternatif wajib untuk itu.
		if ($rights !== 'unknown' && $alt_text === '' && $mime !== 'application/pdf')
		{
			throw new DomainRuleException('Isi teks alternatif sebelum media dapat dipublikasikan.', 422, array('alt_text' => 'Teks alternatif wajib diisi.'));
		}

		// 1. Berkas asli disimpan privat, tidak pernah dilayani langsung dari web.
		$root = $this->private_root();
		$private_relative = 'media/'.$this->CI->clock->now()->format('Y/m').'/'.$this->CI->crypto->random_hex(16);
		$private_dir = $root.'/'.dirname($private_relative);
		if ( ! is_dir($private_dir) && ! @mkdir($private_dir, 0750, TRUE))
		{
			throw new RuntimeException('Cannot create private media directory.');
		}
		$private_path = $root.'/'.$private_relative;
		// move_uploaded_file sekaligus membuktikan berkas benar-benar berasal dari POST,
		// jadi jalur unggahan tetap memakainya. Berkas di disk disalin supaya sumbernya utuh.
		$placed = $uploaded
			? @move_uploaded_file($file['tmp_name'], $private_path)
			: @copy($file['tmp_name'], $private_path);
		if ( ! $placed)
		{
			throw new RuntimeException('Cannot store media file.');
		}
		@chmod($private_path, 0640);
		$this->staged[] = $private_path;

		db_must($this->CI->db->insert('private_files', array(
			'storage_key' => $private_relative,
			'original_name' => mb_substr($original, 0, 255),
			'mime_type' => $mime,
			'byte_size' => (int) filesize($private_path),
			'checksum' => hash_file('sha256', $private_path),
			'scan_status' => 'not_scanned',
			'purpose' => 'media_original',
			'uploaded_by' => $user_id ? (int) $user_id : NULL,
			'created_at' => utc_now(),
		)), 'private_files.media_original');
		$private_file_id = (int) $this->CI->db->insert_id();

		// 2. Derivative publik dengan nama acak.
		$public_base = $this->CI->clock->now()->format('Y/m').'/'.$this->CI->crypto->random_hex(16);
		$derivatives = array();
		$width = $height = NULL;

		if ($mime === 'application/pdf')
		{
			$derivatives[] = $this->write_public_copy($private_path, $public_base.'.pdf', 'application/pdf', 'public');
		}
		else
		{
			$rules = $this->rules();
			$main = $this->write_image_derivative($private_path, $public_base.'.'.$ext, $mime, 1600, $rules, 'public');
			$derivatives[] = $main;
			$width = $main['width'];
			$height = $main['height'];
			$derivatives[] = $this->write_image_derivative($private_path, $public_base.'-web.webp', 'image/webp', 1600, $rules, 'webp');
			$derivatives[] = $this->write_image_derivative($private_path, $public_base.'-thumb.webp', 'image/webp', 480, $rules, 'thumb');
		}

		$now = utc_now();
		db_must($this->CI->db->insert('media_assets', array(
			'storage_key' => $derivatives[0]['storage_key'],
			'private_file_id' => $private_file_id,
			'original_name' => mb_substr($original, 0, 255),
			'mime_type' => $mime,
			'byte_size' => (int) filesize($private_path),
			'width' => $width,
			'height' => $height,
			'checksum' => hash_file('sha256', $private_path),
			'alt_text' => mb_substr($alt_text, 0, 255),
			'caption' => ($meta['caption'] ?? '') === '' ? NULL : mb_substr($meta['caption'], 0, 500),
			'source_credit' => ($meta['source_credit'] ?? '') === '' ? NULL : mb_substr($meta['source_credit'], 0, 255),
			'source_year' => empty($meta['source_year']) ? NULL : (int) $meta['source_year'],
			'people_shown' => ($meta['people_shown'] ?? '') === '' ? NULL : mb_substr($meta['people_shown'], 0, 255),
			'license_note' => ($meta['license_note'] ?? '') === '' ? NULL : mb_substr($meta['license_note'], 0, 255),
			'rights_status' => $rights,
			'verification_status' => 'unverified',
			'is_placeholder' => ! empty($meta['is_placeholder']) ? 1 : 0,
			// Impor lewat CLI tidak punya aktor; kolomnya memang nullable.
			'uploaded_by' => ($user_id === NULL) ? NULL : (int) $user_id,
			'publication_status' => ($rights === 'unknown') ? 'draft' : 'published',
			'created_at' => $now,
			'updated_at' => $now,
		)), 'media_assets.insert');
		$media_id = (int) $this->CI->db->insert_id();

		foreach ($derivatives as $derivative)
		{
			db_must($this->CI->db->insert('media_derivatives', array(
				'media_asset_id' => $media_id,
				'variant' => $derivative['variant'],
				'storage_key' => $derivative['storage_key'],
				'mime_type' => $derivative['mime_type'],
				'width' => $derivative['width'],
				'height' => $derivative['height'],
				'byte_size' => $derivative['byte_size'],
				'checksum' => $derivative['checksum'],
				'created_at' => $now,
			)), 'media_derivatives.insert');
		}
		return $media_id;
	}

	/** Derivative publik untuk berkas non-gambar: salinan apa adanya dengan nama acak. */
	protected function write_public_copy($source, $relative, $mime, $variant)
	{
		$target = $this->public_media_path($relative);
		if ( ! @copy($source, $target))
		{
			throw new RuntimeException('Cannot write public media file.');
		}
		@chmod($target, 0644);
		$this->staged[] = $target;
		return array(
			'variant' => $variant, 'storage_key' => $relative, 'mime_type' => $mime,
			'width' => NULL, 'height' => NULL,
			'byte_size' => (int) filesize($target), 'checksum' => hash_file('sha256', $target),
		);
	}

	/** Derivative gambar: decode ulang (metadata hilang), batasi sisi terpanjang, rasio dijaga. */
	protected function write_image_derivative($source, $relative, $target_mime, $max_side, array $rules, $variant)
	{
		$info = @getimagesize($source);
		if ($info === FALSE)
		{
			throw new DomainRuleException('Gambar tidak dapat dibaca.', 422, array('berkas' => 'Gambar tidak dapat dibaca.'));
		}
		list($width, $height) = $info;
		if ($width > (int) $rules['max_image_side'] OR $height > (int) $rules['max_image_side'] OR ($width * $height) > (int) $rules['max_image_pixels'])
		{
			throw new DomainRuleException('Dimensi gambar terlalu besar. Perkecil gambar lalu unggah kembali.', 422, array('berkas' => 'Dimensi gambar terlalu besar.'));
		}
		switch ($info['mime'])
		{
			case 'image/jpeg': $image = @imagecreatefromjpeg($source); break;
			case 'image/png': $image = @imagecreatefrompng($source); break;
			case 'image/webp': $image = @imagecreatefromwebp($source); break;
			default: $image = FALSE;
		}
		if ($image === FALSE OR $image === NULL)
		{
			throw new DomainRuleException('Gambar tidak dapat diproses.', 422, array('berkas' => 'Gambar tidak dapat diproses.'));
		}
		if (max($width, $height) > $max_side)
		{
			$ratio = $max_side / max($width, $height);
			$resized = imagescale($image, (int) round($width * $ratio), (int) round($height * $ratio));
			if ($resized !== FALSE)
			{
				imagedestroy($image);
				$image = $resized;
				$width = imagesx($image);
				$height = imagesy($image);
			}
		}

		$target = $this->public_media_path($relative);
		$ok = FALSE;
		switch ($target_mime)
		{
			case 'image/jpeg':
				$ok = imagejpeg($image, $target, 85);
				break;
			case 'image/png':
				imagealphablending($image, FALSE);
				imagesavealpha($image, TRUE);
				$ok = imagepng($image, $target, 6);
				break;
			case 'image/webp':
				imagealphablending($image, FALSE);
				imagesavealpha($image, TRUE);
				$ok = imagewebp($image, $target, 82);
				break;
		}
		imagedestroy($image);
		if ( ! $ok)
		{
			throw new RuntimeException('Cannot write public media derivative.');
		}
		@chmod($target, 0644);
		$this->staged[] = $target;
		return array(
			'variant' => $variant, 'storage_key' => $relative, 'mime_type' => $target_mime,
			'width' => $width, 'height' => $height,
			'byte_size' => (int) filesize($target), 'checksum' => hash_file('sha256', $target),
		);
	}

	protected function public_media_path($relative)
	{
		$dir = FCPATH.'media/'.dirname($relative);
		if ( ! is_dir($dir) && ! @mkdir($dir, 0755, TRUE))
		{
			throw new RuntimeException('Cannot create media directory.');
		}
		return FCPATH.'media/'.$relative;
	}

	/** Seluruh derivative publik satu media. */
	public function derivatives($media_id)
	{
		return $this->CI->db->where('media_asset_id', (int) $media_id)->order_by('variant')->get('media_derivatives')->result();
	}

	/**
	 * Arsipkan media: seluruh derivative publik dihapus dari web, berkas asli tetap tersimpan
	 * privat sehingga histori dan rujukan lama masih dapat ditelusuri.
	 */
	public function delete_media_asset($media)
	{
		$keys = array($media->storage_key);
		foreach ($this->derivatives($media->id) as $derivative)
		{
			$keys[] = $derivative->storage_key;
		}
		$root = realpath(FCPATH.'media');
		foreach (array_unique(array_filter($keys)) as $key)
		{
			$path = realpath(FCPATH.'media/'.$key);
			if ($path !== FALSE && $root !== FALSE && strpos(str_replace('\\', '/', $path), str_replace('\\', '/', $root).'/') === 0 && is_file($path))
			{
				@unlink($path);
			}
		}
		$this->CI->db->where('media_asset_id', (int) $media->id)->delete('media_derivatives');
		$this->CI->db->where('id', (int) $media->id)->update('media_assets', array(
			'storage_key' => NULL, 'deleted_at' => utc_now(), 'updated_at' => utc_now(),
		));
	}

	/** Hapus berkas staging (dipanggil saat transaksi dibatalkan). */
	public function discard_staged()
	{
		foreach ($this->staged as $path)
		{
			if (is_file($path))
			{
				@unlink($path);
			}
		}
		$this->staged = array();
	}

	public function commit_staged()
	{
		$this->staged = array();
	}

	public function file($id)
	{
		return $this->CI->db->get_where('private_files', array('id' => (int) $id))->row();
	}

	public function absolute_path($file)
	{
		$root = $this->private_root();
		$path = realpath($root.'/'.$file->storage_key);
		// Pastikan berkas tetap berada di dalam root penyimpanan.
		if ($path === FALSE OR strpos(str_replace('\\', '/', $path), $root.'/') !== 0)
		{
			return NULL;
		}
		return $path;
	}

	/** Kirim berkas privat sebagai attachment (tidak pernah inline). */
	public function send_download($file, $filename = NULL)
	{
		$path = $this->absolute_path($file);
		if ($path === NULL OR ! is_file($path))
		{
			throw new DomainRuleException('Berkas tidak ditemukan.', 404);
		}
		$name = $filename ?: $file->original_name;
		$name = preg_replace('/[^\p{L}\p{N}\s._-]/u', '', $name);
		$this->CI->output
			->set_status_header(200)
			->set_header('Content-Type: '.$file->mime_type)
			->set_header('Content-Disposition: attachment; filename="'.str_replace('"', '', $name).'"; filename*=UTF-8\'\''.rawurlencode($name))
			->set_header('Content-Length: '.filesize($path))
			->set_header('X-Content-Type-Options: nosniff')
			->set_header('Cache-Control: no-store, max-age=0')
			->set_header('Content-Security-Policy: default-src \'none\'; sandbox');
		$this->CI->output->_display();
		readfile($path);
		exit(EXIT_SUCCESS);
	}
}
