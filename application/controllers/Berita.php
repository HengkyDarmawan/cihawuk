<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Berita extends Public_Controller {

	protected $per_page = 9;

	public function index()
	{
		$this->listing(NULL);
	}

	public function kategori($slug)
	{
		$this->listing($slug);
	}

	protected function listing($category_slug)
	{
		$page = max(1, (int) $this->input->get('hal'));
		$total = $this->content->count_posts(array('news', 'announcement'), $category_slug);
		$this->render('site/berita', array(
			'page_title' => $category_slug ? 'Berita' : 'Berita dan Pengumuman',
			'posts' => $this->content->posts(array('news', 'announcement'), $category_slug, $this->per_page, ($page - 1) * $this->per_page),
			'categories' => $this->db->where('content_type', 'news')->order_by('sort_order')->get('content_categories')->result(),
			'active_category' => $category_slug,
			'page' => $page,
			'pages' => max(1, (int) ceil($total / $this->per_page)),
			'total' => $total,
		), 'site');
	}

	public function detail($slug, $type = NULL)
	{
		$post = $this->content->post_by_slug($slug);
		if ( ! $post)
		{
			$redirect = $this->content->redirect_for('post', $slug);
			if ($redirect)
			{
				redirect(site_url('berita/'.rawurlencode($redirect->new_slug)), 'location', 301);
				return;
			}
			$this->not_found_response();
			return;
		}
		$this->render('site/berita_detail', array(
			'page_title' => $post->title,
			'meta_description' => str_limit_id($post->excerpt ?: $post->body_html, 160),
			'post' => $post,
			'related' => $this->content->posts(array('news', 'announcement'), $post->category_slug, 3, 0, $post->id),
		), 'site');
	}
}
