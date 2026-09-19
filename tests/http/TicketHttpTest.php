<?php

require_once __DIR__.'/HttpTestCase.php';

/** ANON-01..05, RBAC-01..04, FILE-01/02, SEC-01/04, NET-01 melalui HTTP. */
class TicketHttpTest extends HttpTestCase {

	const PASSWORD = 'KataSandiUjiCoba2026';

	protected $tmp_files = array();

	protected function setUp(): void
	{
		parent::setUp();
		$this->truncate_service_data();
	}

	protected function tearDown(): void
	{
		foreach ($this->tmp_files as $f)
		{
			@unlink($f);
		}
		parent::tearDown();
	}

	protected function tmp_file($name, $content)
	{
		$path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'chw-'.bin2hex(random_bytes(4)).'-'.$name;
		file_put_contents($path, $content);
		$this->tmp_files[] = $path;
		return $path;
	}

	protected function png_bytes()
	{
		$img = imagecreatetruecolor(20, 20);
		imagefill($img, 0, 0, imagecolorallocate($img, 23, 75, 58));
		ob_start();
		imagepng($img);
		imagedestroy($img);
		return ob_get_clean();
	}

	protected function anon_fields(array $override = array())
	{
		return array_merge(array(
			'report_type' => 'complaint',
			'category_id' => (string) $this->category_id('ADMINISTRASI'),
			'title' => 'Pelayanan kantor desa tutup saat jam kerja',
			'description' => 'Pada hari Senin kantor desa tidak buka pada jam pelayanan sehingga warga menunggu lama.',
			'statement' => '1',
			'website' => '',
		), $override);
	}

	/** Kirim laporan anonim lengkap; kembalikan [kode tiket, kode akses]. */
	protected function submit_anonymous($browser, array $override = array(), array $files = array())
	{
		$page = $this->get('lapor', $browser);
		preg_match('/name="idempotency_key" value="([a-f0-9]+)"/', $page['body'], $m);
		$fields = $this->anon_fields($override);
		$fields['csrf_chw'] = $this->csrf_from($page['body']);
		$fields['idempotency_key'] = $m[1];
		foreach ($files as $i => $file)
		{
			$fields['lampiran['.$i.']'] = $file;
		}
		$post = $this->request('POST', 'lapor', $browser, $fields);
		$receipt = ($post['status'] === 303) ? $this->get('lapor/berhasil', $browser) : NULL;
		$code = $access = NULL;
		if ($receipt && preg_match('/id="ticket-code"[^>]*>([^<]+)</', $receipt['body'], $a) && preg_match('/id="access-code"[^>]*>([^<]+)</', $receipt['body'], $b))
		{
			$code = trim($a[1]);
			$access = trim($b[1]);
		}
		return array('post' => $post, 'receipt' => $receipt, 'code' => $code, 'access' => $access, 'key' => $m[1], 'fields' => $fields);
	}

	public function test_anonymous_submit_track_and_reply(): void
	{
		$png = $this->tmp_file('bukti.png', $this->png_bytes());
		$r = $this->submit_anonymous('anon', array(), array(new CURLFile($png, 'image/png', 'bukti.png')));
		$this->assertSame(303, $r['post']['status']);
		$this->assertStringEndsWith('/lapor/berhasil', $r['post']['location'], 'Kode akses tidak pernah dimuat di URL');
		$this->assertNotNull($r['code']);
		$this->assertNotNull($r['access']);
		$this->assertStringContainsString('no-store', implode(',', $r['receipt']['headers']['cache-control']));

		$ticket = $this->CI->tickets->find_by_code($r['code']);
		$this->assertNull($ticket->reporter_user_id);
		$attachments = $this->CI->tickets->attachments($ticket->id);
		$this->assertCount(1, $attachments);

		// Kode salah → respons generik.
		$bad = $this->post_form('lacak', 'lacak', array('public_code' => $r['code'], 'access_code' => 'AAAAA-AAAAA-AAAAA-AAAAA-AAAAA-A'), 'tracker');
		$this->assertSame(401, $bad['status']);
		$this->assertStringContainsString('tidak cocok', $bad['body']);
		$this->assertSame(303, $this->get('lacak/detail', 'tracker')['status'], 'Tanpa grant tidak ada detail');

		// Kode benar → grant sesi satu tiket.
		$ok = $this->post_form('lacak', 'lacak', array('public_code' => strtolower($r['code']), 'access_code' => strtolower($r['access'])), 'tracker');
		$this->assertSame(303, $ok['status']);
		$detail = $this->get('lacak/detail', 'tracker');
		$this->assertSame(200, $detail['status']);
		$this->assertStringContainsString('Pelayanan kantor desa tutup', $detail['body']);

		// Lampiran dapat diunduh oleh pemegang grant, tidak oleh orang lain.
		$file_url = 'berkas/privat/'.$attachments[0]->private_file_id;
		$dl = $this->get($file_url, 'tracker');
		$this->assertSame(200, $dl['status']);
		$this->assertStringContainsString('attachment', $dl['headers']['content-disposition'][0]);
		$this->assertSame(403, $this->get($file_url, 'stranger')['status']);

		// Balasan anonim memerlukan grant + CSRF.
		$reply = $this->request('POST', 'lacak/balas', 'tracker', http_build_query(array('csrf_chw' => $this->csrf_from($detail['body']), 'message' => 'Tambahan: kejadian berulang minggu ini.')));
		$this->assertSame(303, $reply['status']);
		$this->assertSame(1, $this->CI->db->where(array('ticket_id' => $ticket->id, 'actor_type' => 'anonymous'))->count_all_results('ticket_messages'));
		$stranger_page = $this->get('lacak', 'stranger');
		$forged = $this->request('POST', 'lacak/balas', 'stranger', http_build_query(array('csrf_chw' => $this->csrf_from($stranger_page['body']), 'message' => 'Pesan palsu dari orang lain')));
		$this->assertSame(303, $forged['status']);
		$this->assertSame(1, $this->CI->db->where(array('ticket_id' => $ticket->id, 'actor_type' => 'anonymous'))->count_all_results('ticket_messages'));

		// Kode akses tidak pernah muncul pada log aplikasi.
		$normalized = $this->CI->crypto->normalize_code($r['access']);
		foreach (glob(ROOTPATH.'storage/logs/*.log') ?: array() as $log)
		{
			$content = (string) file_get_contents($log);
			$this->assertStringNotContainsString($normalized, $content);
			$this->assertStringNotContainsString($r['access'], $content);
		}
		$this->assertSame(0, $this->CI->db->like('safe_metadata_json', $normalized)->count_all_results('audit_logs'));
	}

	public function test_tracking_bruteforce_is_limited(): void
	{
		$r = $this->submit_anonymous('anon2');
		for ($i = 0; $i < 5; $i++)
		{
			$this->assertSame(401, $this->post_form('lacak', 'lacak', array('public_code' => $r['code'], 'access_code' => 'SALAH'.$i.'SALAHSALAHSALAHSALAH'), 'bf')['status']);
		}
		$blocked = $this->post_form('lacak', 'lacak', array('public_code' => $r['code'], 'access_code' => $r['access']), 'bf');
		$this->assertSame(429, $blocked['status'], 'Tiket yang ditebak dikunci sementara');
	}

	public function test_double_submit_creates_single_ticket(): void
	{
		$r = $this->submit_anonymous('dbl');
		$this->assertSame(303, $r['post']['status']);
		// Pengiriman ulang dengan kunci & payload sama (mis. tombol ditekan dua kali).
		$page = $this->get('lapor', 'dbl');
		$fields = $r['fields'];
		$fields['csrf_chw'] = $this->csrf_from($page['body']);
		$again = $this->request('POST', 'lapor', 'dbl', $fields);
		$this->assertSame(303, $again['status']);
		$this->assertSame(1, $this->CI->db->count_all('tickets'));
		// Kunci sama, payload berbeda → 409.
		$page = $this->get('lapor', 'dbl');
		$fields['csrf_chw'] = $this->csrf_from($page['body']);
		$fields['title'] = 'Judul berbeda dengan kunci yang sama';
		$conflict = $this->request('POST', 'lapor', 'dbl', $fields);
		$this->assertSame(409, $conflict['status']);
		$this->assertSame(1, $this->CI->db->count_all('tickets'));
	}

	public function test_public_report_rate_limit(): void
	{
		for ($i = 0; $i < 5; $i++)
		{
			$this->assertSame(303, $this->submit_anonymous('rl'.$i, array('title' => 'Laporan uji batas nomor '.$i))['post']['status']);
		}
		$sixth = $this->submit_anonymous('rl6', array('title' => 'Laporan keenam dari jaringan sama'));
		$this->assertSame(429, $sixth['post']['status']);
		$this->assertSame(5, $this->CI->db->count_all('tickets'));
	}

	public function test_malicious_uploads_are_rejected(): void
	{
		$cases = array(
			array('shell.jpg', "<?php echo 'x'; ?>", 'image/jpeg'),
			array('gambar.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'image/svg+xml'),
			array('foto.php.png', $this->png_bytes(), 'image/png'),
			array('arsip.pdf', "PK\x03\x04bukan-pdf", 'application/pdf'),
		);
		foreach ($cases as $i => $case)
		{
			$path = $this->tmp_file($case[0], $case[1]);
			$r = $this->submit_anonymous('up'.$i, array('title' => 'Upload berbahaya nomor '.$i), array(new CURLFile($path, $case[2], $case[0])));
			$this->assertSame(422, $r['post']['status'], 'Berkas '.$case[0].' harus ditolak');
		}
		$this->assertSame(0, $this->CI->db->count_all('tickets'), 'Tidak ada tiket tersimpan saat lampiran ditolak');
		$this->assertSame(0, $this->CI->db->count_all('private_files'));
		$public = glob(FCPATH.'media/**/*.php') ?: array();
		$this->assertEmpty($public);

		// Lebih dari 3 berkas ditolak.
		$this->CI->db->query('DELETE FROM rate_limits');
		$png = $this->tmp_file('a.png', $this->png_bytes());
		$files = array();
		for ($i = 0; $i < 4; $i++)
		{
			$files[] = new CURLFile($png, 'image/png', 'a'.$i.'.png');
		}
		$too_many = $this->submit_anonymous('upmany', array('title' => 'Terlalu banyak lampiran uji'), $files);
		$this->assertSame(422, $too_many['post']['status']);
	}

	public function test_resident_isolation_and_field_allowlist(): void
	{
		$a = $this->make_user('warga.a.test', array('resident'));
		$b = $this->make_user('warga.b.test', array('resident'));
		$this->assertSame(303, $this->login('warga.a.test', self::PASSWORD, 'wa')['status']);
		$this->assertSame(303, $this->login('warga.b.test', self::PASSWORD, 'wb')['status']);

		$png = $this->tmp_file('b.png', $this->png_bytes());
		$page = $this->get('warga/laporan/buat', 'wb');
		preg_match('/name="idempotency_key" value="([a-f0-9]+)"/', $page['body'], $m);
		$fields = $this->anon_fields(array(
			'csrf_chw' => $this->csrf_from($page['body']),
			'idempotency_key' => $m[1],
			'title' => 'Laporan rahasia milik warga B saja',
			// Field terlarang dari peramban: harus diabaikan.
			'reporter_user_id' => (string) $a->id,
			'status' => 'resolved',
			'assigned_user_id' => (string) $a->id,
			'lampiran[0]' => new CURLFile($png, 'image/png', 'b.png'),
		));
		$store = $this->request('POST', 'warga/laporan', 'wb', $fields);
		$this->assertSame(303, $store['status']);
		$ticket = $this->CI->db->order_by('id', 'DESC')->get('tickets')->row();
		$this->assertSame((int) $b->id, (int) $ticket->reporter_user_id);
		$this->assertSame('submitted', $ticket->status);
		$this->assertNull($ticket->assigned_user_id);

		// Warga A menebak kode/berkas milik B.
		$this->assertSame(404, $this->get('warga/laporan/'.$ticket->public_code, 'wa')['status']);
		$this->assertStringNotContainsString('Laporan rahasia milik warga B', $this->get('warga/laporan', 'wa')['body']);
		$file_id = $this->CI->tickets->attachments($ticket->id)[0]->private_file_id;
		$this->assertSame(403, $this->get('berkas/privat/'.$file_id, 'wa')['status']);
		$this->assertSame(200, $this->get('berkas/privat/'.$file_id, 'wb')['status']);
		$this->assertSame(200, $this->get('warga/laporan/'.$ticket->public_code, 'wb')['status']);

		// Warga tidak dapat membuka area pengelola.
		$this->assertSame(303, $this->get('admin/laporan/'.$ticket->public_code, 'wa')['status']);
	}

	public function test_staff_scope_and_editor_denial(): void
	{
		$admin = $this->make_user('admin.h.test', array('service_admin'));
		$officer = $this->make_user('petugas.h.test', array('officer'));
		$editor = $this->make_user('editor.h.test', array('content_editor'));
		$r = $this->submit_ticket();
		$internal = $this->CI->workflow->perform($r['ticket'], 'start_verification', array(), $this->staff_actor($admin));
		$this->CI->workflow->perform($internal, 'follow_up', array('message' => 'Catatan internal rahasia petugas.', 'visibility' => 'internal'), $this->staff_actor($admin));

		$this->assertSame(303, $this->login('petugas.h.test', self::PASSWORD, 'off')['status']);
		$this->assertSame(403, $this->get('admin/laporan/'.$r['ticket']->public_code, 'off')['status'], 'Petugas yang tidak ditugaskan ditolak');
		$data = json_decode($this->get('admin/laporan/data?draw=1&start=0&length=10', 'off', array('Accept: application/json'))['body'], TRUE);
		$this->assertSame(0, $data['recordsTotal']);

		$this->assertSame(303, $this->login('editor.h.test', self::PASSWORD, 'ed')['status']);
		foreach (array('admin/laporan', 'admin/laporan/data', 'admin/pengguna', 'admin/ekspor', 'admin/laporan/'.$r['ticket']->public_code) as $path)
		{
			$this->assertSame(403, $this->get($path, 'ed', array('Accept: application/json'))['status'], $path);
		}

		// Admin melihat catatan internal; pelapor (grant) tidak.
		$this->assertSame(303, $this->login('admin.h.test', self::PASSWORD, 'adm')['status']);
		$this->assertStringContainsString('Catatan internal rahasia petugas', $this->get('admin/laporan/'.$r['ticket']->public_code, 'adm')['body']);
		$this->assertSame(303, $this->post_form('lacak', 'lacak', array('public_code' => $r['ticket']->public_code, 'access_code' => $r['access_code']), 'rep')['status']);
		$this->assertStringNotContainsString('Catatan internal rahasia petugas', $this->get('lacak/detail', 'rep')['body']);

		// Status bebas tidak diterima; hanya aksi dari allowlist.
		$page = $this->get('admin/laporan/'.$r['ticket']->public_code, 'adm');
		$bad = $this->request('POST', 'admin/laporan/'.$r['ticket']->public_code.'/status', 'adm', http_build_query(array(
			'csrf_chw' => $this->csrf_from($page['body']), 'action' => 'resolved', 'version' => 1,
		)));
		$this->assertSame(403, $bad['status']);
	}

	public function test_xss_is_escaped_and_datatables_sort_is_allowlisted(): void
	{
		$admin = $this->make_user('admin.x.test', array('service_admin'));
		$payload = '<script>alert("xss")</script><img src=x onerror=alert(1)>';
		$r = $this->submit_anonymous('xss', array('title' => 'Judul '.$payload, 'description' => 'Uraian panjang berisi '.$payload.' untuk uji keluaran.'));
		$this->assertSame(303, $r['post']['status']);
		$this->assertSame(303, $this->login('admin.x.test', self::PASSWORD, 'adm')['status']);
		$detail = $this->get('admin/laporan/'.$r['code'], 'adm');
		$this->assertStringNotContainsString('<script>alert("xss")</script>', $detail['body']);
		$this->assertStringContainsString('&lt;script&gt;', $detail['body']);

		$json = $this->get('admin/laporan/data?draw=1&start=0&length=10&order[0][column]=99;DROP%20TABLE%20tickets&order[0][dir]=asc%20--&search[value]=%27%20OR%201%3D1%20--', 'adm', array('Accept: application/json'));
		$this->assertSame(200, $json['status']);
		$decoded = json_decode($json['body'], TRUE);
		$this->assertSame(0, $decoded['recordsFiltered'], 'Keyword injeksi diperlakukan sebagai teks');
		$this->assertGreaterThan(0, $this->CI->db->count_all('tickets'));
		$this->assertStringNotContainsString('<script>alert', $json['body']);

		$search = $this->get('cari?q='.rawurlencode($payload), 'anon');
		$this->assertStringNotContainsString('<script>alert("xss")', $search['body']);
	}

	public function test_security_headers_and_blocked_paths(): void
	{
		$home = $this->get('/', 'hdr');
		$this->assertSame(200, $home['status']);
		$csp = $home['headers']['content-security-policy'][0] ?? '';
		$this->assertStringContainsString("script-src 'self'", $csp);
		$this->assertStringContainsString("frame-ancestors 'none'", $csp);
		$this->assertSame('nosniff', $home['headers']['x-content-type-options'][0] ?? '');
		// Auto-routing CI3 dan CLI tidak dapat diakses via HTTP.
		$this->assertSame(404, $this->get('tools/migrate', 'hdr')['status']);
		$this->assertSame(404, $this->get('home/index', 'hdr')['status']);
		$this->assertSame(404, $this->get('admin/laporan/load_ticket/x', 'hdr')['status']);
		$this->assertSame(404, $this->get('.env', 'hdr')['status']);
		$this->assertSame(404, $this->get('../application/config/database.php', 'hdr')['status']);
	}
}
