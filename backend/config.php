<?php
declare(strict_types=1);

define('APP_NAME', 'ServisYuk');
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'servisyuk');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

/* --------------------------------------------------------------------------
   Portal pelanggan (tanpa username/password)
   Verifikasi memakai nomor polisi + PIN servis yang sudah aktif.
   OTP disiapkan agar bisa diaktifkan kemudian hari tanpa mengubah alur:
   set CUSTOMER_OTP_ENABLED=1 lalu isi fungsi customer_otp_send()/verify()
   di backend/includes/functions.php (titik sambung sudah dipanggil halaman
   login-pelanggan.php).
   -------------------------------------------------------------------------- */
define('CUSTOMER_OTP_ENABLED', filter_var(getenv('CUSTOMER_OTP_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN));

/** Masa berlaku sesi portal pelanggan (menit) sejak terakhir masuk. */
define('CUSTOMER_SESSION_MINUTES', (int) (getenv('CUSTOMER_SESSION_MINUTES') ?: 60));

/* --------------------------------------------------------------------------
   Informasi rekening tujuan pembayaran transfer & QRIS (US-11).
   -------------------------------------------------------------------------- */
define('BANK_ACCOUNTS', [
    'bni' => [
        'name' => 'Bank BNI',
        'account_number' => getenv('BANK_BNI_NUMBER') ?: '0238472910',
        'account_name' => getenv('BANK_BNI_NAME') ?: 'Bengkel ServisYuk',
    ],
    'bri' => [
        'name' => 'Bank BRI',
        'account_number' => getenv('BANK_BRI_NUMBER') ?: '102938475610293',
        'account_name' => getenv('BANK_BRI_NAME') ?: 'Bengkel ServisYuk',
    ],
    'bca' => [
        'name' => 'Bank BCA',
        'account_number' => getenv('BANK_BCA_NUMBER') ?: '8049281726',
        'account_name' => getenv('BANK_BCA_NAME') ?: 'Bengkel ServisYuk',
    ],
    'mandiri' => [
        'name' => 'Bank Mandiri',
        'account_number' => getenv('BANK_MANDIRI_NUMBER') ?: '1370019283746',
        'account_name' => getenv('BANK_MANDIRI_NAME') ?: 'Bengkel ServisYuk',
    ],
]);

define('QRIS_MERCHANT_NAME', getenv('QRIS_MERCHANT_NAME') ?: 'ServisYuk Auto Service');
define('QRIS_NMID', getenv('QRIS_NMID') ?: 'ID1020039281920');
define('QRIS_IMAGE_PATH', getenv('QRIS_IMAGE_PATH') ?: 'assets/img/qris-servisyuk.svg');

// Kompatibilitas konstanta lama
define('BANK_NAME', getenv('BANK_NAME') ?: 'Bank BNI');
define('BANK_ACCOUNT_NUMBER', getenv('BANK_ACCOUNT_NUMBER') ?: '0238472910');
define('BANK_ACCOUNT_NAME', getenv('BANK_ACCOUNT_NAME') ?: 'Bengkel ServisYuk');

/** Batas ukuran bukti transfer yang boleh diunggah pelanggan (byte). */
define('PAYMENT_PROOF_MAX_BYTES', 2 * 1024 * 1024);

/** Ekstensi berkas bukti transfer yang diizinkan. */
define('PAYMENT_PROOF_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
