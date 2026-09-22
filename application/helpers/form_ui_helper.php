<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Komponen form sebagai fungsi (bukan partial view) karena CI3 menyimpan variabel
 * view antar-pemanggilan sehingga opsi partial dapat bocor ke field lain.
 * Semua label terhubung ke field, error terhubung via aria-describedby.
 */

if ( ! function_exists('ui_field_meta'))
{
	function ui_field_meta(array $o)
	{
		$CI =& get_instance();
		$name = $o['name'];
		$id = $o['id'] ?? preg_replace('/[^a-z0-9_\-]/i', '_', $name);
		$error_key = $o['error_key'] ?? $name;
		$has_error = isset($CI->form_errors[$error_key]) && $CI->form_errors[$error_key] !== '';
		$described = array();
		if ( ! empty($o['help']))
		{
			$described[] = $id.'-help';
		}
		if ($has_error)
		{
			$described[] = 'err-'.$error_key;
		}
		return array($id, $error_key, $has_error, $described);
	}
}

if ( ! function_exists('ui_label'))
{
	function ui_label($id, $label, $required, $optional_hint = TRUE)
	{
		$suffix = $required
			? ' <span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden sr-only"> (wajib)</span>'
			: ($optional_hint ? ' <span class="text-muted fw-normal">(opsional)</span>' : '');
		return '<label class="form-label" for="'.e($id).'">'.e($label).$suffix.'</label>';
	}
}

if ( ! function_exists('ui_help_error'))
{
	function ui_help_error($id, $error_key, array $o)
	{
		$html = '';
		if ( ! empty($o['help']))
		{
			$html .= '<div class="form-text" id="'.e($id).'-help">'.e($o['help']).'</div>';
		}
		return $html.field_error($error_key);
	}
}

if ( ! function_exists('ui_attrs'))
{
	function ui_attrs(array $o, $has_error, array $described)
	{
		$attrs = '';
		foreach (array('autocomplete', 'maxlength', 'minlength', 'inputmode', 'placeholder', 'pattern', 'min', 'max', 'step', 'accept', 'rows') as $key)
		{
			if (isset($o[$key]) && $o[$key] !== '' && $o[$key] !== NULL)
			{
				$attrs .= ' '.$key.'="'.e($o[$key]).'"';
			}
		}
		foreach (array('required', 'disabled', 'readonly', 'multiple', 'autofocus') as $flag)
		{
			if ( ! empty($o[$flag]))
			{
				$attrs .= ' '.$flag;
			}
		}
		if ( ! empty($o['data']) && is_array($o['data']))
		{
			foreach ($o['data'] as $k => $v)
			{
				$attrs .= ' data-'.e($k).'="'.e($v).'"';
			}
		}
		if ( ! empty($o['raw_attrs']))
		{
			$attrs .= ' '.$o['raw_attrs'];
		}
		if ( ! empty($described))
		{
			$attrs .= ' aria-describedby="'.e(implode(' ', $described)).'"';
		}
		if ($has_error)
		{
			$attrs .= ' aria-invalid="true"';
		}
		return $attrs;
	}
}

if ( ! function_exists('ui_input'))
{
	function ui_input(array $o)
	{
		list($id, $error_key, $has_error, $described) = ui_field_meta($o);
		$value = array_key_exists('value', $o) ? $o['value'] : old($o['name']);
		$type = $o['type'] ?? 'text';
		$wrap = $o['wrap_class'] ?? 'mb-3';
		return '<div class="'.e($wrap).'"'.( ! empty($o['group']) ? ' data-field-group="'.e($o['group']).'"' : '').'>'
			.ui_label($id, $o['label'], ! empty($o['required']), $o['optional_hint'] ?? TRUE)
			.'<input class="form-control'.($has_error ? ' is-invalid' : '').'" type="'.e($type).'" id="'.e($id).'" name="'.e($o['name']).'"'
			.($type !== 'file' ? ' value="'.e($value).'"' : '')
			.ui_attrs($o, $has_error, $described).'>'
			.ui_help_error($id, $error_key, $o)
			.'</div>';
	}
}

if ( ! function_exists('ui_textarea'))
{
	function ui_textarea(array $o)
	{
		list($id, $error_key, $has_error, $described) = ui_field_meta($o);
		$value = array_key_exists('value', $o) ? $o['value'] : old($o['name']);
		$wrap = $o['wrap_class'] ?? 'mb-3';
		return '<div class="'.e($wrap).'"'.( ! empty($o['group']) ? ' data-field-group="'.e($o['group']).'"' : '').'>'
			.ui_label($id, $o['label'], ! empty($o['required']), $o['optional_hint'] ?? TRUE)
			.'<textarea class="form-control'.($has_error ? ' is-invalid' : '').'" id="'.e($id).'" name="'.e($o['name']).'"'
			.ui_attrs($o, $has_error, $described).'>'.e($value).'</textarea>'
			.ui_help_error($id, $error_key, $o)
			.'</div>';
	}
}

if ( ! function_exists('ui_select'))
{
	/**
	 * $o['options']: array value => label, atau array of ['value','label','data'=>[]]
	 */
	function ui_select(array $o)
	{
		list($id, $error_key, $has_error, $described) = ui_field_meta($o);
		$selected = array_key_exists('value', $o) ? $o['value'] : old($o['name']);
		$wrap = $o['wrap_class'] ?? 'mb-3';
		$html = '<div class="'.e($wrap).'"'.( ! empty($o['group']) ? ' data-field-group="'.e($o['group']).'"' : '').'>'
			.ui_label($id, $o['label'], ! empty($o['required']), $o['optional_hint'] ?? TRUE)
			.'<select class="form-select'.($has_error ? ' is-invalid' : '').(isset($o['select_class']) ? ' '.e($o['select_class']) : '').'" id="'.e($id).'" name="'.e($o['name']).'"'
			.ui_attrs($o, $has_error, $described).'>';
		if (array_key_exists('placeholder_option', $o))
		{
			$html .= '<option value="">'.e($o['placeholder_option']).'</option>';
		}
		foreach ($o['options'] as $key => $opt)
		{
			if (is_array($opt))
			{
				$value = (string) $opt['value'];
				$label = $opt['label'];
				$data = '';
				foreach (($opt['data'] ?? array()) as $dk => $dv)
				{
					$data .= ' data-'.e($dk).'="'.e($dv).'"';
				}
			}
			else
			{
				$value = (string) $key;
				$label = $opt;
				$data = '';
			}
			$is_selected = is_array($selected) ? in_array($value, array_map('strval', $selected), TRUE) : ((string) $selected === $value);
			$html .= '<option value="'.e($value).'"'.$data.($is_selected ? ' selected' : '').'>'.e($label).'</option>';
		}
		return $html.'</select>'.ui_help_error($id, $error_key, $o).'</div>';
	}
}

if ( ! function_exists('ui_password'))
{
	function ui_password(array $o)
	{
		list($id, $error_key, $has_error, $described) = ui_field_meta($o);
		$o['maxlength'] = $o['maxlength'] ?? 256;
		return '<div class="mb-3">'
			.ui_label($id, $o['label'], ! empty($o['required']), FALSE)
			.'<div class="password-field"><input class="form-control'.($has_error ? ' is-invalid' : '').'" type="password" id="'.e($id).'" name="'.e($o['name']).'"'
			.ui_attrs($o, $has_error, $described).'>'
			.'<button class="password-toggle" type="button" data-password-toggle aria-controls="'.e($id).'" aria-pressed="false" aria-label="Tampilkan password">'.icon('eye').'</button></div>'
			.ui_help_error($id, $error_key, $o)
			.'</div>';
	}
}

if ( ! function_exists('ui_error_summary'))
{
	function ui_error_summary(array $labels = array(), array $anchors = array())
	{
		$CI =& get_instance();
		$errors = array_filter((array) $CI->form_errors);
		if (empty($errors))
		{
			return '';
		}
		$html = '<div class="error-summary mb-4" role="alert" tabindex="-1" data-error-summary><strong>'.icon('alert-circle').' Ada '.count($errors).' isian yang perlu diperbaiki:</strong><ul>';
		foreach ($errors as $field => $message)
		{
			$anchor = $anchors[$field] ?? preg_replace('/[^a-z0-9_\-]/i', '_', $field);
			$html .= '<li><a href="#'.e($anchor).'">'.(isset($labels[$field]) ? e($labels[$field]).': ' : '').e($message).'</a></li>';
		}
		return $html.'</ul></div>';
	}
}

/*
 * Popup form tambah (Bootstrap 4). Form tambah tidak ditempel di bawah tabel; halaman daftar
 * hanya menampilkan tombol "+ Tambah …" yang membuka modal berisi form yang sama.
 */
if ( ! function_exists('ui_add_button'))
{
	function ui_add_button($target, $label, $class = 'btn-primary')
	{
		return '<button class="btn '.e($class).' btn-add" type="button" data-toggle="modal" data-target="#'.e($target).'">'
			.'<i class="fas fa-plus mr-1" aria-hidden="true"></i> '.e($label).'</button>';
	}
}

if ( ! function_exists('ui_modal_open'))
{
	/** $size: '', 'modal-lg', atau 'modal-xl'. $open: tampilkan langsung saat halaman dimuat. */
	function ui_modal_open($id, $title, $size = '', $open = FALSE)
	{
		return '<div class="modal fade form-modal" id="'.e($id).'" tabindex="-1" role="dialog" aria-labelledby="'.e($id).'-title" aria-hidden="true"'.($open ? ' data-open-on-load' : '').'>'
			.'<div class="modal-dialog modal-dialog-scrollable '.e($size).'" role="document"><div class="modal-content">'
			.'<div class="modal-header"><h2 class="modal-title h5" id="'.e($id).'-title">'.e($title).'</h2>'
			.'<button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button></div>'
			.'<div class="modal-body">';
	}
}

if ( ! function_exists('ui_modal_close'))
{
	function ui_modal_close()
	{
		return '</div></div></div></div>';
	}
}

if ( ! function_exists('ui_modal_actions'))
{
	/** Baris tombol di akhir form dalam modal: Batal + tombol kirim. */
	function ui_modal_actions($submit_label = 'Simpan')
	{
		return '<div class="modal-actions"><button class="btn btn-light" type="button" data-dismiss="modal">Batal</button>'
			.'<button class="btn btn-primary" type="submit"><i class="fas fa-check mr-1" aria-hidden="true"></i> '.e($submit_label).'</button></div>';
	}
}
