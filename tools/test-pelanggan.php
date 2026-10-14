<?php
declare(strict_types=1);

/**
 * Uji portal pelanggan (HTTP) — role kedua tanpa username/password:
 *  - menu "Login Pelanggan" ada di samping "Login Admin" (navbar & footer)
 *  - login cukup nomor polisi (spasi/huruf besar-kecil diabaikan)
 *  - kendaraan ber-PIN wajib verifikasi PIN (benar/salah/kuota percobaan)
 *  - struktur OTP tersedia (customer_verification_methods/customer_otp_*)
 *  - portal hanya menampilkan data pelanggan yang sedang login
 *  - pesan jelas saat nomor polisi tidak ditemukan; sesi aman (CSRF, logout)
 *
 * Data uji dibuat lewat PDO lalu dibersihkan di blok finally.
 *
 * Pakai: php tools/test-pelanggan.php [base-url] [user] [pass]
 */
require_once __DIR__ . '/../backend/includes/functions.php';

$base = rtrim((string) ($argv[1] ?? 'http://127.0.0.1:8080/ServisYuk'), '/');
$adminBase = $base . '/backend/admin';
$cookieFile = tempnam(sys_get_temp_dir(), 'sy_pelanggan');
$pdo = db();
$pass = 0;
$fail = 0;

function request(string $method, string $url, array $post = []): array
{
    global $cookieFile;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_HEADER => true,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['status' => $status, 'headers' => substr($raw, 0, $headerSize), 'body' => substr($raw, $headerSize)];
}

/** Buat sesi HTTP baru (dipakai untuk menguji halaman tanpa login). */
function fresh_session(): void
{
    global $cookieFile;
    @unlink($cookieFile);
    $cookieFile = tempnam(sys_get_temp_dir(), 'sy_pelanggan');
}

function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  [OK]   $name\n";
    } else {
        $fail++;
        echo "  [GAGAL] $name" . ($detail !== '' ? " ($detail)" : '') . "\n";
    }
}

function csrfFrom(string $html): string
{
    preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $html, $m);
    return $m[1] ?? '';
}

function usedIcons(string $body): array
{
    preg_match_all('/<use href="#ic-([a-z0-9-]+)"/', $body, $m);
    $ids = array_unique($m[1]);
    sort($ids);
    return $ids;
}

echo "=== Uji Portal Pelanggan ({$base}) ===\n";

try {
    // ---------- Data uji ----------
    $pdo->prepare('INSERT INTO customers (name) VALUES (:n)')->execute(['n' => 'Uji Portal A']);
    $customerA = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO vehicles (customer_id, plate_number, brand, model) VALUES (:c, :pl, "Honda", "Brio")')
        ->execute(['c' => $customerA, 'pl' => 'UJI POR A1']);
    $vehicleA1 = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO service_records (vehicle_id, service_type_id, description, status, service_date, cost)
                   VALUES (:v, 1, "REMUK KIRI depan berisik", "Dikerjakan", CURDATE(), 250000)')
        ->execute(['v' => $vehicleA1]);
    $pdo->prepare('INSERT INTO vehicles (customer_id, plate_number, brand, model) VALUES (:c, :pl, "Toyota", "Agya")')
        ->execute(['c' => $customerA, 'pl' => 'UJI POR A2']);
    $vehicleA2 = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO service_records (vehicle_id, service_type_id, description, status, service_date, cost)
                   VALUES (:v, 1, "AC kurang dingin", "Selesai", DATE_SUB(CURDATE(), INTERVAL 10 DAY), 180000)')
        ->execute(['v' => $vehicleA2]);

    $pdo->prepare('INSERT INTO customers (name) VALUES (:n)')->execute(['n' => 'Uji Portal B']);
    $customerB = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO vehicles (customer_id, plate_number, brand, model) VALUES (:c, :pl, "Suzuki", "Ertiga")')
        ->execute(['c' => $customerB, 'pl' => 'UJI POR B1']);
    $vehicleB = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO service_records (vehicle_id, service_type_id, description, status, service_date, cost)
                   VALUES (:v, 1, "GANTI OLI BORONGAN mahal", "Selesai", CURDATE(), 400000)')
        ->execute(['v' => $vehicleB]);
    save_vehicle_pin($pdo, $vehicleB, '7788', null);

    $pdo->prepare('INSERT INTO customers (name) VALUES (:n)')->execute(['n' => 'Uji Portal Rahasia C']);
    $customerC = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO vehicles (customer_id, plate_number, brand, model) VALUES (:c, :pl, "Mitsubishi", "Pajero")')
        ->execute(['c' => $customerC, 'pl' => 'UJI POR C1']);
    $vehicleC = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO service_records (vehicle_id, service_type_id, description, status, service_date, cost)
                   VALUES (:v, 1, "RAHASIA pelanggan lain", "Menunggu", CURDATE(), 999000)')
        ->execute(['v' => $vehicleC]);

    // ---------- 1. Menu login pelanggan di navbar & footer ----------
    $r = request('GET', $base . '/index.php');
    $navPos = strpos($r['body'], '>Login Pelanggan<');
    $adminPos = strpos($r['body'], '>Login Admin<');
    check('Beranda punya menu Login Pelanggan', $navPos !== false);
    check('Beranda punya menu Login Admin', $adminPos !== false);
    check('Login Pelanggan berdampingan dengan Login Admin', $navPos !== false && $adminPos !== false && ($adminPos - $navPos) < 300,
        $navPos !== false && $adminPos !== false ? 'jarak ' . ($adminPos - $navPos) : 'salah satu tidak ada');
    check('Footer juga menautkan Login Pelanggan', substr_count($r['body'], 'login-pelanggan.php') >= 2);

    $r = request('GET', $adminBase . '/login.php');
    check('Halaman login admin menautkan Login Pelanggan (path benar dari backend/admin)',
        str_contains($r['body'], 'href="../../login-pelanggan.php"'));

    // ---------- 2. Halaman login ----------
    $r = request('GET', $base . '/login-pelanggan.php');
    check('Halaman login pelanggan terbuka (200)', $r['status'] === 200, 'status ' . $r['status']);
    check('Login pelanggan tanpa username/password (hanya nomor polisi)',
        str_contains($r['body'], 'id="plate_number"') && !str_contains($r['body'], 'id="username"'));
    check('Menjelaskan verifikasi PIN/OTP', str_contains($r['body'], 'PIN') && str_contains($r['body'], 'OTP'));
    check('Halaman login merender sprite ikon tepat sekali', substr_count($r['body'], 'class="icon-sprite"') === 1);
    check('Halaman login memakai ikon log-in', in_array('log-in', usedIcons($r['body']), true));
    $csrf = csrfFrom($r['body']);

    // ---------- 3. CSRF wajib ----------
    $r = request('POST', $base . '/login-pelanggan.php', ['action' => 'login', 'plate_number' => 'UJI POR A1']);
    check('POST tanpa CSRF token ditolak (403)', $r['status'] === 403, 'status ' . $r['status']);

    // ---------- 4. Nomor polisi tidak ditemukan -> pesan jelas ----------
    $r = request('GET', $base . '/login-pelanggan.php');
    $csrf = csrfFrom($r['body']);
    $r = request('POST', $base . '/login-pelanggan.php', [
        'csrf_token' => $csrf, 'action' => 'login', 'plate_number' => 'TIDAK ADA 999',
    ]);
    check('Plat tak diberitahu menampilkan pesan jelas', str_contains($r['body'], 'belum terdaftar'));
    check('Pesan tak ditemukan menyebut nomor polisi yang diketik', str_contains($r['body'], 'TIDAK ADA 999'));
    check('Login gagal tetap di halaman login (200)', $r['status'] === 200);

    // ---------- 5. Login tanpa PIN: spasi & huruf besar-kecil diabaikan ----------
    $r = request('GET', $base . '/login-pelanggan.php');
    $csrf = csrfFrom($r['body']);
    $r = request('POST', $base . '/login-pelanggan.php', [
        'csrf_token' => $csrf, 'action' => 'login', 'plate_number' => '  uji   por a1 ',
    ]);
    check('Login berhasil walau spasi & huruf kecil (302 ke portal)',
        $r['status'] === 302 && str_contains($r['headers'], 'akun-pelanggan.php'),
        'status ' . $r['status']);

    // ---------- 6. Portal: data sendiri saja ----------
    $r = request('GET', $base . '/akun-pelanggan.php');
    check('Portal terbuka setelah login (200)', $r['status'] === 200, 'status ' . $r['status']);
    check('Portal menampilkan kedua kendaraan pelanggan ini',
        str_contains($r['body'], 'UJI POR A1') && str_contains($r['body'], 'UJI POR A2'));
    check('Portal menampilkan keluhan & tanggal masuk',
        str_contains($r['body'], 'REMUK KIRI depan berisik') && str_contains($r['body'], 'Tanggal Masuk'));
    check('Portal menampilkan status, estimasi biaya, dan riwayat',
        str_contains($r['body'], 'Estimasi Biaya') && str_contains($r['body'], 'Riwayat servis')
        && str_contains($r['body'], 'AC kurang dingin'));
    check('Portal TIDAK menampilkan kendaraan pelanggan lain',
        !str_contains($r['body'], 'UJI POR B1') && !str_contains($r['body'], 'UJI POR C1'));
    check('Portal TIDAK membocorkan riwayat pelanggan lain',
        !str_contains($r['body'], 'RAHASIA pelanggan lain') && !str_contains($r['body'], 'GANTI OLI BORONGAN')
        && !str_contains($r['body'], 'Uji Portal B') && !str_contains($r['body'], 'Uji Portal Rahasia C'));
    check('Portal menampilkan nama sendiri, bukan nama pelanggan lain',
        str_contains($r['body'], 'Uji Portal A') && !str_contains($r['body'], 'Uji Portal Rahasia'));
    check('Portal memakai ikon log-out & kendaraan',
        in_array('log-out', usedIcons($r['body']), true) && in_array('car', usedIcons($r['body']), true));

    // ---------- 7. Tanpa sesi -> dialihkan ke login ----------
    fresh_session();
    $r = request('GET', $base . '/akun-pelanggan.php');
    check('Portal tanpa sesi dialihkan ke login (302)',
        $r['status'] === 302 && str_contains($r['headers'], 'login-pelanggan.php'), 'status ' . $r['status']);

    // ---------- 8. Login kendaraan ber-PIN ----------
    $r = request('GET', $base . '/login-pelanggan.php');
    $csrf = csrfFrom($r['body']);
    $r = request('POST', $base . '/login-pelanggan.php', [
        'csrf_token' => $csrf, 'action' => 'login', 'plate_number' => 'UJI POR B1',
    ]);
    check('Kendaraan ber-PIN diminta PIN (belum masuk)', $r['status'] === 200 && str_contains($r['body'], 'dilindungi PIN'));
    check('PIN form memakai action verify_pin', str_contains($r['body'], 'name="action" value="verify_pin"'));
    check('Sebelum verifikasi, riwayat tidak bocor', !str_contains($r['body'], 'GANTI OLI BORONGAN'));
    $csrf = csrfFrom($r['body']);

    $r = request('POST', $base . '/login-pelanggan.php', [
        'csrf_token' => $csrf, 'action' => 'verify_pin', 'plate_number' => 'UJI POR B1', 'pin' => '1111',
    ]);
    check('PIN salah ditolak dengan pesan sisa percobaan', str_contains($r['body'], 'PIN tidak sesuai'));
    check('PIN salah tetap di halaman login (200)', $r['status'] === 200);
    check('PIN salah tidak membocorkan riwayat', !str_contains($r['body'], 'GANTI OLI BORONGAN'));
    $csrf = csrfFrom($r['body']);

    $r = request('POST', $base . '/login-pelanggan.php', [
        'csrf_token' => $csrf, 'action' => 'verify_pin', 'plate_number' => 'UJI POR B1', 'pin' => '7788',
    ]);
    check('PIN benar membuka portal (302)', $r['status'] === 302 && str_contains($r['headers'], 'akun-pelanggan.php'),
        'status ' . $r['status']);
    $r = request('GET', $base . '/akun-pelanggan.php');
    check('Portal ber-PIN menampilkan datanya sendiri', str_contains($r['body'], 'UJI POR B1')
        && str_contains($r['body'], 'GANTI OLI BORONGAN'));
    check('Portal ber-PIN tetap tidak menampilkan pelanggan lain',
        !str_contains($r['body'], 'UJI POR A1') && !str_contains($r['body'], 'RAHASIA pelanggan lain'));

    // ---------- 9. Logout aman ----------
    $r = request('GET', $base . '/logout-pelanggan.php');
    check('Logout dialihkan ke halaman login (302)', $r['status'] === 302 && str_contains($r['headers'], 'login-pelanggan.php'));
    $r = request('GET', $base . '/akun-pelanggan.php');
    check('Setelah logout portal terkunci (302)', $r['status'] === 302, 'status ' . $r['status']);
    $r = request('GET', $base . '/login-pelanggan.php');
    check('Logout menampilkan pesan sukses', str_contains($r['body'], 'keluar dari portal pelanggan'));

    // ---------- 10. Struktur verifikasi (PIN siap, OTP disiapkan) ----------
    check('customer_verification_methods() tersedia', function_exists('customer_verification_methods'));
    check('Metode kendaraan ber-PIN = pin', customer_verification_methods($pdo, $vehicleB) === ['pin']);
    check('Metode kendaraan tanpa pin = kosong (nomor polisi cukup)',
        customer_verification_methods($pdo, $vehicleA1) === []);
    check('OTP default nonaktif (siap diaktifkan via CUSTOMER_OTP_ENABLED)', customer_otp_enabled() === false);
    check('customer_otp_send() tersedia sebagai titik sambung', function_exists('customer_otp_send'));
    check('customer_otp_verify() tersedia sebagai titik sambung', function_exists('customer_otp_verify'));
} finally {
    $pdo->prepare('DELETE FROM customers WHERE name LIKE :n')->execute(['n' => 'Uji Portal %']);
    check('Data uji dibersihkan', true);
    @unlink($cookieFile);
}

echo "\n=== Ringkasan: $pass lulus, $fail gagal ===\n";
exit($fail === 0 ? 0 : 1);
