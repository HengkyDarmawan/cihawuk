<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Unduhan berkas privat. Setiap permintaan memeriksa hak pada objek induk
 * (tiket/pesan) dan visibility berkas; tidak ada URL penyimpanan langsung.
 */
class Berkas extends Public_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->no_store = TRUE;
		$this->start_session();
		$this->load->model('Ticket_model', 'tickets');
		$this->load->library('UploadService', NULL, 'uploads');
		$this->load->library('TicketAccessService', NULL, 'ticket_access');
	}

	public function privat($id)
	{
		$file = $this->uploads->file((int) $id);
		if ( ! $file)
		{
			$this->not_found();
			return;
		}

		$allowed = FALSE;
		$context = array();

		if ($file->purpose === 'ticket_attachment')
		{
			$attachment = $this->tickets->attachment_by_file($file->id);
			if ( ! $attachment)
			{
				$this->not_found();
				return;
			}
			$ticket = $this->tickets->find($attachment->ticket_id);
			$context = array('ticket' => $ticket->public_code, 'visibility' => $attachment->visibility);

			$user = $this->auth->user();
			if ($user)
			{
				$abilities = $this->authz->ticket_abilities($user->id, $ticket);
				if ($abilities['view'])
				{
					// Lampiran internal hanya untuk petugas dengan akses tiket.
					$allowed = TRUE;
				}
				elseif ($ticket->reporter_user_id !== NULL && (int) $ticket->reporter_user_id === (int) $user->id)
				{
					$allowed = ($attachment->visibility === 'reporter');
				}
			}
			if ( ! $allowed && $attachment->visibility === 'reporter')
			{
				$granted = $this->ticket_access->granted_ticket();
				$allowed = ($granted && (int) $granted->id === (int) $ticket->id);
			}
		}
		elseif ($file->purpose === 'source_document')
		{
			$allowed = $this->auth->check() && $this->authz->can('statistics.review');
			$context = array('purpose' => 'source_document');
		}
		elseif ($file->purpose === 'export')
		{
			// Ekspor diunduh melalui /admin/ekspor/{id}/unduh agar izin dicek ulang.
			$allowed = FALSE;
		}

		if ( ! $allowed)
		{
			$this->audit->log('file.download_denied', 'private_file', (string) $file->id, $context);
			$this->forbidden();
			return;
		}

		$this->audit->log('file.download', 'private_file', (string) $file->id, $context);
		$this->uploads->send_download($file);
	}
}
