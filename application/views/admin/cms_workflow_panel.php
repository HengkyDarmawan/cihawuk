<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Panel alur publikasi: aksi yang tampil mengikuti status halaman dan permission pengguna. */
$can = function ($p) use ($permissions) { return in_array($p, $permissions, TRUE); };
$status = $page->status;
$action_url = function ($action) use ($page) {
	return site_url('admin/cms/halaman/'.rawurlencode($page->public_id).'/alur/'.$action);
};
?>
<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Alur publikasi</h2></div>
	<div class="card-body">
		<p class="small text-muted">
			Status saat ini: <span class="chip-flag <?= $status === 'published' ? 'is-info' : '' ?>"><?= e($status_labels[$status] ?? $status) ?></span>
			<?php if ($version): ?><span class="d-block">Draft versi <?= (int) $version->version_no ?> · <?= e($version->title) ?></span><?php endif; ?>
		</p>

		<form method="post" action="<?= site_url('admin/cms/halaman/'.rawurlencode($page->public_id).'/pratinjau') ?>" class="mb-3">
			<?= csrf_field() ?>
			<button class="btn btn-outline-primary btn-sm btn-block" type="submit">Pratinjau draft</button>
			<span class="small text-muted d-block mt-1">Tautan pratinjau berlaku singkat, hanya untuk Anda, dan tidak terindeks mesin pencari.</span>
		</form>

		<?php if (in_array($status, array('draft', 'changes_requested'), TRUE) && $can('cms.page.submit_review')): ?>
		<form method="post" action="<?= $action_url('ajukan') ?>" class="mb-2">
			<?= csrf_field() ?>
			<button class="btn btn-primary btn-sm btn-block" type="submit">Ajukan untuk review</button>
		</form>
		<?php endif; ?>

		<?php if ($status === 'in_review' && $can('cms.page.approve')): ?>
		<form method="post" action="<?= $action_url('setujui') ?>" class="mb-2">
			<?= csrf_field() ?>
			<button class="btn btn-primary btn-sm btn-block" type="submit">Setujui</button>
		</form>
		<form method="post" action="<?= $action_url('minta-perbaikan') ?>" class="mb-3">
			<?= csrf_field() ?>
			<label class="sr-only" for="komentar-review">Komentar perbaikan</label>
			<textarea class="form-control form-control-sm mb-2" id="komentar-review" name="reason" rows="2" maxlength="1000" placeholder="Apa yang perlu diperbaiki? (minimal 10 karakter)"></textarea>
			<button class="btn btn-outline-primary btn-sm btn-block" type="submit">Minta perbaikan</button>
		</form>
		<?php endif; ?>

		<?php if ($can('cms.page.publish') && in_array($status, array('approved', 'scheduled', 'unpublished', 'published'), TRUE)): ?>
		<form method="post" action="<?= $action_url('terbitkan') ?>" class="mb-3"
			data-confirm="Terbitkan susunan draft halaman &quot;<?= e($page->page_key) ?>&quot; sekarang? Halaman publik langsung memakai susunan ini."
			data-confirm-ok="Terbitkan">
			<?= csrf_field() ?>
			<label class="sr-only" for="alasan-terbit">Catatan publikasi</label>
			<input class="form-control form-control-sm mb-2" id="alasan-terbit" name="reason" maxlength="500" placeholder="Catatan publikasi (opsional)">
			<button class="btn btn-primary btn-sm btn-block" type="submit">Terbitkan sekarang</button>
		</form>
		<?php endif; ?>

		<?php if ($can('cms.page.publish') && $status === 'approved'): ?>
		<form method="post" action="<?= $action_url('jadwalkan') ?>" class="mb-3">
			<?= csrf_field() ?>
			<label class="small" for="run_at">Jadwalkan publikasi (WIB)</label>
			<input class="form-control form-control-sm mb-2" type="datetime-local" id="run_at" name="run_at" required>
			<input class="form-control form-control-sm mb-2" name="reason" maxlength="500" placeholder="Catatan (opsional)">
			<button class="btn btn-outline-primary btn-sm btn-block" type="submit">Jadwalkan</button>
		</form>
		<?php endif; ?>

		<?php if ($status === 'published' && $can('cms.page.unpublish')): ?>
		<form method="post" action="<?= $action_url('tarik') ?>"
			data-confirm="Tarik halaman &quot;<?= e($page->page_key) ?>&quot; dari publik? Pengunjung tidak lagi melihat susunan ini sampai diterbitkan lagi."
			data-confirm-ok="Tarik dari publik">
			<?= csrf_field() ?>
			<label class="sr-only" for="alasan-tarik">Alasan penarikan</label>
			<input class="form-control form-control-sm mb-2" id="alasan-tarik" name="reason" maxlength="500" placeholder="Alasan (minimal 10 karakter)">
			<button class="btn btn-outline-danger btn-sm btn-block" type="submit">Tarik dari publik</button>
		</form>
		<?php endif; ?>
	</div>
</div>

<?php if ( ! empty($schedules)): ?>
<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Jadwal publikasi</h2></div>
	<div class="card-body">
		<ul class="list-unstyled mb-0 small">
			<?php foreach ($schedules as $schedule): ?>
			<li class="mb-2">
				<span class="chip-flag <?= $schedule->status === 'failed' ? 'is-danger' : ($schedule->status === 'done' ? 'is-info' : '') ?>"><?= e($schedule->status) ?></span>
				<?= e(format_wib($schedule->run_at, 'short')) ?>
				<?php if ($schedule->last_error): ?><span class="d-block text-danger"><?= e($schedule->last_error) ?></span><?php endif; ?>
			</li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>
<?php endif; ?>

<?php if ( ! empty($reviews)): ?>
<div class="card shadow-sm mb-4">
	<div class="card-header"><h2 class="h6 mb-0">Catatan review</h2></div>
	<div class="card-body">
		<ul class="list-unstyled mb-0 small">
			<?php foreach ($reviews as $review): ?>
			<li class="mb-2">
				<span class="chip-flag"><?= e($review->status) ?></span>
				<?= e(format_wib($review->submitted_at, 'short')) ?> · <?= e($review->submitter ?: 'sistem') ?>
				<?php if ($review->comment): ?><span class="d-block"><?= e($review->comment) ?></span><?php endif; ?>
			</li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>
<?php endif; ?>
