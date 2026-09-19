<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Dokumen publik: hanya salinan yang sudah disetujui. Dokumen sumber penelitian
 * tetap privat dan tidak pernah tampil di sini.
 */
class Dokumen extends Public_Controller {

	public function index()
	{
		$category = (string) $this->input->get('kategori');
		$year = (int) $this->input->get('tahun');
		$documents = $this->content->public_documents($category !== '' ? $category : NULL, $year > 0 ? $year : NULL);
		$years = array();
		foreach ($this->content->public_documents() as $doc)
		{
			if ($doc->source_year)
			{
				$years[(int) $doc->source_year] = TRUE;
			}
		}
		krsort($years);
		$this->render('site/dokumen', array(
			'page_title' => 'Dokumen Publik',
			'documents' => $documents,
			'categories' => $this->db->where('content_type', 'document')->order_by('sort_order')->get('content_categories')->result(),
			'years' => array_keys($years),
			'active_category' => $category,
			'active_year' => $year,
		), 'site');
	}

	public function unduh($id)
	{
		$document = $this->content->public_document((int) $id);
		if ( ! $document)
		{
			$this->not_found_response();
			return;
		}
		$path = FCPATH.'media/'.ltrim($document->storage_key, '/');
		$real = realpath($path);
		if ($real === FALSE OR strpos(str_replace('\\', '/', $real), str_replace('\\', '/', realpath(FCPATH.'media')).'/') !== 0 OR ! is_file($real))
		{
			$this->not_found_response();
			return;
		}
		$this->audit->log('document.download', 'public_document', (string) $document->id, array('title' => $document->title));
		$this->output
			->set_header('Content-Type: '.$document->mime_type)
			->set_header('Content-Disposition: attachment; filename="'.str_replace('"', '', $document->original_name).'"')
			->set_header('Content-Length: '.filesize($real))
			->set_header('X-Content-Type-Options: nosniff');
		$this->output->_display();
		readfile($real);
		exit(EXIT_SUCCESS);
	}
}
