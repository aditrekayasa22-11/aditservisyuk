<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/icons.php';

function e(mixed $value): string
{
    // Kolom numerik dari MySQL (tahun, id, biaya) ikut dilewati sini,
    // jadi nilai dikonversi dulu alih-alih memicu TypeError.
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        // 403 (bukan 419): instalasi PHP/XAMPP ini me-render 419 menjadi 500.
        http_response_code(403);
        exit('Sesi formulir tidak valid. Muat ulang halaman, lalu coba lagi.');
    }
}

function require_admin(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function rupiah(float|int|string|null $amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

function status_class(string $status): string
{
    return match ($status) {
        'Selesai', 'Lunas' => 'status-success',
        'Dikerjakan', 'Menunggu', 'Belum Dibayar' => 'status-pending',
        'Dibatalkan', 'Tidak Ditemukan' => 'status-muted',
        default => 'status-info',
    };
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

/* =========================================================================
   Fitur PIN akses pelanggan (opsional, dikelola admin)
   Tabel: service_pins (lihat database.sql / database_upgrade.sql)
   ========================================================================= */

/**
 * Cari kendaraan berdasarkan nomor polisi (abaikan spasi & huruf besar/kecil).
 */
function find_vehicle_by_plate(PDO $pdo, string $plate): ?array
{
    $statement = $pdo->prepare(
        'SELECT v.id, v.customer_id, v.plate_number, v.brand, v.model, v.year, c.name AS customer_name
         FROM vehicles v JOIN customers c ON c.id = v.customer_id
         WHERE REPLACE(UPPER(v.plate_number), " ", "") = REPLACE(UPPER(:plate), " ", "")
         LIMIT 1'
    );
    $statement->execute(['plate' => $plate]);
    $vehicle = $statement->fetch();
    return $vehicle ?: null;
}

/**
 * Semua riwayat servis milik satu kendaraan (terbaru di atas).
 */
function vehicle_services(PDO $pdo, int $vehicleId): array
{
    $statement = $pdo->prepare(
        'SELECT sr.id, sr.description, sr.status, sr.service_date, sr.cost,
                st.name AS service_name, p.status AS payment_status, p.amount AS payment_amount
         FROM service_records sr
         LEFT JOIN service_types st ON st.id = sr.service_type_id
         LEFT JOIN payments p ON p.service_record_id = sr.id
         WHERE sr.vehicle_id = :vehicle_id
         ORDER BY sr.service_date DESC, sr.id DESC'
    );
    $statement->execute(['vehicle_id' => $vehicleId]);
    return $statement->fetchAll();
}

/**
 * Apakah kendaraan sudah memiliki PIN akses yang masih berlaku?
 */
function vehicle_has_pin(PDO $pdo, int $vehicleId): bool
{
    $statement = $pdo->prepare(
        'SELECT 1 FROM service_pins
         WHERE vehicle_id = :vehicle_id
           AND (expires_at IS NULL OR expires_at >= CURDATE())
         LIMIT 1'
    );
    $statement->execute(['vehicle_id' => $vehicleId]);
    return (bool) $statement->fetchColumn();
}

/**
 * Verifikasi PIN kendaraan. Mengembalikan true jika PIN cocok dan belum kedaluwarsa.
 */
function verify_vehicle_pin(PDO $pdo, int $vehicleId, string $pin): bool
{
    if (!preg_match('/^\d{4,6}$/', $pin)) {
        return false;
    }
    $statement = $pdo->prepare(
        'SELECT pin_hash FROM service_pins
         WHERE vehicle_id = :vehicle_id
           AND (expires_at IS NULL OR expires_at >= CURDATE())'
    );
    $statement->execute(['vehicle_id' => $vehicleId]);
    foreach ($statement->fetchAll() as $row) {
        if (password_verify($pin, $row['pin_hash'])) {
            return true;
        }
    }
    return false;
}

/**
 * Simpan (ganti) PIN akses kendaraan. PIN disimpan sebagai hash, tidak pernah disimpan plaintext.
 */
function save_vehicle_pin(PDO $pdo, int $vehicleId, string $pin, ?string $expiresAt = null): void
{
    $pdo->prepare('DELETE FROM service_pins WHERE vehicle_id = :vehicle_id')
        ->execute(['vehicle_id' => $vehicleId]);
    $pdo->prepare(
        'INSERT INTO service_pins (vehicle_id, pin_hash, expires_at) VALUES (:vehicle_id, :pin_hash, :expires_at)'
    )->execute([
        'vehicle_id' => $vehicleId,
        'pin_hash' => password_hash($pin, PASSWORD_DEFAULT),
        'expires_at' => $expiresAt,
    ]);
}

function delete_vehicle_pin(PDO $pdo, int $vehicleId): void
{
    $pdo->prepare('DELETE FROM service_pins WHERE vehicle_id = :vehicle_id')
        ->execute(['vehicle_id' => $vehicleId]);
}

/**
 * Pembatas percobaan PIN agar tidak bisa ditebak sembarangan.
 * Maksimal 5 percobaan gagal per nomor polisi dalam satu sesi.
 */
function pin_attempt_allowed(string $plate): bool
{
    $_SESSION['pin_attempts'][$plate] = ($_SESSION['pin_attempts'][$plate] ?? 0);
    return $_SESSION['pin_attempts'][$plate] < 5;
}

function pin_attempt_failed(string $plate): int
{
    $_SESSION['pin_attempts'][$plate] = ($_SESSION['pin_attempts'][$plate] ?? 0) + 1;
    return 5 - $_SESSION['pin_attempts'][$plate];
}

function pin_attempt_reset(string $plate): void
{
    unset($_SESSION['pin_attempts'][$plate]);
}

/**
 * Sisa percobaan PIN yang tersisa untuk sebuah nomor polisi pada sesi ini.
 */
function pin_attempts_left(string $plate): int
{
    return max(0, 5 - ($_SESSION['pin_attempts'][$plate] ?? 0));
}

/* =========================================================================
   Portal pelanggan (login tanpa username/password)
   Pelanggan masuk memakai nomor polisi kendaraan; bila bengkel memasang PIN
   atau mengaktifkan OTP, verifikasi wajib dilakukan di server.
   ========================================================================= */

/**
 * Mulai sesi portal pelanggan setelah verifikasi berhasil.
 * id sesi diganti agar sesi lama (sebelum login) tidak bisa dilanjutkan.
 */
function customer_login(int $customerId, string $customerName): void
{
    session_regenerate_id(true);
    $_SESSION['pelanggan'] = [
        'customer_id' => $customerId,
        'customer_name' => $customerName,
        'login_at' => time(),
        'last_seen' => time(),
    ];
}

/**
 * Ambil sesi pelanggan yang masih berlaku, atau null bila belum/kedaluwarsa.
 * Sesi kedaluwarsa setelah CUSTOMER_SESSION_MINUTES menit tanpa aktivitas.
 */
function customer_session(): ?array
{
    $session = $_SESSION['pelanggan'] ?? null;
    if (!is_array($session) || !isset($session['customer_id'])) {
        return null;
    }

    $maxIdle = CUSTOMER_SESSION_MINUTES * 60;
    if (time() - (int) ($session['last_seen'] ?? 0) > $maxIdle) {
        unset($_SESSION['pelanggan']);
        return null;
    }
    $session['last_seen'] = time();
    $_SESSION['pelanggan'] = $session;

    return $session;
}

/** Wajib login portal pelanggan; selain itu dialihkan ke halaman login. */
function require_customer(): array
{
    $session = customer_session();
    if ($session === null) {
        redirect('login-pelanggan.php');
    }
    return $session;
}

/** Keluar dari portal pelanggan (bersih, aman diulang). */
function customer_logout(): void
{
    unset($_SESSION['pelanggan'], $_SESSION['pin_attempts'], $_SESSION['customer_otp']);
    session_regenerate_id(true);
}

/**
 * Satu-satunya sumber data portal: kendaraan milik pelanggan yang sedang login.
 * Query selalu terikat customer_id dari sesi sehingga data pemilik lain
 * tidak pernah ikut terbaca, berapa pun jumlah kendaraan yang dicari saat masuk.
 */
function customer_vehicles(PDO $pdo, int $customerId): array
{
    $statement = $pdo->prepare(
        'SELECT id, plate_number, brand, model, year
         FROM vehicles
         WHERE customer_id = :customer_id
         ORDER BY id'
    );
    $statement->execute(['customer_id' => $customerId]);
    return $statement->fetchAll();
}

/**
 * Metode verifikasi yang aktif untuk sebuah kendaraan.
 * 'pin'  — sudah berfungsi (dikelola admin lewat menu Riwayat & PIN)
 * 'otp'  — disiapkan; aktif setelah CUSTOMER_OTP_ENABLED diubah
 *
 * @return string[] mis. ['pin'], ['pin','otp'], atau [] bila tanpa verifikasi
 */
function customer_verification_methods(PDO $pdo, int $vehicleId): array
{
    $methods = [];
    if (vehicle_has_pin($pdo, $vehicleId)) {
        $methods[] = 'pin';
    }
    if (customer_otp_enabled()) {
        $methods[] = 'otp';
    }
    return $methods;
}

function customer_otp_enabled(): bool
{
    return CUSTOMER_OTP_ENABLED === true;
}

/**
 * Titik sambung kirim kode OTP. Isi bagian ini (kirim via SMS/email
 * gateway) saat CUSTOMER_OTP_ENABLED diaktifkan.
 *
 * @return array{ok:bool, message:string}
 */
function customer_otp_send(PDO $pdo, array $vehicle): array
{
    return [
        'ok' => false,
        'message' => 'Verifikasi OTP belum dikonfigurasi bengkel. Gunakan PIN servis atau hubungi bengkel.',
    ];
}

/**
 * Titik sambung verifikasi kode OTP.
 */
function customer_otp_verify(PDO $pdo, int $vehicleId, string $code): bool
{
    return false;
}
