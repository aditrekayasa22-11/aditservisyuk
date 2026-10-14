<?php
declare(strict_types=1);

/**
 * Beranda ServisYuk (halaman publik).
 * Helper, koneksi database, dan layout dipakai dari folder backend
 * agar satu sumber kebenaran untuk seluruh aplikasi.
 */
require_once __DIR__ . '/backend/includes/functions.php';

$pageTitle = 'Beranda';

/** Kartu layanan diambil dari tabel service_types (bukan data tetap). */
$serviceTypes = [];
$dbError = null;
try {
    $serviceTypes = db()->query(
        'SELECT name, description, base_price FROM service_types ORDER BY base_price, name'
    )->fetchAll();
} catch (PDOException $exception) {
    $dbError = 'Database belum dapat diakses. Pastikan MySQL aktif dan database sudah diimpor dari database.sql.';
}

/**
 * Ikon layanan dipilih sesuai nama jenis servis (kartu tampil beda per layanan),
 * dengan 'wrench' sebagai ikon cadangan untuk layanan yang belum dikenali.
 */
$serviceIconMap = [
    'oli' => 'droplet',
    'rem' => 'disc',
    'tune' => 'gauge',
    'ban' => 'circle-dot',
    'ac' => 'snowflake',
    'cuci' => 'sparkles',
    'poles' => 'sparkles',
];
$serviceIconFor = static function (string $name) use ($serviceIconMap): string {
    $lower = strtolower($name);
    foreach ($serviceIconMap as $keyword => $iconName) {
        if (str_contains($lower, $keyword)) {
            return $iconName;
        }
    }
    return 'wrench';
};

require __DIR__ . '/backend/includes/header.php';
?>
<!-- HERO -->
<!-- Foto bengkel motor pada hero diambil dari referensi yang diberikan. -->
<section class="hero hero-photo home-hero">
    <div class="container hero-inner">
        <div class="hero-copy">
            <span class="eyebrow eyebrow-light"><span class="eyebrow-dot"></span> BENGKEL MOTOR • SERVIS TANPA RIBET</span>
            <h1>Motor prima,<br>perjalanan lebih <em>tenang.</em></h1>
            <p class="hero-description">Pantau status pengerjaan, keluhan, estimasi biaya, dan riwayat servis kendaraan Anda kapan saja — cukup dengan nomor polisi, tanpa perlu membuat akun.</p>

            <div class="hero-actions">
                <a class="button button-primary" href="cek-status.php">Cek Status Servis <?= icon('arrow-right') ?></a>
                <a class="button button-ghost button-ghost-light" href="#layanan">Lihat Layanan</a>
            </div>

            <div class="hero-points">
                <span><b><?= icon('check') ?></b> Status servis terkini</span>
                <span><b><?= icon('check') ?></b> Biaya transparan</span>
                <span><b><?= icon('check') ?></b> Riwayat tersimpan aman</span>
            </div>
        </div>

        <div class="hero-art">
            <div class="hero-photo-frame">
                <img class="hero-garage-image" src="<?= e($base) ?>assets/img/motorcycle-garage.jpg"
                     alt="Bengkel motor bernuansa hangat dengan motor klasik berwarna merah">
                <div class="hero-photo-caption">
                    <span class="hero-caption-icon"><?= icon('wrench') ?></span>
                    <span><strong>Rawat dengan sepenuh hati</strong><small>Siap menemani setiap perjalanan</small></span>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="trust-strip" aria-label="Keunggulan layanan ServisYuk">
    <div class="container trust-grid">
        <div class="trust-item reveal"><span><?= icon('search') ?></span><div><strong>Pantau lebih mudah</strong><small>Cek progres servis kapan saja</small></div></div>
        <div class="trust-item reveal"><span><?= icon('clipboard-list') ?></span><div><strong>Biaya transparan</strong><small>Estimasi tercatat dengan jelas</small></div></div>
        <div class="trust-item reveal"><span><?= icon('shield-check') ?></span><div><strong>Riwayat tersimpan</strong><small>Info kendaraan lebih tertata</small></div></div>
    </div>
</section>

<!-- LAYANAN -->
<section class="section home-services" id="layanan">
    <div class="container">
        <div class="section-head center reveal">
            <span class="eyebrow">DAFTAR LAYANAN</span>
            <h2>Siap bikin motor kembali <em>prima?</em></h2>
            <p>Pilih perawatan yang sesuai kebutuhan. Informasi harga mengikuti katalog layanan bengkel.</p>
        </div>

        <?php if ($dbError): ?>
            <div class="alert alert-error reveal"><?= e($dbError) ?></div>
        <?php elseif (!$serviceTypes): ?>
            <div class="empty-state reveal">Belum ada layanan yang terdaftar. Silakan tambahkan melalui menu admin.</div>
        <?php else: ?>
            <div class="card-grid">
                <?php foreach ($serviceTypes as $service): ?>
                    <article class="service-card reveal">
                        <div class="service-icon"><?= icon($serviceIconFor((string) $service['name'])) ?></div>
                        <h3><?= e($service['name']) ?></h3>
                        <p><?= e($service['description'] ?: 'Layanan perawatan kendaraan profesional.') ?></p>
                        <div class="service-price">
                            <strong>Mulai <?= rupiah($service['base_price']) ?></strong>
                            <span>Harga dasar</span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- KEUNGGULAN -->
<section class="section home-why" id="keunggulan">
    <div class="container">
        <div class="section-head center reveal">
            <span class="eyebrow eyebrow-light">KENAPA SERVISYUK</span>
            <h2>Servis terasa lebih <em>nyaman.</em></h2>
            <p>Informasi yang jelas dan praktis, dari motor masuk bengkel sampai siap kembali ke jalan.</p>
        </div>
        <div class="feature-grid">
            <div class="feature reveal"><div class="feature-topline"><span class="feature-icon"><?= icon('eye') ?></span><span class="feature-number">01</span></div><h3>Progres transparan</h3><p>Status pengerjaan dan estimasi biaya dapat dipantau melalui halaman cek status.</p></div>
            <div class="feature reveal"><div class="feature-topline"><span class="feature-icon"><?= icon('clock') ?></span><span class="feature-number">02</span></div><h3>Praktis dan efisien</h3><p>Cek informasi servis cukup menggunakan nomor polisi, tanpa perlu membuat akun.</p></div>
            <div class="feature reveal"><div class="feature-topline"><span class="feature-icon"><?= icon('wrench') ?></span><span class="feature-number">03</span></div><h3>Perawatan terencana</h3><p>Catatan pekerjaan membantu Anda memahami perawatan yang sudah dilakukan.</p></div>
            <div class="feature reveal"><div class="feature-topline"><span class="feature-icon"><?= icon('coins') ?></span><span class="feature-number">04</span></div><h3>Rincian lebih jelas</h3><p>Jenis pekerjaan dan perkiraan biaya tercatat agar lebih mudah diperiksa.</p></div>
            <div class="feature reveal"><div class="feature-topline"><span class="feature-icon"><?= icon('history') ?></span><span class="feature-number">05</span></div><h3>Riwayat tertata</h3><p>Informasi servis terdahulu tersimpan sehingga mudah ditinjau kembali.</p></div>
            <div class="feature reveal"><div class="feature-topline"><span class="feature-icon"><?= icon('lock') ?></span><span class="feature-number">06</span></div><h3>Akses lebih terjaga</h3><p>Data riwayat dapat dilindungi PIN apabila fitur tersebut diaktifkan bengkel.</p></div>
        </div>
    </div>
</section>

<!-- ALUR PENGGUNAAN -->
<section class="section home-process" id="alur">
    <div class="container">
        <div class="section-head center reveal">
            <span class="eyebrow">LANGKAH PENGGUNAAN</span>
            <h2>Semudah ini alur <em>servisnya.</em></h2>
            <p>Empat langkah sederhana dari booking sampai kendaraan siap diambil.</p>
        </div>
        <div class="steps">
            <div class="step reveal"><div class="step-num">01</div><span class="step-kicker">MULAI</span><h3>Datang ke bengkel</h3><p>Bawa kendaraan dan sampaikan keluhan atau layanan yang Anda butuhkan.</p></div>
            <div class="step reveal"><div class="step-num">02</div><span class="step-kicker">PEMERIKSAAN</span><h3>Cek kondisi motor</h3><p>Keluhan dan pekerjaan servis dicatat agar penanganan lebih terarah.</p></div>
            <div class="step reveal"><div class="step-num">03</div><span class="step-kicker">PENGERJAAN</span><h3>Pantau progresnya</h3><p>Periksa perkembangan servis dan estimasi melalui nomor polisi kendaraan.</p></div>
            <div class="step reveal"><div class="step-num">04</div><span class="step-kicker">SELESAI</span><h3>Motor siap jalan</h3><p>Selesaikan pembayaran dan tinjau kembali riwayat pekerjaan servis.</p></div>
        </div>

        <div class="process-action reveal">
            <a class="button button-navy" href="cek-status.php">Cek Status Servis Anda <?= icon('arrow-right') ?></a>
        </div>
    </div>
</section>

<!-- KONTAK -->
<section class="section home-contact" id="kontak">
    <div class="container contact-grid">
        <div class="contact-promo reveal">
            <span class="eyebrow eyebrow-light">ADA YANG BISA KAMI BANTU?</span>
            <h2>Perjalanan nyaman dimulai dari motor yang terawat.</h2>
            <p>Hubungi ServisYuk untuk informasi seputar layanan dan servis kendaraan Anda.</p>
            <div class="hero-actions">
                <a class="button button-primary" href="tel:+6281234567890"><?= icon('phone') ?> Telepon Bengkel</a>
                <a class="button button-ghost button-ghost-light" href="mailto:info@servisyuk.id"><?= icon('mail') ?> Kirim Email</a>
            </div>
        </div>
        <div class="contact-info reveal">
            <div class="contact-item"><div class="contact-icon"><?= icon('map-pin') ?></div><div><h4>Alamat Bengkel</h4><p>Jalan Hasyim Asyari No. 22, Madiun</p></div></div>
            <div class="contact-item"><div class="contact-icon"><?= icon('phone') ?></div><div><h4>Telepon / WhatsApp</h4><p><a href="tel:+6281234567890">+62 812-3456-7890</a></p></div></div>
            <div class="contact-item"><div class="contact-icon"><?= icon('mail') ?></div><div><h4>Email</h4><p><a href="mailto:info@servisyuk.id">info@servisyuk.id</a></p></div></div>
            <div class="contact-item"><div class="contact-icon"><?= icon('clock') ?></div><div><h4>Jam Operasional</h4><p>Senin - Sabtu, 08.00 - 17.00 WIB</p></div></div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/backend/includes/footer.php'; ?>
