<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CMS konten situs. Menyusun draft membutuhkan content.edit; menerbitkan dan
 * mengarsipkan membutuhkan content.publish (permission terpisah).
 */
class Konten extends Admin_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('ContentService', NULL, 'content_service');
		$this->layout_data['nav_active'] = 'konten';
	}

	public function index()
	{
		$this->require_permission('content.edit');
		$counts = array();
		foreach (array_keys($this->content_service->types()) as $type)
		{
			$counts[$type] = array(
				'total' => $this->content_service->count($type),
				'draft' => $this->content_service->count($type, array('status' => 'draft')),
				'published' => $this->content_service->count($type, array('status' => 'published')),
			);
		}
		$this->render('admin/konten_index', array(
			'page_title' => 'Konten Situs',
			'types' => $this->content_service->types(),
			'counts' => $counts,
			'profile' => $this->db->order_by('id')->limit(1)->get('village_profiles')->row(),
		), 'dashboard');
	}

	public function listing($type)
	{
		$this->require_permission('content.edit');
		$schema = $this->content_service->schema($type);
		$status = (string) $this->input->get('status');
		$page = max(1, (int) $this->input->get('hal'));
		$per_page = 25;
		$filters = array('status' => $status, 'keyword' => (string) $this->input->get('q'));
		$total = $this->content_service->count($type, $filters);

		$this->render('admin/konten_listing', array(
			'page_title' => strip_tags($schema['label']),
			'type' => $type,
			'schema' => $schema,
			'items' => $this->content_service->listing($type, $filters, $per_page, ($page - 1) * $per_page),
			'filters' => $filters,
			'page' => $page,
			'pages' => max(1, (int) ceil($total / $per_page)),
			'total' => $total,
			'can_publish' => $this->authz->can('content.publish'),
			'extra_js' => array('vendor/sweetalert2/sweetalert2.min.js'),
		), 'dashboard');
	}

	public function edit($type, $id = NULL)
	{
		$this->require_permission('content.edit');
		$schema = $this->content_service->schema($type);
		$item = $id ? $this->content_service->find($type, $id) : NULL;
		if ($id && ! $item)
		{
			$this->not_found_response();
			return;
		}
		$this->render('admin/konten_form', $this->form_data($type, $schema, $item), 'dashboard');
	}

	protected function form_data($type, array $schema, $item, array $extra = array())
	{
		$media = $this->db->select('id, original_name, alt_text, mime_type, rights_status, publication_status')
			->where('deleted_at IS NULL', NULL, FALSE)
			->where_in('rights_status', array('owned', 'licensed', 'permission_granted'))
			->order_by('id', 'DESC')->limit(200)->get('media_assets')->result();
		$categories = array();
		foreach ($schema['fields'] as $name => $field)
		{
			if ($field['type'] === 'category')
			{
				$categories[$name] = $this->db->where('content_type', $field['content_type'])->order_by('sort_order')->get('content_categories')->result();
			}
		}
		return array_merge(array(
			'page_title' => ($item ? 'Ubah ' : 'Buat ').strtolower($schema['singular']),
			'type' => $type,
			'schema' => $schema,
			'item' => $item,
			'media_options' => $media,
			'category_options' => $categories,
			'positions' => $this->db->order_by('sort_order')->get('official_positions')->result(),
			'menu_parents' => $this->db->where('parent_id IS NULL', NULL, FALSE)->order_by('menu_key')->order_by('sort_order')->get('navigation_items')->result(),
			'gallery_items' => ($type === 'galeri' && $item) ? $this->content_service->gallery_item_ids($item->id) : array(),
			'can_publish' => $this->authz->can('content.publish'),
			'publication_statuses' => app_config('publication_statuses'),
			'extra_js' => array('vendor/sweetalert2/sweetalert2.min.js'),
		), $extra);
	}

	public function save($type)
	{
		$this->require_method('post');
		$this->require_permission('content.edit');
		$schema = $this->content_service->schema($type);
		$id = (int) $this->input->post('id') ?: NULL;

		$input = array();
		foreach ($schema['fields'] as $name => $field)
		{
			switch ($field['type'])
			{
				case 'checkbox':
					$input[$name] = (bool) $this->input->post($name);
					break;
				case 'cta':
					$input[$name] = array(
						'label' => $this->post_string($name.'_label', 60),
						'url' => trim((string) $this->input->post($name.'_url')),
					);
					break;
				case 'richtext':
					$input[$name] = (string) $this->input->post($name, FALSE);
					break;
				case 'media_multi':
					break;
				default:
					$input[$name] = $this->post_string($name, 12000);
			}
		}
		$this->old_input = array_map(function ($v) { return is_array($v) ? '' : $v; }, $input);

		try
		{
			$saved_id = $this->content_service->save($type, $id, $input, (int) $this->user->id);
			if ($type === 'galeri')
			{
				$ids = (array) $this->input->post('gallery_items');
				$this->content_service->set_gallery_items($saved_id, $ids);
			}
		}
		catch (DomainRuleException $e)
		{
			$this->form_errors = $e->errors;
			$this->output->set_status_header($e->http_status);
			$item = $id ? $this->content_service->find($type, $id) : NULL;
			$this->render('admin/konten_form', $this->form_data($type, $schema, $item, array('save_error' => $e->getMessage())), 'dashboard');
			return;
		}

		$this->flash('success', 'Konten tersimpan sebagai draft/versi terbaru. Gunakan tombol status untuk mengirim ke review atau menerbitkan.');
		redirect(site_url('admin/konten/'.$type.'/'.$saved_id), 'location', 303);
	}

	public function status($type, $id)
	{
		$this->require_method('post');
		$action = (string) $this->input->post('status');
		if (in_array($action, array('published', 'archived'), TRUE))
		{
			$this->require_permission('content.publish');
		}
		else
		{
			$this->require_permission('content.edit');
		}
		if ($action === 'deleted')
		{
			$this->require_permission('content.publish');
			$this->content_service->archive($type, $id, (int) $this->user->id);
			$this->flash('success', 'Konten diarsipkan (tidak dihapus permanen).');
			redirect(site_url('admin/konten/'.$type), 'location', 303);
			return;
		}
		$this->content_service->set_status($type, $id, $action, (int) $this->user->id);
		$labels = array('draft' => 'dikembalikan ke draft', 'in_review' => 'dikirim untuk review', 'published' => 'diterbitkan', 'archived' => 'diarsipkan');
		$this->flash('success', 'Konten '.($labels[$action] ?? 'diperbarui').'.');
		redirect(site_url('admin/konten/'.$type.'/'.(int) $id), 'location', 303);
	}

	// ------------------------------------------------------------ Profil desa

	public function profile()
	{
		$this->require_permission('content.edit');
		$this->render('admin/konten_profil', $this->profile_data(), 'dashboard');
	}

	protected function profile_data(array $extra = array())
	{
		$profile = $this->db->order_by('id')->limit(1)->get('village_profiles')->row();
		return array_merge(array(
			'page_title' => 'Profil Desa',
			'profile' => $profile,
			'mission' => ($profile && $profile->mission_json) ? json_decode($profile->mission_json, TRUE) : array(),
			'hours' => ($profile && $profile->service_hours_json) ? json_decode($profile->service_hours_json, TRUE) : array(),
			'contacts' => ($profile && $profile->contacts_json) ? json_decode($profile->contacts_json, TRUE) : array(),
			'media_options' => $this->db->select('id, original_name, alt_text, rights_status')
				->where('deleted_at IS NULL', NULL, FALSE)
				->where_in('rights_status', array('owned', 'licensed', 'permission_granted'))
				->order_by('id', 'DESC')->limit(200)->get('media_assets')->result(),
			'can_publish' => $this->authz->can('content.publish'),
			'publication_statuses' => app_config('publication_statuses'),
		), $extra);
	}

	public function profile_save()
	{
		$this->require_method('post');
		$this->require_permission('content.edit');
		$profile = $this->db->order_by('id')->limit(1)->get('village_profiles')->row();
		if ( ! $profile)
		{
			throw new DomainRuleException('Profil desa belum tersedia. Jalankan seed master terlebih dahulu.', 409);
		}

		$mission = array();
		foreach ((array) $this->input->post('mission') as $line)
		{
			$line = trim((string) $line);
			if ($line !== '')
			{
				$mission[] = mb_substr($line, 0, 300);
			}
		}
		$status = (string) $this->input->post('publication_status');
		if (in_array($status, array('published', 'archived'), TRUE) && ! $this->authz->can('content.publish'))
		{
			throw new AccessDeniedException('Publishing requires content.publish');
		}
		if ( ! isset(app_config('publication_statuses', array())[$status]))
		{
			$status = $profile->publication_status;
		}

		$data = array(
			'summary' => $this->post_string('summary', 2000),
			'history_html' => $this->content_service->sanitize_html((string) $this->input->post('history_html', FALSE)),
			'vision_official' => $this->post_string('vision_official', 2000),
			'vision_summary' => $this->post_string('vision_summary', 2000),
			'greeting_html' => $this->content_service->sanitize_html((string) $this->input->post('greeting_html', FALSE)),
			'mission_json' => json_encode($mission, JSON_UNESCAPED_UNICODE),
			'office_address' => $this->post_string('office_address', 255),
			'contacts_json' => json_encode(array(
				'phone' => $this->post_string('contact_phone', 30),
				'email' => $this->post_string('contact_email', 191),
				'confirmed' => (bool) $this->input->post('contact_confirmed'),
			), JSON_UNESCAPED_UNICODE),
			'service_hours_json' => json_encode(array(
				'label' => $this->post_string('service_hours', 150),
				'is_example' => (bool) $this->input->post('service_hours_example'),
			), JSON_UNESCAPED_UNICODE),
			'seo_description' => $this->post_string('seo_description', 300),
			'source_note' => $this->post_string('source_note', 500),
			'review_note' => $this->post_string('review_note', 2000),
			'publication_status' => $status,
			'logo_media_id' => ((int) $this->input->post('logo_media_id')) ?: NULL,
			'profile_media_id' => ((int) $this->input->post('profile_media_id')) ?: NULL,
			'updated_by' => (int) $this->user->id,
			'updated_at' => utc_now(),
		);
		if ($status === 'published' && $profile->published_at === NULL)
		{
			$data['published_at'] = utc_now();
		}
		db_must($this->db->where('id', (int) $profile->id)->update('village_profiles', $data), 'village_profiles.update');

		// Kontak publik juga dipakai footer/halaman kontak.
		$this->settings->set('site.contact', array(
			'phone' => $data['contacts_json'] ? json_decode($data['contacts_json'], TRUE)['phone'] : NULL,
			'email' => json_decode($data['contacts_json'], TRUE)['email'],
			'confirmed' => json_decode($data['contacts_json'], TRUE)['confirmed'],
		), 'site', TRUE, (int) $this->user->id);
		$this->settings->set('site.service_hours', json_decode($data['service_hours_json'], TRUE), 'site', TRUE, (int) $this->user->id);

		$this->audit->log('content.profile_saved', 'content', 'village_profile:'.$profile->id, array('status' => $status));
		$this->content_service->invalidate_cache();
		$this->flash('success', 'Profil desa tersimpan.');
		redirect(site_url('admin/konten/profil'), 'location', 303);
	}
}
