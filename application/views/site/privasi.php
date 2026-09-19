<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<section class="page-hero">
	<div class="container-site">
		<h1>Kebijakan privasi</h1>
		<p>Bagaimana Layanan Digital Desa Cihawuk memproses data Anda, siapa yang dapat mengaksesnya, dan berapa lama disimpan.</p>
	</div>
</section>

<section class="page-body">
	<div class="container-site">
		<div class="prose">
			<h2>Data yang diproses</h2>
			<ul>
				<li><strong>Laporan tanpa akun:</strong> jenis, kategori, judul, uraian, lokasi (bila Anda isi), lampiran, dan waktu penerimaan. Nama, NIK, email, dan nomor telepon <em>tidak diminta</em>.</li>
				<li><strong>Akun warga:</strong> nama tampilan, username, password (disimpan sebagai hash), serta email/telepon bila Anda isi.</li>
				<li><strong>Laporan melalui loket:</strong> petugas dapat mencatat kontak Anda bila Anda memberikannya. Kontak tersebut disimpan terenkripsi dan terpisah dari isi laporan.</li>
				<li><strong>Catatan teknis:</strong> log server (termasuk alamat IP) untuk keamanan dan pembatasan penyalahgunaan, dengan retensi terbatas. Untuk pembatasan laju, alamat IP disimpan sebagai nilai ter-hash berkunci, bukan alamat polos.</li>
			</ul>

			<h2>Siapa yang dapat mengakses</h2>
			<ul>
				<li>Petugas pelayanan desa yang berwenang, sesuai peran dan penugasannya. Laporan <strong>tidak</strong> ditampilkan kepada publik.</li>
				<li>Laporan berkategori sensitif hanya dapat dibuka petugas berizin khusus; setiap akses tercatat pada log audit.</li>
				<li>Identitas pelapor berakun hanya dapat dilihat petugas dengan izin khusus, dan pembukaan identitas tercatat.</li>
				<li>Administrator sistem memiliki akses teknis ke server dan basis data. Log audit mengurangi risiko penyalahgunaan, tetapi tidak dapat diklaim mustahil dimanipulasi oleh pemegang akses basis data.</li>
			</ul>

			<h2>Kode akses laporan anonim</h2>
			<p>Kode akses dibuat acak dan hanya disimpan dalam bentuk hash. Kode asli ditampilkan sekali pada halaman bukti penerimaan. Kami tidak dapat memulihkan kode yang hilang, dan tidak menyediakan pemulihan berdasarkan nama atau isi laporan.</p>

			<h2>Penyimpanan dan retensi</h2>
			<ul>
				<li>Laporan dan riwayat penanganannya disimpan sebagai arsip pelayanan desa.</li>
				<li>Lampiran disimpan di luar folder publik dan hanya dapat diunduh melalui pemeriksaan hak akses.</li>
				<li>Metadata lokasi (EXIF) pada foto dihapus saat lampiran diproses ulang.</li>
				<li>Sesi login berakhir otomatis: pengelola <?= (int) $limits['admin']['idle'] ?> menit tanpa aktivitas (maksimal <?= (int) ($limits['admin']['absolute'] / 60) ?> jam), warga <?= (int) $limits['resident']['idle'] ?> menit tanpa aktivitas (maksimal <?= (int) ($limits['resident']['absolute'] / 60) ?> jam).</li>
				<li>Kebijakan retensi rinci untuk data pribadi dan arsip operasional ditetapkan pengelola desa dan dapat berubah; halaman ini diperbarui bila kebijakan tersebut disahkan.</li>
			</ul>

			<h2>Yang tidak kami lakukan</h2>
			<ul>
				<li>Tidak ada pelacak iklan, analytics pihak ketiga, session replay, atau chatbot pihak ketiga pada formulir, pelacakan, dan dashboard.</li>
				<li>Tidak mengirim pesan WhatsApp otomatis hanya karena nomor telepon tersedia.</li>
				<li>Tidak meneruskan data Anda ke instansi lain tanpa proses rujukan yang tercatat pada laporan.</li>
				<li>Belum ada integrasi dengan Dukcapil, SP4N-LAPOR!, atau layanan tanda tangan elektronik.</li>
			</ul>

			<h2>Hak Anda</h2>
			<p>Anda dapat meminta penjelasan mengenai data laporan Anda, meminta koreksi data akun, atau menarik laporan sesuai ketentuan alur layanan. Permintaan disampaikan melalui kantor desa dengan pemeriksaan identitas sesuai prosedur.</p>

			<h2>Kontak pengelola</h2>
			<?php if (is_array($contact) && ! empty($contact['confirmed'])): ?>
				<p><?= e($contact['name']) ?> — <?= e($contact['channel']) ?></p>
			<?php else: ?>
				<p><?= e(is_array($contact) ? $contact['name'] : 'Pengelola Layanan Digital Desa Cihawuk') ?>. Kontak resmi (telepon/email) sedang dikonfirmasi pengelola desa dan akan dicantumkan di sini setelah tersedia. Sementara itu, sampaikan pertanyaan melalui kantor desa.</p>
			<?php endif; ?>
		</div>
	</div>
</section>
