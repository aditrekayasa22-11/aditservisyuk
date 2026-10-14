<?php
declare(strict_types=1);

/**
 * Login portal pelanggan — TANPA username/password.
 *
 * Alur:
 *   1. Pelanggan memasukkan nomor polisi (spasi & huruf besar/kecil diabaikan,
 *      sama persis dengan pencarian di cek-status.php).
 *   2. Bila bengkel memasang PIN untuk kendaraan itu, verifikasi PIN dilakukan
 *      di server sebelum sesi dibuka (percobaan dibatasi per sesi).
 *   3. Struktur OTP sudah disiapkan: saat CUSTOMER_OTP_ENABLED aktif,
 *      customer_verification_methods() ikut mengembalikan 'otp' dan halaman
 *      ini memanggil customer_otp_send()/customer_otp_verify().
 *
 * Sesi portal hanya menyimpan customer_id — semua data diambil dari sesi,
 * sehingga data pribadi pelanggan lain tidak pernah bisa terbaca.
 */
require_once __DIR__ . '/backend/includes/functions.php';

if (customer_session() !== null) {
    redirect('akun-pelanggan.php');
}

$pageTitle = 'Login Pelanggan';

$stage = 'plate';           // 'plate' | 'pin' | 'otp'
$vehicle = null;
$plate = '';
$error = null;
$pinError = null;
$otpMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'login');
    $plate = strtoupper(trim((string) ($_POST['plate_number'] ?? '')));

    if ($plate === '') {
        $error = 'Masukkan nomor polisi kendaraan Anda.';
    } else {
        try {
            $pdo = db();
            $vehicle = find_vehicle_by_plate($pdo, $plate);

            if (!$vehicle) {
                $error = 'Nomor polisi ' . $plate . ' belum terdaftar di bengkel kami. Periksa kembali penulisan nomor polisi, atau hubungi bengkel untuk mendaftarkan kendaraan Anda.';
            } elseif ($action === 'verify_pin') {
                // Pelanggan sudah menemukan kendaraan, sekarang memverifikasi PIN.
                if (!vehicle_has_pin($pdo, (int) $vehicle['id'])) {
                    // Kendaraan tidak lagi dipasangi PIN -> langsung masuk.
                    customer_login((int) $vehicle['customer_id'], (string) $vehicle['customer_name']);
                    flash('Berhasil masuk. Selamat datang, ' . $vehicle['customer_name'] . '.');
                    redirect('akun-pelanggan.php');
                } elseif (!pin_attempt_allowed($plate)) {
                    $pinError = 'Terlalu banyak percobaan PIN yang gagal untuk plat ' . $plate
                        . '. Muat ulang halaman lalu mulai login ulang untuk mencoba kembali.';
                    $stage = 'pin';
                } else {
                    $pin = (string) ($_POST['pin'] ?? '');
                    if (verify_vehicle_pin($pdo, (int) $vehicle['id'], $pin)) {
                        pin_attempt_reset($plate);
                        customer_login((int) $vehicle['customer_id'], (string) $vehicle['customer_name']);
                        flash('Berhasil masuk. Selamat datang, ' . $vehicle['customer_name'] . '.');
                        redirect('akun-pelanggan.php');
                    }
                    $pinError = 'PIN tidak sesuai. Sisa percobaan: ' . pin_attempt_failed($plate) . '.';
                    $stage = 'pin';
                }
            } elseif ($action === 'verify_otp') {
                // Titik sambung OTP (aktif bila CUSTOMER_OTP_ENABLED di config.php).
                if (!customer_otp_enabled()) {
                    customer_login((int) $vehicle['customer_id'], (string) $vehicle['customer_name']);
                    redirect('akun-pelanggan.php');
                }
                $code = (string) ($_POST['otp_code'] ?? '');
                if (customer_otp_verify($pdo, (int) $vehicle['id'], $code)) {
                    customer_login((int) $vehicle['customer_id'], (string) $vehicle['customer_name']);
                    flash('Berhasil masuk. Selamat datang, ' . $vehicle['customer_name'] . '.');
                    redirect('akun-pelanggan.php');
                }
                $otpMessage = 'Kode OTP tidak sesuai atau sudah kedaluwarsa. Periksa kembali kode yang Anda terima.';
                $stage = 'otp';
            } else {
                // Langkah 1: nomor polisi ditemukan, tentukan verifikasi yang diperlukan.
                pin_attempt_reset($plate);
                $methods = customer_verification_methods($pdo, (int) $vehicle['id']);

                if ($methods === []) {
                    // Tanpa PIN dan tanpa OTP: nomor polisi menjadi kuncinya
                    // (sesuai janji layanan: cukup nomor polisi, tanpa akun).
                    customer_login((int) $vehicle['customer_id'], (string) $vehicle['customer_name']);
                    flash('Berhasil masuk. Selamat datang, ' . $vehicle['customer_name'] . '.');
                    redirect('akun-pelanggan.php');
                }

                $stage = in_array('pin', $methods, true) ? 'pin' : 'otp';
                if ($stage === 'otp') {
                    $sent = customer_otp_send($pdo, $vehicle);
                    $otpMessage = $sent['message'];
                }
            }
        } catch (PDOException $exception) {
            error_log('[ServisYuk login-pelanggan] ' . $exception->getMessage());
            $error = 'Database belum dapat diakses. Pastikan MySQL aktif dan database sudah diimpor dari database.sql.';
            $stage = 'plate';
        }
    }
}

$flashMessage = take_flash();

require __DIR__ . '/backend/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <span class="eyebrow eyebrow-light"><span class="eyebrow-dot"></span> PORTAL PELANGGAN</span>
        <h1>Login pelanggan ServisYuk</h1>
        <p>Tidak perlu membuat username atau password. Cukup nomor polisi kendaraan Anda, lalu verifikasi PIN servis bengkel (atau OTP bila diaktifkan).</p>
    </div>
</section>

<section class="container lookup-wrap" style="padding-bottom:80px">
    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= e($flashMessage['type']) ?> alert-auto" style="max-width:480px;margin-inline:auto"><?= e($flashMessage['message']) ?></div>
    <?php endif; ?>

    <div class="lookup-grid">
        <!-- Form login -->
        <div class="lookup-panel auth-panel">
            <div class="panel-heading">
                <div class="panel-icon"><?= icon('log-in') ?></div>
                <div>
                    <h3>Masuk ke portal</h3>
                    <p>Verifikasi dilakukan di server, bukan di browser</p>
                </div>
            </div>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= $stage === 'pin' ? 'verify_pin' : ($stage === 'otp' ? 'verify_otp' : 'login') ?>">

                <label for="plate_number">Nomor Polisi</label>
                <div class="plate-input">
                    <input class="text-input" id="plate_number" name="plate_number" value="<?= e($plate) ?>" placeholder="B 1234 SYK" maxlength="20" autocomplete="off" required>
                </div>
                <?php if ($error): ?><p class="form-error"><?= e($error) ?></p><?php endif; ?>

                <?php if ($stage === 'pin' && $vehicle): ?>
                    <div class="alert alert-warning" style="margin-top:16px">
                        Kendaraan <strong><?= e($vehicle['plate_number']) ?></strong> dilindungi PIN. Masukkan PIN yang Anda terima saat menyerahkan kendaraan.
                    </div>
                    <label for="pin">PIN Servis</label>
                    <input class="text-input" id="pin" name="pin" type="password" inputmode="numeric" pattern="\d{4,6}" maxlength="6" placeholder="4-6 digit angka" autocomplete="off" required>
                    <?php if ($pinError): ?><p class="form-error"><?= e($pinError) ?></p><?php endif; ?>
                <?php endif; ?>

                <?php if ($stage === 'otp' && $vehicle): ?>
                    <div class="alert alert-warning" style="margin-top:16px">
                        Kendaraan <strong><?= e($vehicle['plate_number']) ?></strong> diverifikasi lewat OTP.
                        <?php if ($otpMessage): ?><br><?= e($otpMessage) ?><?php endif; ?>
                    </div>
                    <label for="otp_code">Kode OTP</label>
                    <input class="text-input" id="otp_code" name="otp_code" inputmode="numeric" maxlength="6" placeholder="Kode verifikasi" autocomplete="off" required>
                    <?php if ($otpMessage && !customer_otp_enabled()): ?><p class="form-error"><?= e($otpMessage) ?></p><?php endif; ?>
                <?php endif; ?>

                <button class="button button-primary button-wide" type="submit" style="margin-top:18px">
                    <?php if ($stage === 'pin'): ?>
                        <?= icon('lock') ?> Verifikasi PIN &amp; masuk
                    <?php elseif ($stage === 'otp'): ?>
                        <?= icon('shield-check') ?> Verifikasi &amp; masuk
                    <?php else: ?>
                        <?= icon('log-in') ?> Masuk ke portal <?= icon('arrow-right') ?>
                    <?php endif; ?>
                </button>

                <?php if ($stage !== 'plate'): ?>
                    <a class="back-link" href="login-pelanggan.php"><?= icon('arrow-left') ?> Ulangi dengan nomor polisi lain</a>
                <?php endif; ?>
            </form>

            <div class="privacy-note"><?= icon('shield-check') ?> Kami tidak menyimpan username atau password Anda. Sesi berakhir otomatis setelah <?= CUSTOMER_SESSION_MINUTES ?> menit tidak aktif.</div>
        </div>

        <!-- Informasi fitur portal -->
        <div>
            <div class="result-card">
                <div class="panel-heading">
                    <div class="panel-icon"><?= icon('car') ?></div>
                    <div>
                        <h3>Apa saja yang bisa Anda lihat</h3>
                        <p>Semua data kendaraan yang terdaftar atas nama Anda</p>
                    </div>
                </div>
                <ul class="benefit-list">
                    <li><?= icon('circle-check') ?><span><strong>Data kendaraan</strong> — merek, model, tahun, dan nomor polisi.</span></li>
                    <li><?= icon('circle-check') ?><span><strong>Keluhan &amp; catatan pekerjaan</strong> yang sedang dikerjakan bengkel.</span></li>
                    <li><?= icon('circle-check') ?><span><strong>Tanggal masuk</strong> dan <strong>status pengerjaan</strong> terkini.</span></li>
                    <li><?= icon('circle-check') ?><span><strong>Estimasi biaya</strong> beserta status pembayarannya.</span></li>
                    <li><?= icon('circle-check') ?><span><strong>Riwayat servis</strong> lengkap setiap kendaraan Anda.</span></li>
                </ul>
            </div>

            <div class="result-card">
                <div class="panel-heading">
                    <div class="panel-icon"><?= icon('shield-check') ?></div>
                    <div>
                        <h3>Privasi terjaga</h3>
                        <p>Data hanya milik akun Anda</p>
                    </div>
                </div>
                <p class="text-muted" style="margin:0">Portal hanya menampilkan kendaraan yang terdaftar atas nama Anda. Nomor polisi pelanggan lain dan riwayat mereka tidak pernah ikut ditampilkan.</p>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/backend/includes/footer.php'; ?>
