<?php

/** DATA-01: normalisasi sadar tipe. */
class SourceNormalizationTest extends CiTestCase {

	public function test_indonesian_number_formats(): void
	{
		$n = $this->CI->source_import;
		$this->assertSame(array('value' => '6809', 'type' => 'integer', 'unit' => NULL), $n->normalize('6.809'));
		$this->assertSame(array('value' => '932.35', 'type' => 'decimal', 'unit' => NULL), $n->normalize('932,35'));
		$this->assertSame(array('value' => '1234.5', 'type' => 'decimal', 'unit' => NULL), $n->normalize('1.234,5'));
		$this->assertSame('ha', $n->normalize('15,50 ha')['unit']);
		$this->assertSame('15.5', $n->normalize('15,50 ha')['value']);
	}

	public function test_codes_and_missing_values(): void
	{
		$n = $this->CI->source_import;
		$this->assertSame(array('value' => '320431.2006', 'type' => 'code', 'unit' => NULL), $n->normalize('320431.2006'));
		$this->assertNull($n->normalize('-')['value']);
		$this->assertSame('null', $n->normalize('-')['type']);
		$this->assertSame('null', $n->normalize('')['type']);
		// "Ada/Tidak" tetap teks, bukan jawaban boolean otomatis.
		$this->assertSame('text', $n->normalize('Ada/Tidak')['type']);
		$this->assertNull($n->normalize('Ada/Tidak')['value']);
	}

	public function test_real_docx_extraction_when_available(): void
	{
		$path = ROOTPATH.'reference/documents/3 Potensi Desa Cihawuk 2023.docx';
		if ( ! is_file($path))
		{
			$this->markTestSkipped('Dokumen S1 tidak tersedia di reference/documents.');
		}
		$observations = $this->CI->source_import->blocks_to_observations($this->CI->source_import->extract_blocks($path));
		$this->assertGreaterThan(1000, count($observations));
		$found = array_filter($observations, function ($o) { return $o['raw_value'] === '6.809' && $o['normalized_value'] === '6809'; });
		$this->assertNotEmpty($found, 'Nilai total penduduk 6.809 harus terbaca sebagai 6809');
		// Locator unik per observasi (merged cell tidak diduplikasi).
		$keys = array_map(function ($o) { return $o['source_locator'].'|'.$o['field_key']; }, $observations);
		$this->assertSame(count($keys), count(array_unique($keys)));
	}
}
