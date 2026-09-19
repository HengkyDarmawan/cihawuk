<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Beranda dirender dari susunan section yang diterbitkan (snapshot CMS).
 * Tidak ada urutan atau isi yang ditulis permanen di view ini.
 */
?>
<?php if (empty($layout['sections'])): ?>
	<section class="section">
		<div class="container-site">
			<div class="empty-state">
				<?= icon('layout') ?>
				<h1 class="h4">Beranda sedang disusun</h1>
				<p class="mb-3">Pengelola desa sedang menyiapkan tampilan beranda. Layanan tetap dapat digunakan.</p>
				<div class="d-flex flex-wrap gap-2 justify-content-center">
					<a class="btn btn-primary" href="<?= site_url('lapor') ?>">Buat Laporan</a>
					<a class="btn btn-outline-primary" href="<?= site_url('lacak') ?>">Lacak Laporan</a>
				</div>
			</div>
		</div>
	</section>
<?php else: ?>
	<?php foreach ($layout['sections'] as $section): ?>
		<?php if (is_file(APPPATH.'views/site/sections/'.$section['type'].'.php')): ?>
			<?php $this->load->view('site/sections/'.$section['type'], array('s' => $section, 'd' => $section['data'])); ?>
		<?php endif; ?>
	<?php endforeach; ?>
<?php endif; ?>
