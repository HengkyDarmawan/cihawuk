<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Halaman publik hasil pindai QR aset (prompt-master 18.4.2).
 *
 * Halaman ini menjawab "barang ini apa" dan tidak pernah menampilkan harga, dokumen
 * kepemilikan, nomor seri, nama penanggung jawab, lokasi penyimpanan sensitif, biaya
 * pemeliharaan, maupun catatan audit internal. Selalu `noindex` dan di luar sitemap.
 *
 * Seluruh keadaan punya halaman sendiri: aktif, pemeliharaan, tidak aktif, dihapuskan,
 * hilang, token dicabut, dan QR tidak valid. Tidak ada 500 atau halaman kosong.
 */
class Aset extends Public_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('AssetService', NULL, 'assets');
	}

	public function q($token = '')
	{
		$ip = $this->input->ip_address();
		if ($this->rate_limiter->too_many('asset_qr_ip', $ip))
		{
			$this->respond_error(429, 'Terlalu banyak pemindaian dari jaringan ini. Coba lagi beberapa saat lagi.');
			return;
		}
		$this->rate_limiter->hit('asset_qr_ip', $ip);

		$result = $this->assets->resolve_token($token);
		$view = NULL;
		if ($result['status'] === 'ok' && $result['unit'])
		{
			$view = $this->assets->public_unit_view($result['unit']);
			// Unit yang masih draft belum pernah dinyatakan ada; jangan diakui publik.
			if ($result['unit']->lifecycle_status === 'draft')
			{
				$result['status'] = 'unknown';
				$view = NULL;
			}
		}

		$this->output->set_header('X-Robots-Tag: noindex, nofollow');
		$this->render('site/aset_qr', array(
			'page_title' => $view ? $view['name'] : 'Identitas aset desa',
			'noindex' => TRUE,
			'status' => $result['status'],
			'asset' => $view,
			'media' => ($view && $view['media_id']) ? $this->content->media($view['media_id']) : NULL,
		), 'site');
	}
}
