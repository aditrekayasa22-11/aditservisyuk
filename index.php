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
<!--
    Latar hero memakai foto bengkel (assets/img/hero-garage.jpg) lewat variabel
    CSS --hero-photo agar berkas boleh diganti tanpa menyentuh kode.
    Jika berkas tidak ada, hero tetap tampil dengan gradasi navy bawaan.
-->
<section class="hero hero-photo" style="--hero-photo: url('<?= e($base) ?>assets/img/hero-garage.jpg')">
    <div class="container hero-inner">
        <div class="hero-copy">
            <span class="eyebrow eyebrow-light"><span class="eyebrow-dot"></span> SERVIS KENDARAAN TANPA RIBET</span>
            <h1>Servis Kendaraan Lebih Mudah Bersama <em>ServisYuk</em></h1>
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

        <div class="hero-art" aria-hidden="true">
            <div class="art-orbit orbit-one"></div>
            <div class="art-orbit orbit-two"></div>
            <div class="art-glow"></div>
            <div class="art-card" style="animation:floaty 5s ease-in-out infinite">
                <div class="art-card-head">
                    <div class="art-card-icon"><?= icon('settings') ?></div>
                    <div><span>SERVICE TRACKING</span><strong>Status Servis Kendaraan</strong></div>
                </div>
                <div class="art-row"><span>Nomor Polisi</span><span class="art-plate">B 1234 SYK</span></div>
                <div class="art-row"><span>Pekerjaan</span><strong style="color:#fff">Ganti oli mesin</strong></div>
                <div class="art-row"><span>Status</span><span class="pill pill-live"><span class="live-dot"></span> Dikerjakan</span></div>
                <div class="art-row"><span>Estimasi Biaya</span><strong style="color:var(--orange-400)">Rp 150.000</strong></div>
            </div>
        </div>
    </div>
</section>

<!-- LAYANAN -->
<section class="section" id="layanan">
    <div class="container">
        <div class="section-head center reveal">
            <span class="eyebrow">DAFTAR LAYANAN</span>
            <h2>Layanan bengkel untuk kebutuhan kendaraan Anda</h2>
            <p>Harga di bawah diambil langsung dari katalog layanan bengkel, sehingga estimasi biaya tetap transparan.</p>
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
<section class="section section-alt" id="keunggulan">
    <div class="container">
        <div class="section-head center reveal">
            <span class="eyebrow">KENAPA SERVISYUK</span>
            <h2>Keunggulan bengkel kami</h2>
            <p>Transparan, cepat, dan nyaman pada setiap servis kendaraan Anda.</p>
        </div>
        <div class="feature-grid">
            <div class="feature reveal"><span class="feature-number">01</span><h3>Transparan</h3><p>Status servis dan rincian biaya bisa Anda pantau kapan saja melalui halaman cek status.</p></div>
            <div class="feature reveal"><span class="feature-number">02</span><h3>Proses Cepat</h3><p>Antrean servis yang terorganisir membuat pekerjaan kendaraan Anda selesai tepat waktu.</p></div>
            <div class="feature reveal"><span class="feature-number">03</span><h3>Mekanik Berpengalaman</h3><p>Dikerjakan oleh tenaga ahli yang sudah menangani berbagai merek kendaraan.</p></div>
            <div class="feature reveal"><span class="feature-number">04</span><h3>Suku Cadang Asli</h3><p>Kami menggunakan suku cadang berkualitas agar kendaraan Anda awet dan aman.</p></div>
            <div class="feature reveal"><span class="feature-number">05</span><h3>Riwayat Tersimpan</h3><p>Setiap catatan servis tersimpan rapi sehingga perawatan berikutnya mudah direncanakan.</p></div>
            <div class="feature reveal"><span class="feature-number">06</span><h3>Data Terlindungi</h3><p>Riwayat kendaraan hanya bisa dibuka menggunakan nomor polisi, dan PIN bila bengkel mengaktifkannya.</p></div>
        </div>
    </div>
</section>

<!-- ALUR PENGGUNAAN -->
<section class="section" id="alur">
    <div class="container">
        <div class="section-head center reveal">
            <span class="eyebrow">LANGKAH PENGGUNAAN</span>
            <h2>Bagaimana alur servis di ServisYuk</h2>
            <p>Empat langkah sederhana dari booking sampai kendaraan siap diambil.</p>
        </div>
        <div class="steps">
            <div class="step reveal"><div class="step-num">1</div><h3>Datang / Booking</h3><p>Bawa kendaraan Anda ke bengkel atau hubungi kami untuk melakukan pemesanan waktu servis.</p></div>
            <div class="step reveal"><div class="step-num">2</div><h3>Pemeriksaan</h3><p>Mekanik memeriksa kondisi kendaraan dan mencatat keluhan serta pekerjaan yang dibutuhkan.</p></div>
            <div class="step reveal"><div class="step-num">3</div><h3>Pantau Status</h3><p>Servis dikerjakan sesuai jenis layanan. Pantau statusnya kapan saja melalui nomor polisi.</p></div>
            <div class="step reveal"><div class="step-num">4</div><h3>Selesai &amp; Bayar</h3><p>Kendaraan siap diambil setelah pekerjaan selesai, biaya dibayar, dan riwayat tersimpan.</p></div>
        </div>

        <div style="text-align:center;margin-top:40px" class="reveal">
            <a class="button button-navy" href="cek-status.php">Cek Status Servis Anda <?= icon('arrow-right') ?></a>
        </div>
    </div>
</section>

<!-- KONTAK -->
<section class="section section-alt">
    <div class="container contact-grid">
        <div class="section-head reveal" style="margin-bottom:0">
            <span class="eyebrow">INFORMASI KONTAK</span>
            <h2>Butuh bantuan? Hubungi kami</h2>
            <p>Tim kami siap membantu menjawab pertanyaan seputar servis kendaraan Anda.</p>
            <div class="hero-actions">
                <a class="button button-primary" href="tel:+6281234567890">Telepon Sekarang</a>
                <a class="button button-ghost" href="mailto:info@servisyuk.id">Kirim Email</a>
            </div>
        </div>
        <div class="contact-info reveal">
            <div class="contact-item"><div class="contact-icon"><?= icon('map-pin') ?></div><div><h4>Alamat Bengkel</h4><p>Jl. Raya Servis No. 12, Kota Anda</p></div></div>
            <div class="contact-item"><div class="contact-icon"><?= icon('phone') ?></div><div><h4>Telepon / WhatsApp</h4><p><a href="tel:+6281234567890">+62 812-3456-7890</a></p></div></div>
            <div class="contact-item"><div class="contact-icon"><?= icon('mail') ?></div><div><h4>Email</h4><p><a href="mailto:info@servisyuk.id">info@servisyuk.id</a></p></div></div>
            <div class="contact-item"><div class="contact-icon"><?= icon('clock') ?></div><div><h4>Jam Operasional</h4><p>Senin - Sabtu, 08.00 - 17.00 WIB</p></div></div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/backend/includes/footer.php'; ?>
