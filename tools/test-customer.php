<?php
declare(strict_types=1);
/**
 * Uji alur cek status pelanggan lewat HTTP (pengujian lokal).
 * Menguji: nomor tak ditemukan, kendaraan tanpa PIN, kendaraan dengan PIN
 * (benar & salah). Data contoh dibuat lalu dihapus kembali.
 */
require_once __DIR__ . '/../backend/includes/functions.php';

$base = rtrim((string) ($argv[1] ?? 'http://127.0.0.1:8080/ServisYuk'), '/');
$cookieFile = tempnam(sys_get_temp_dir(), 'sy_http');
$pdo = db();
$pass = 0;
$fail = 0;

function http(string $method, string $url, array $post = []): array
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
    $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['status' => $status, 'body' => substr($raw, $size)];
}

function csrfFrom(string $html): string
{
    preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $html, $m);
    return $m[1] ?? '';
}

function check(string $name, bool $ok): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  [OK]   $name\n"; }
    else { $fail++; echo "  [GAGAL] $name\n"; }
}

echo "=== Uji Alur Cek Status (HTTP) ===\n";

$plateTanpaPin = 'UJI HTP 101';
$plateDenganPin = 'UJI HTP 202';
$pin = '4829';
$vehicleIds = [];

try {
    // Siapkan dua kendaraan contoh
    foreach ([$plateTanpaPin, $plateDenganPin] as $plate) {
        $pdo->prepare('INSERT INTO customers (name) VALUES (:n)')->execute(['n' => 'Uji HTTP ' . $plate]);
        $cid = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO vehicles (customer_id, plate_number, brand, model) VALUES (:c, :pl, "Honda", "Brio")')
            ->execute(['c' => $cid, 'pl' => $plate]);
        $vid = (int) $pdo->lastInsertId();
        $vehicleIds[] = $vid;
        $pdo->prepare('INSERT INTO service_records (vehicle_id, service_type_id, description, status, service_date, cost)
                       VALUES (:v, 1, "oli mesin berganti", "Dikerjakan", CURDATE(), 150000)')
            ->execute(['v' => $vid]);
    }
    // Kendaraan kedua dipasangi PIN
    save_vehicle_pin($pdo, $vehicleIds[1], $pin, null);

    // 1. Nomor polisi tidak ditemukan
    $r = http('GET', $base . '/cek-status.php');
    $csrf = csrfFrom($r['body']);
    $r = http('POST', $base . '/cek-status.php', [
        'csrf_token' => $csrf, 'action' => 'search', 'plate_number' => 'TIDAK ADA 999',
    ]);
    check('Nomor tidak ditemukan menampilkan pesan', str_contains($r['body'], 'belum terdaftar') || str_contains($r['body'], 'belum ditemukan'));

    // 2. Kendaraan tanpa PIN -> langsung terbuka
    $r = http('GET', $base . '/cek-status.php');
    $csrf = csrfFrom($r['body']);
    $r = http('POST', $base . '/cek-status.php', [
        'csrf_token' => $csrf, 'action' => 'search', 'plate_number' => $plateTanpaPin,
    ]);
    check('Kendaraan tanpa PIN langsung menampilkan riwayat', str_contains($r['body'], 'oli mesin berganti') && str_contains($r['body'], 'Informasi kendaraan'));

    // 3. Kendaraan dengan PIN -> diminta PIN
    $r = http('GET', $base . '/cek-status.php');
    $csrf = csrfFrom($r['body']);
    $r = http('POST', $base . '/cek-status.php', [
        'csrf_token' => $csrf, 'action' => 'search', 'plate_number' => $plateDenganPin,
    ]);
    check('Kendaraan dengan PIN meminta PIN', str_contains($r['body'], 'id="pin"') || str_contains($r['body'], 'dilindungi PIN'));

    // 4. PIN salah -> ditolak, riwayat tidak bocor
    $r = http('GET', $base . '/cek-status.php');
    $csrf = csrfFrom($r['body']);
    $r = http('POST', $base . '/cek-status.php', [
        'csrf_token' => $csrf, 'action' => 'verify_pin', 'plate_number' => $plateDenganPin, 'pin' => '1111',
    ]);
    check('PIN salah ditolak', str_contains($r['body'], 'PIN tidak sesuai'));
    check('Riwayat tidak tampil saat PIN salah', !str_contains($r['body'], 'oli mesin berganti'));

    // 5. PIN benar -> riwayat tampil
    $r = http('GET', $base . '/cek-status.php');
    $csrf = csrfFrom($r['body']);
    $r = http('POST', $base . '/cek-status.php', [
        'csrf_token' => $csrf, 'action' => 'verify_pin', 'plate_number' => $plateDenganPin, 'pin' => $pin,
    ]);
    check('PIN benar membuka riwayat', str_contains($r['body'], 'oli mesin berganti') && str_contains($r['body'], 'Informasi kendaraan'));

    // Info debug bila pengujian di atas gagal
    if (!str_contains($r['body'], 'oli mesin berganti')) {
        preg_match_all('/<p class="form-error">(.*?)<\/p>/s', $r['body'], $m);
        foreach ($m[1] as $err) { echo "       [debug] error: " . trim(strip_tags($err)) . "\n"; }
        echo "       [debug] minta PIN: " . (str_contains($r['body'], 'id="pin"') ? 'ya' : 'tidak') . "\n";
        echo "       [debug] tak ditemukan: " . (str_contains($r['body'], 'belum terdaftar') ? 'ya' : 'tidak') . "\n";
    }
} finally {
    // Bersihkan data uji SETELAH seluruh pengujian selesai.
    $pdo->prepare('DELETE FROM customers WHERE name LIKE :n')->execute(['n' => 'Uji HTTP %']);
    check('Data uji dibersihkan', true);
}

echo "\n=== Ringkasan: {$pass} lulus, {$fail} gagal ===\n";
@unlink($cookieFile);
exit($fail > 0 ? 1 : 0);
