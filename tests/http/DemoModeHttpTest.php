<?php

require_once __DIR__.'/../CiTestCase.php';

/**
 * Mode demonstrasi harus terlihat dan tidak boleh terindeks.
 *
 * Kelas ini menjalankan servernya sendiri dengan DEMO_MODE=true. Variabel proses menang
 * atas .env.testing karena Dotenv dimuat dengan createImmutable, sehingga saklarnya benar
 * benar diuji lewat jalur yang sama dengan pemakaian sungguhan.
 */
class DemoModeHttpTest extends CiTestCase {

	const PORT = 8767;

	/** @var resource|null */
	protected static $server = NULL;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		$cmd = '"'.PHP_BINARY.'" -S 127.0.0.1:'.self::PORT.' -t "'.ROOTPATH.'public" "'.ROOTPATH.'scripts/dev-router.php"';
		$env = array_merge(getenv(), array(
			'APP_ENV' => 'testing',
			'APP_BASE_URL' => 'http://127.0.0.1:'.self::PORT.'/',
			'DEMO_MODE' => 'true',
		));
		$log = sys_get_temp_dir().DIRECTORY_SEPARATOR.'chw-demo-http.log';
		self::$server = proc_open($cmd, array(
			0 => array('pipe', 'r'), 1 => array('file', $log, 'a'), 2 => array('file', $log, 'a'),
		), $pipes, ROOTPATH, $env);

		$deadline = microtime(TRUE) + 15;
		while (microtime(TRUE) < $deadline)
		{
			$fp = @fsockopen('127.0.0.1', self::PORT, $errno, $errstr, 0.3);
			if ($fp)
			{
				fclose($fp);
				register_shutdown_function(array(__CLASS__, 'stop_server'));
				return;
			}
			usleep(150000);
		}
		self::fail('Server mode demo tidak dapat dijalankan pada porta '.self::PORT.'.');
	}

	public static function stop_server()
	{
		if (self::$server === NULL)
		{
			return;
		}
		$status = proc_get_status(self::$server);
		if ( ! empty($status['pid']))
		{
			// Hentikan proses server beserta anaknya; tanpa ini shell ikut menggantung.
			exec('taskkill /F /T /PID '.(int) $status['pid'].' 2>NUL');
		}
		@proc_terminate(self::$server);
		self::$server = NULL;
	}

	public static function tearDownAfterClass(): void
	{
		self::stop_server();
		parent::tearDownAfterClass();
	}

	protected function fetch($path)
	{
		$ch = curl_init('http://127.0.0.1:'.self::PORT.'/'.ltrim($path, '/'));
		curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_TIMEOUT => 25));
		$body = curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return array($code, (string) $body);
	}

	public function test_every_public_page_is_marked_and_not_indexable(): void
	{
		$paths = array('/', 'profil', 'berita', 'agenda', 'fasilitas', 'umkm',
			'potensi', 'data-desa', 'transparansi/anggaran', 'pemerintahan/struktur');

		foreach ($paths as $path)
		{
			list($code, $body) = $this->fetch($path);
			$this->assertSame(200, $code, $path.' harus dapat dibuka.');
			$this->assertStringContainsString('demo-bar', $body,
				$path.' tidak memuat penanda mode demonstrasi.');
			$this->assertStringContainsString('Mode demonstrasi', $body, $path);
			$this->assertStringContainsString('noindex, nofollow', $body,
				$path.' harus noindex selama mode demonstrasi aktif.');
		}
	}

	public function test_marker_states_the_content_is_not_official(): void
	{
		list($code, $body) = $this->fetch('/');
		$this->assertSame(200, $code);
		// Penandanya harus menyebut bahwa isinya contoh, bukan sekadar kata "demo".
		$this->assertStringContainsString('contoh', $body);
		$this->assertStringContainsString('bukan data resmi', $body);
	}
}
