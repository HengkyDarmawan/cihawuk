<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="page-heading">
	<div>
		<h1>Menu dashboard</h1>
		<p>Ubah label, grup, dan urutan menu, matikan item, atau sembunyikan item untuk role tertentu.</p>
	</div>
</div>

<?php $this->load->view('admin/rbac_tabs', array('tab' => $tab)); ?>

<div class="alert alert-warning small">
	Menyembunyikan menu <strong>tidak mencabut izin</strong>: halaman tetap dapat dibuka lewat URL bila role memegang izinnya. Untuk menutup akses, cabut izinnya di tab <a href="<?= site_url('admin/rbac') ?>">Role</a>.
	Item tetap tampil bila setidaknya satu role pengguna tidak menyembunyikannya. Item bertanda <i class="fas fa-lock" aria-hidden="true"></i><span class="sr-only">terkunci</span> selalu tampil.
</div>

<form method="post" action="<?= site_url('admin/rbac/menu') ?>" data-once>
	<?= csrf_field() ?>
	<div class="card shadow-sm mb-4">
		<div class="table-responsive">
			<table class="table table-sm mb-0 align-middle">
				<thead>
					<tr>
						<th scope="col">Item</th>
						<th scope="col" style="min-width:180px">Label</th>
						<th scope="col" style="min-width:160px">Grup</th>
						<th scope="col" style="width:90px">Urutan</th>
						<th scope="col" class="text-center">Aktif</th>
						<th scope="col" style="min-width:220px">Sembunyikan untuk role</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($items as $key => $item):
					$o = $overrides[$key] ?? NULL;
					$is_locked = in_array($key, $locked, TRUE);
					$k = e($key); ?>
					<tr>
						<th scope="row" class="small">
							<i class="fas fa-fw <?= e($item['icon']) ?> text-gray-500" aria-hidden="true"></i>
							<code><?= $k ?></code><?php if ($is_locked): ?> <i class="fas fa-lock text-muted" aria-hidden="true"></i><span class="sr-only">terkunci</span><?php endif; ?>
							<div class="text-muted font-weight-normal"><?= e($item['url']) ?></div>
						</th>
						<td><input class="form-control form-control-sm" name="items[<?= $k ?>][label]" maxlength="80" aria-label="Label <?= $k ?>" value="<?= e($o && $o->label !== NULL ? $o->label : $item['label']) ?>"></td>
						<td><input class="form-control form-control-sm" name="items[<?= $k ?>][group_label]" maxlength="80" aria-label="Grup <?= $k ?>" value="<?= e($o && $o->group_label !== NULL ? $o->group_label : (string) $item['heading']) ?>" placeholder="(tanpa judul)"></td>
						<td><input class="form-control form-control-sm" type="number" min="0" max="99999" name="items[<?= $k ?>][sort_order]" aria-label="Urutan <?= $k ?>" value="<?= (int) ($o && $o->sort_order !== NULL ? $o->sort_order : $item['default_order']) ?>"></td>
						<td class="text-center">
							<input type="checkbox" name="items[<?= $k ?>][active]" value="1" aria-label="Aktifkan <?= $k ?>" <?= ( ! $o || (int) $o->active === 1) ? 'checked' : '' ?> <?= $is_locked ? 'disabled checked' : '' ?>>
						</td>
						<td>
							<?php if ($is_locked): ?>
								<span class="small text-muted">Selalu tampil</span>
							<?php else: ?>
								<select class="form-control form-control-sm" name="hidden[<?= $k ?>][]" multiple size="3" aria-label="Role yang tidak melihat <?= $k ?>" data-select2>
									<?php foreach ($roles as $role): ?>
									<option value="<?= (int) $role->id ?>" <?= ! empty($hidden[(int) $role->id][$key]) ? 'selected' : '' ?>><?= e($role->name) ?></option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<button class="btn btn-primary" type="submit">Simpan pengaturan menu</button>
</form>
