<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Agenda extends Public_Controller {

	public function index()
	{
		$archive = ((string) $this->input->get('arsip')) === '1';
		$data = array(
			'page_title' => 'Agenda Desa',
			'archive' => $archive,
			'extra_css' => array('site/css/calendar.css'),
			'extra_js' => array('site/js/calendar.js'),
		);
		if ($archive)
		{
			// Arsip tetap berupa daftar supaya tautan lama dan riwayat panjang mudah ditelusuri.
			$page = max(1, (int) $this->input->get('hal'));
			$per_page = 12;
			$total = $this->content->count_events(FALSE);
			$data['events'] = $this->content->events(FALSE, $per_page, ($page - 1) * $per_page);
			$data['page'] = $page;
			$data['pages'] = max(1, (int) ceil($total / $per_page));
		}
		else
		{
			$month = agenda_month((string) $this->input->get('bulan'));
			$events = $this->content->events_between($month['from_utc'], $month['to_utc']);
			$data['month'] = $month;
			$data['weeks'] = agenda_month_grid($month, $events);
			$data['events'] = $events;
			// Bila bulan ini kosong, tawarkan loncatan ke kegiatan terdekat berikutnya.
			$next = $events ? array() : $this->content->events(TRUE, 1);
			$data['next_event'] = $next ? $next[0] : NULL;
		}
		$this->render('site/agenda', $data, 'site');
	}

	public function detail($slug)
	{
		$event = $this->content->event_by_slug($slug);
		if ( ! $event)
		{
			$this->not_found_response();
			return;
		}
		$this->render('site/agenda_detail', array(
			'page_title' => $event->title,
			'meta_description' => str_limit_id($event->summary, 160),
			'event' => $event,
		), 'site');
	}
}
