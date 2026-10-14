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
