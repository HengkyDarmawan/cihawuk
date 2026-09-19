<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Agenda extends Public_Controller {

	public function index()
	{
		$archive = ((string) $this->input->get('arsip')) === '1';
		$page = max(1, (int) $this->input->get('hal'));
		$per_page = 12;
		$total = $this->content->count_events( ! $archive);
		$this->render('site/agenda', array(
			'page_title' => 'Agenda Desa',
			'events' => $this->content->events( ! $archive, $per_page, ($page - 1) * $per_page),
			'archive' => $archive,
			'page' => $page,
			'pages' => max(1, (int) ceil($total / $per_page)),
		), 'site');
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
