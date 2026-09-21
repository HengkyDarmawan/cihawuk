<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Satu unit fisik: ringkasan, QR, status, mutasi, peminjaman, pemeliharaan, dan riwayat. */
$base = site_url('admin/aset/unit/'.rawurlencode($unit->public_id));
$location_options = array('' => '— belum ditentukan —');
foreach ($locations as $loc) { $location_options[(string) $loc->id] = $loc->name; }
$event_labels = array('status_change' => 'Perubahan status', 'movement_accepted' => 'Mutasi diterima');
$open_loans = array_filter($loans, function ($l) { return $l->status === 'out'; });
$open_maintenance = array_filter($maintenances, function ($m) { return $m->status !== 'completed'; });
$pending_moves = array_filter($movements, function ($m) { return $m->status !== 'accepted'; });
$tabs = array();
if ($can_status) { $tabs['tab-status'] = 'Ubah status'; }
if ($can_move) { $tabs['tab-mutasi'] = 'Mutasi & pinjam'.(count($open_loans) + count($pending_moves) ? ' ('.(count($open_loans) + count($pending_moves)).')' : ''); }
if ($can_maintain) { $tabs['tab-pemeliharaan'] = 'Pemeliharaan'.(count($open_maintenance) ? ' ('.count($open_maintenance).')' : ''); }
$tabs['tab-riwayat'] = 'Riwayat';
$first_tab = array_keys($tabs)[0];
?>
<div class="page-heading">
	<div>
		<h1><?= e($unit->asset_tag) ?></h1>
		<p><a href="<?= site_url('admin/aset/'.rawurlencode($register->public_id)) ?>"><?= e($register->name) ?></a>
			· <?= AssetService::badge('lifecycle', $unit->lifecycle_status) ?> <?= AssetService::badge('condition', $unit->condition_status) ?></p>
	</div>
	<div><a class="btn btn-outline-primary btn-sm" href="<?= site_url('admin/aset/'.rawurlencode($register->public_id)) ?>">Kembali ke register</a></div>
</div>

<?= ui_error_summary($this->form_errors) ?>

<div class="row">
	<div class="col-lg-4 mb-4">
		<div class="card shadow-sm mb-4">
			<div class="card-header d-flex align-items-center justify-content-between">
				<h2 class="h6 mb-0">Label QR</h2>
				<?php if ($token): ?><span class="badge badge-pill badge-success">Aktif</span><?php else: ?><span class="badge badge-pill badge-light border">Belum ada</span><?php endif; ?>
			</div>
			<div class="card-body text-center">
				<?php if ($qr_url): ?>
					<div class="qr-box mx-auto mb-2" data-qr="<?= e($qr_url) ?>" data-qr-caption="<?= e($unit->asset_tag) ?>" role="img" aria-label="Kode QR untuk <?= e($unit->asset_tag) ?>"></div>
					<p class="small text-muted mb-1"><?= e($unit->asset_tag) ?> · versi <?= (int) $token->token_version ?></p>
					<p class="small mb-3"><?= $token->last_scanned_at ? 'Terakhir dipindai '.e(format_wib($token->last_scanned_at, 'short')) : 'Belum pernah dipindai' ?></p>
					<input type="text" class="form-control form-control-sm mb-2 text-center" id="qr-url" value="<?= e($qr_url) ?>" readonly aria-label="Tautan QR">
					<div class="d-flex flex-wrap justify-content-center asset-actions">
						<button class="btn btn-outline-primary btn-sm" type="button" data-copy-value="#qr-url"><i class="fas fa-copy fa-sm mr-1" aria-hidden="true"></i> Salin tautan</button>
						<button class="btn btn-outline-primary btn-sm" type="button" data-qr-download="<?= e($unit->asset_tag) ?>"><i class="fas fa-download fa-sm mr-1" aria-hidden="true"></i> Unduh PNG</button>
						<a class="btn btn-outline-primary btn-sm" href="<?= e($qr_url) ?>" target="_blank" rel="noopener"><i class="fas fa-external-link-alt fa-sm mr-1" aria-hidden="true"></i> Lihat halaman</a>
					</div>
				<?php elseif ($token): ?>
					<p class="text-muted">QR aktif dibuat dengan cara lama sehingga gambarnya tidak dapat ditampilkan ulang. Buat QR baru lalu cetak labelnya.</p>
				<?php else: ?>
					<div class="empty-box py-3"><i class="fas fa-qrcode" aria-hidden="true"></i>Unit ini belum punya QR.</div>
				<?php endif; ?>

				<?php if ($can_labels): ?>
				<div class="border-top pt-3 mt-3 d-flex flex-wrap justify-content-center asset-actions">
					<form method="post" action="<?= site_url('admin/aset/label') ?>" target="_blank">
						<?= csrf_field() ?>
						<input type="hidden" name="unit_ids[]" value="<?= e($unit->public_id) ?>">
						<button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-print fa-sm mr-1" aria-hidden="true"></i> Cetak label</button>
					</form>
					<form method="post" action="<?= $base ?>/qr/terbitkan"<?= $token ? ' data-confirm="Buat QR baru? Label yang sudah tertempel tidak berlaku lagi dan harus diganti." data-confirm-ok="Buat QR baru"' : ' data-once' ?>>
						<?= csrf_field() ?>
						<button class="btn btn-outline-secondary btn-sm" type="submit"><?= $token ? 'Buat ulang QR' : 'Buat QR' ?></button>
					</form>
					<?php if ($token): ?>
					<form method="post" action="<?= $base ?>/qr/cabut" data-confirm="Cabut QR unit ini? Label yang tertempel tidak berlaku lagi." data-confirm-ok="Cabut QR">
						<?= csrf_field() ?>
						<input type="hidden" name="reason" value="Dicabut dari halaman unit">
						<button class="btn btn-outline-danger btn-sm" type="submit">Cabut</button>
					</form>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="card shadow-sm">
			<div class="card-header"><h2 class="h6 mb-0">Ringkasan</h2></div>
			<div class="card-body">
				<dl class="dl-grid mb-0">
					<dt>Barang</dt><dd><?= e($register->name) ?></dd>
					<dt>Merek/tipe</dt><dd><?= e(trim($unit->brand.' '.$unit->model) ?: '—') ?></dd>
					<dt>Lokasi</dt><dd><?= $location ? e($location->name).((int) $location->is_sensitive === 1 ? ' <span class="badge badge-warning">sensitif</span>' : '') : '—' ?></dd>
					<dt>Status</dt><dd><?= AssetService::badge('lifecycle', $unit->lifecycle_status) ?></dd>
					<dt>Kondisi</dt><dd><?= AssetService::badge('condition', $unit->condition_status) ?></dd>
					<dt>Dipinjam</dt><dd><?= $open_loans ? 'Ya' : 'Tidak' ?></dd>
				</dl>
			</div>
		</div>
	</div>

	<div class="col-lg-8 mb-4">
		<div class="card shadow-sm">
			<div class="card-header pb-0 border-bottom-0">
				<ul class="nav nav-tabs card-header-tabs" role="tablist">
					<?php foreach ($tabs as $id => $label): ?>
					<li class="nav-item"><a class="nav-link<?= $id === $first_tab ? ' active' : '' ?>" id="<?= $id ?>-link" data-toggle="tab" href="#<?= $id ?>" role="tab" aria-controls="<?= $id ?>" aria-selected="<?= $id === $first_tab ? 'true' : 'false' ?>"><?= e($label) ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="card-body tab-content">
				<?php if ($can_status): ?>
				<div class="tab-pane fade<?= $first_tab === 'tab-status' ? ' show active' : '' ?>" id="tab-status" role="tabpanel" aria-labelledby="tab-status-link">
					<form method="post" action="<?= $base ?>/status" data-once>
						<?= csrf_field() ?>
						<div class="form-row">
							<div class="col-md-6"><?= ui_select(array('name' => 'to_lifecycle', 'label' => 'Status', 'options' => $lifecycle, 'value' => $unit->lifecycle_status)) ?></div>
							<div class="col-md-6"><?= ui_select(array('name' => 'to_condition', 'label' => 'Kondisi', 'options' => $conditions, 'value' => $unit->condition_status)) ?></div>
							<div class="col-md-4"><?= ui_input(array('name' => 'effective_at', 'label' => 'Tanggal berlaku', 'type' => 'date')) ?></div>
							<div class="col-md-8"><?= ui_input(array('name' => 'reason', 'label' => 'Alasan', 'maxlength' => 500, 'required' => TRUE, 'help' => 'Contoh: barang sudah diterima dan dipakai di kantor.')) ?></div>
						</div>
						<button class="btn btn-primary" type="submit">Simpan perubahan</button>
					</form>
				</div>
				<?php endif; ?>

				<?php if ($can_move): ?>
				<div class="tab-pane fade<?= $first_tab === 'tab-mutasi' ? ' show active' : '' ?>" id="tab-mutasi" role="tabpanel" aria-labelledby="tab-mutasi-link">
					<?php foreach ($pending_moves as $movement): ?>
					<form class="alert alert-info d-flex flex-wrap align-items-center justify-content-between" method="post" action="<?= $base ?>/mutasi/<?= e($movement->public_id) ?>/terima" data-once>
						<?= csrf_field() ?>
						<span class="small">Mutasi menunggu serah terima.</span>
						<button class="btn btn-sm btn-primary" type="submit">Terima mutasi</button>
					</form>
					<?php endforeach; ?>
					<?php foreach ($open_loans as $loan): ?>
					<form class="alert alert-warning mb-3" method="post" action="<?= $base ?>/pinjam/<?= e($loan->public_id) ?>/kembali" data-once>
						<?= csrf_field() ?>
						<div class="small mb-2">Sedang dipinjam sejak <?= e(format_wib($loan->checked_out_at, 'short')) ?>.</div>
						<div class="form-inline">
							<label class="small mr-2" for="rc-<?= e($loan->public_id) ?>">Kondisi saat kembali</label>
							<select class="custom-select custom-select-sm mr-2" id="rc-<?= e($loan->public_id) ?>" name="return_condition">
								<?php foreach ($conditions as $code => $label): ?><option value="<?= e($code) ?>"><?= e($label) ?></option><?php endforeach; ?>
							</select>
							<button class="btn btn-sm btn-outline-dark" type="submit">Catat pengembalian</button>
						</div>
					</form>
					<?php endforeach; ?>

					<h3 class="h6 font-weight-bold">Pindahkan ke lokasi lain</h3>
					<p class="small text-muted">Lokasi baru berlaku setelah serah terima diterima.</p>
					<form method="post" action="<?= $base ?>/mutasi" data-once>
						<?= csrf_field() ?>
						<div class="form-row">
							<div class="col-md-5"><?= ui_select(array('name' => 'to_location_id', 'label' => 'Lokasi tujuan', 'options' => array_slice($location_options, 1, NULL, TRUE), 'required' => TRUE)) ?></div>
							<div class="col-md-7"><?= ui_input(array('name' => 'reason', 'id' => 'move_reason', 'label' => 'Alasan', 'maxlength' => 500, 'required' => TRUE)) ?></div>
						</div>
						<button class="btn btn-outline-primary" type="submit">Ajukan mutasi</button>
					</form>

					<hr>
					<h3 class="h6 font-weight-bold">Catat peminjaman</h3>
					<form method="post" action="<?= $base ?>/pinjam" data-once>
						<?= csrf_field() ?>
						<div class="form-row">
							<div class="col-md-6"><?= ui_input(array('name' => 'borrower_name', 'label' => 'Nama peminjam', 'maxlength' => 180, 'required' => TRUE,
								'help' => 'Disimpan terenkripsi dan tidak tampil publik.')) ?></div>
							<div class="col-md-6"><?= ui_input(array('name' => 'borrower_unit', 'label' => 'Dari unit/lembaga', 'maxlength' => 180)) ?></div>
							<div class="col-md-8"><?= ui_input(array('name' => 'purpose', 'label' => 'Keperluan', 'maxlength' => 500, 'required' => TRUE)) ?></div>
							<div class="col-md-4"><?= ui_input(array('name' => 'due_at', 'label' => 'Rencana kembali', 'type' => 'date')) ?></div>
						</div>
						<button class="btn btn-outline-primary" type="submit">Catat peminjaman</button>
					</form>
				</div>
				<?php endif; ?>

				<?php if ($can_maintain): ?>
				<div class="tab-pane fade<?= $first_tab === 'tab-pemeliharaan' ? ' show active' : '' ?>" id="tab-pemeliharaan" role="tabpanel" aria-labelledby="tab-pemeliharaan-link">
					<?php foreach ($open_maintenance as $maintenance): ?>
					<form class="alert alert-warning mb-3" method="post" action="<?= $base ?>/pemeliharaan/<?= e($maintenance->public_id) ?>/selesai" data-once>
						<?= csrf_field() ?>
						<div class="small mb-2"><strong>Sedang diperbaiki:</strong> <?= e(str_limit_id($maintenance->complaint, 120)) ?></div>
						<div class="form-row">
							<div class="col-md-6 mb-2"><input class="form-control form-control-sm" type="text" name="action_taken" maxlength="600" placeholder="Tindakan yang dilakukan" aria-label="Tindakan yang dilakukan" required></div>
							<div class="col-md-3 mb-2"><select class="custom-select custom-select-sm" name="condition_after" aria-label="Kondisi setelah pemeliharaan">
								<?php foreach ($conditions as $code => $label): ?><option value="<?= e($code) ?>"><?= e($label) ?></option><?php endforeach; ?>
							</select></div>
							<div class="col-md-3 mb-2"><button class="btn btn-sm btn-outline-dark btn-block" type="submit">Selesai</button></div>
						</div>
					</form>
					<?php endforeach; ?>
					<h3 class="h6 font-weight-bold">Catat pemeliharaan baru</h3>
					<form method="post" action="<?= $base ?>/pemeliharaan" data-once>
						<?= csrf_field() ?>
						<div class="form-row">
							<div class="col-md-12"><?= ui_input(array('name' => 'complaint', 'label' => 'Keluhan/kerusakan', 'maxlength' => 600, 'required' => TRUE)) ?></div>
							<div class="col-md-8"><?= ui_input(array('name' => 'vendor', 'label' => 'Bengkel/vendor', 'maxlength' => 180)) ?></div>
							<div class="col-md-4"><?= ui_input(array('name' => 'planned_at', 'label' => 'Rencana', 'type' => 'date')) ?></div>
						</div>
						<button class="btn btn-outline-primary" type="submit">Catat pemeliharaan</button>
					</form>
					<p class="small text-muted mt-3 mb-0">Menyelesaikan pemeliharaan tidak otomatis mengubah kondisi menjadi baik; nilai kondisinya di tab Ubah status.</p>
				</div>
				<?php endif; ?>

				<div class="tab-pane fade<?= $first_tab === 'tab-riwayat' ? ' show active' : '' ?>" id="tab-riwayat" role="tabpanel" aria-labelledby="tab-riwayat-link">
					<?php if (empty($events)): ?>
						<p class="text-muted mb-0">Belum ada perubahan status.</p>
					<?php else: ?>
					<ul class="timeline">
						<?php foreach ($events as $event): ?>
						<li>
							<span class="tl-dot" aria-hidden="true"><i class="fas fa-circle"></i></span>
							<div class="tl-title"><?= e($event_labels[$event->event_type] ?? $event->event_type) ?></div>
							<div class="tl-meta"><?= e(format_wib($event->effective_at, 'short')) ?></div>
							<div class="small mt-1">
								<?php if ($event->from_lifecycle || $event->to_lifecycle): ?>Status: <?= $event->from_lifecycle ? AssetService::badge('lifecycle', $event->from_lifecycle).' &rarr; ' : '' ?><?= AssetService::badge('lifecycle', (string) $event->to_lifecycle) ?><br><?php endif; ?>
								<?php if ($event->from_condition || $event->to_condition): ?>Kondisi: <?= $event->from_condition ? AssetService::badge('condition', $event->from_condition).' &rarr; ' : '' ?><?= AssetService::badge('condition', (string) $event->to_condition) ?><?php endif; ?>
							</div>
							<?php if ($event->reason): ?><div class="tl-body small"><?= e($event->reason) ?></div><?php endif; ?>
						</li>
						<?php endforeach; ?>
					</ul>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>
