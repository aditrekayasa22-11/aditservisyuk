<?php
declare(strict_types=1);

/**
 * Cek status servis pelanggan (tanpa akun).
 * - Pelanggan cukup memasukkan nomor polisi (spasi & huruf besar/kecil diabaikan).
 * - Jika bengkel mengaktifkan PIN untuk kendaraan, riwayat hanya terbuka
 *   setelah PIN yang diberikan saat servis diverifikasi di server.
 * Data pribadi pemilik lain tidak pernah ditampilkan di halaman ini.
 */
require_once __DIR__ . '/backend/includes/functions.php';

$pageTitle = 'Cek Status Servis';

$plate = '';
$pinRequired = false;
$verified = false;
$vehicle = null;
$services = [];
$notFound = false;
$error = null;
$pinError = null;
$pinLeft = 5;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'search');
    $plate = strtoupper(trim((string) ($_POST['plate_number'] ?? '')));

    if ($plate === '') {
        $error = 'Masukkan nomor polisi kendaraan Anda.';
    } else {
        try {
            $pdo = db();
            $vehicle = find_vehicle_by_plate($pdo, $plate);

            if (!$vehicle) {
                $notFound = true;
            } elseif ($action === 'verify_pin') {
                // Pelanggan sudah menemukan kendaraan, sekarang memverifikasi PIN.
                if (!pin_attempt_allowed($plate)) {
                    $pinError = 'Terlalu banyak percobaan PIN yang gagal untuk plat ' . e($plate)
                        . '. Muat ulang halaman lalu lakukan pencarian ulang untuk mencoba kembali.';
                    $pinRequired = true;
                } else {
                    $pin = (string) ($_POST['pin'] ?? '');
                    if (verify_vehicle_pin($pdo, (int) $vehicle['id'], $pin)) {
                        pin_attempt_reset($plate);
                        $verified = true;
                        $services = vehicle_services($pdo, (int) $vehicle['id']);
                    } else {
                        $pinLeft = pin_attempt_failed($plate);
                        $pinError = 'PIN tidak sesuai. Sisa percobaan: ' . $pinLeft . '.';
                        $pinRequired = true;
                    }
                }
            } else {
                // Pencarian awal: reset penghitung percobaan, lalu cek proteksi PIN.
                pin_attempt_reset($plate);
                if (vehicle_has_pin($pdo, (int) $vehicle['id'])) {
                    $pinRequired = true;
                } else {
                    $verified = true;
                    $services = vehicle_services($pdo, (int) $vehicle['id']);
                }
            }
        } catch (PDOException $exception) {
            error_log('[ServisYuk cek-status] ' . $exception->getMessage());
            $error = 'Database belum dapat diakses. Pastikan MySQL aktif dan database sudah diimpor dari database.sql.';
        }
    }
}

/** Catatan servis terbaru dipakai sebagai "status terkini". */
$latest = $services[0] ?? null;

/** Tonggak pengerjaan untuk indikator progres. */
$statusSteps = ['Menunggu', 'Dikerjakan', 'Selesai'];
$statusIndex = $latest ? array_search($latest['status'], $statusSteps, true) : false;

require __DIR__ . '/backend/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <span class="eyebrow eyebrow-light"><span class="eyebrow-dot"></span> LACAK KENDARAAN ANDA</span>
        <h1>Cek status servis kendaraan</h1>
        <p>Masukkan nomor polisi kendaraan Anda untuk melihat status pengerjaan, keluhan, estimasi biaya, dan riwayat servis terakhir.</p>
    </div>
</section>

<section class="container lookup-wrap" style="padding-bottom:80px">
    <div class="lookup-grid">
        <!-- Form pencarian -->
        <div class="lookup-panel">
            <div class="panel-heading">
                <div class="panel-icon"><?= icon('search') ?></div>
                <div>
                    <h3>Cari kendaraan</h3>
                    <p>Gunakan nomor polisi yang terdaftar di bengkel kami</p>
                </div>
            </div>

            <form method="post" data-lookup-form>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= $pinRequired ? 'verify_pin' : 'search' ?>">

                <label for="plate_number">Nomor Polisi</label>
                <div class="plate-input">
                    <input class="text-input" id="plate_number" name="plate_number" value="<?= e($plate) ?>" placeholder="B 1234 SYK" maxlength="20" autocomplete="off" required>
                </div>
                <?php if ($error): ?><p class="form-error"><?= e($error) ?></p><?php endif; ?>

                <?php if ($pinRequired && !$pinError): ?>
                    <div class="alert alert-warning" style="margin-top:16px">
                        Kendaraan <strong><?= e($vehicle['plate_number']) ?></strong> dilindungi PIN. Masukkan PIN yang Anda terima saat menyerahkan kendaraan.
                    </div>
                <?php endif; ?>

                <?php if ($pinRequired): ?>
                    <label for="pin">PIN Servis</label>
                    <input class="text-input" id="pin" name="pin" type="password" inputmode="numeric" pattern="\d{4,6}" maxlength="6" placeholder="4-6 digit angka" autocomplete="off" required>
                    <?php if ($pinError): ?><p class="form-error"><?= e($pinError) ?></p><?php endif; ?>
                <?php endif; ?>

                <button class="button button-primary button-wide" type="submit" style="margin-top:18px">
                    <?= $pinRequired ? 'Verifikasi PIN &amp; Lihat Servis' : 'Cek status servis' ?> <?= icon('arrow-right') ?>
                </button>
            </form>

            <div class="privacy-note"><?= icon('shield-check') ?> Data kendaraan Anda kami jaga dengan aman. Informasi hanya ditampilkan kepada pemilik nomor polisi.</div>
        </div>

        <!-- Hasil pencarian -->
        <div>
            <?php if ($notFound): ?>
                <div class="result-card not-found">
                    <div class="result-symbol"><?= icon('circle-help') ?></div>
                    <h3>Kendaraan belum ditemukan</h3>
                    <p>Nomor polisi <strong><?= e($plate) ?></strong> belum terdaftar di sistem kami. Periksa kembali penulisan nomor polisi atau hubungi bengkel untuk pendaftaran.</p>
                </div>
            <?php elseif ($verified && $vehicle): ?>
                <div class="result-card">
                    <div class="vehicle-card">
                        <div class="vehicle-avatar"><?= icon('car') ?></div>
                        <div class="vehicle-meta">
                            <span>Informasi kendaraan</span>
                            <h3><?= e(trim(($vehicle['brand'] ?? '') . ' ' . ($vehicle['model'] ?? '')) ?: 'Kendaraan pelanggan') ?><?= ($vehicle['year'] ?? null) ? ' ' . e((string) $vehicle['year']) : '' ?></h3>
                            <p>Pemilik: <?= e($vehicle['customer_name']) ?></p>
                        </div>
                        <span class="plate-tag"><?= e($vehicle['plate_number']) ?></span>
                    </div>

                    <?php if (!$latest): ?>
                        <div class="empty-state">Belum ada catatan servis untuk kendaraan ini. Silakan hubungi bengkel untuk pendaftaran servis.</div>
                    <?php else: ?>
                        <h3>Status terkini</h3>
                        <div class="progress-track" aria-hidden="true">
                            <?php foreach ($statusSteps as $index => $step): ?>
                                <span class="<?= $statusIndex !== false && $index <= $statusIndex ? 'done' : '' ?>"></span>
                            <?php endforeach; ?>
                        </div>

                        <div class="detail-grid">
                            <div class="detail-item"><span>Status</span><strong><?= e($latest['status']) ?></strong></div>
                            <div class="detail-item"><span>Jenis Servis</span><strong><?= e($latest['service_name'] ?: 'Servis umum') ?></strong></div>
                            <div class="detail-item"><span>Tanggal Masuk</span><strong><?= e(date('d M Y', strtotime($latest['service_date']))) ?></strong></div>
                            <div class="detail-item"><span>Estimasi Biaya</span><strong><?= rupiah($latest['cost']) ?></strong></div>
                            <div class="detail-item"><span>Pembayaran</span><strong><?= e($latest['payment_status'] ?: 'Belum ada data') ?></strong></div>
                        </div>

                        <div class="detail-item" style="margin-bottom:6px">
                            <span>Keluhan / Catatan Pekerjaan</span>
                            <strong style="font-weight:600;color:var(--slate-700)"><?= e($latest['description'] ?: 'Tidak ada keterangan tambahan.') ?></strong>
                        </div>

                        <div class="history-heading">
                            <h3>Riwayat servis</h3>
                            <span><?= count($services) ?> catatan</span>
                        </div>
                        <div class="history-list">
                            <?php foreach ($services as $service): ?>
                                <article class="history-item">
                                    <div class="history-date"><?= e(date('d M Y', strtotime($service['service_date']))) ?></div>
                                    <div class="history-details">
                                        <h4><?= e($service['service_name'] ?: 'Servis kendaraan') ?></h4>
                                        <p><?= e($service['description'] ?: 'Tidak ada keterangan tambahan.') ?></p>
                                    </div>
                                    <span class="status-badge <?= status_class($service['status']) ?>"><?= e($service['status']) ?></span>
                                    <div class="history-cost"><?= rupiah($service['cost']) ?><small><?= e($service['payment_status'] ?: 'Belum Dibayar') ?></small></div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($pinRequired): ?>
                <div class="result-card">
                    <div class="panel-heading">
                        <div class="panel-icon"><?= icon('lock') ?></div>
                        <div>
                            <h3>Verifikasi diperlukan</h3>
                            <p>Masukkan PIN servis untuk membuka riwayat kendaraan ini</p>
                        </div>
                    </div>
                    <p class="text-muted" style="margin:0">Kendaraan <strong><?= e($vehicle['plate_number'] ?? '') ?></strong> dilindungi PIN. Riwayat servis tidak ditampilkan sebelum PIN benar dimasukkan.</p>
                </div>
            <?php else: ?>
                <div class="result-card">
                    <div class="panel-heading">
                        <div class="panel-icon"><?= icon('car') ?></div>
                        <div>
                            <h3>Informasi servis Anda</h3>
                            <p>Hasil pencarian berdasarkan nomor polisi akan tampil di sini</p>
                        </div>
                    </div>
                    <p class="text-muted" style="margin:0">Contoh penulisan nomor polisi: <strong>B 1234 SYK</strong>. Spasi dan huruf besar/kecil tidak memengaruhi hasil pencarian.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/backend/includes/footer.php'; ?>
