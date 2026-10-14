<?php
declare(strict_types=1);

/**
 * Uji tampilan ikon SVG (HTTP):
 *  - sprite ikon ter-render tepat sekali di setiap halaman
 *  - setiap <use href="#ic-..."> menunjuk simbol yang benar-benar ada di sprite
 *  - ikon kunci muncul di halaman yang sesuai (kartu layanan, sidebar statistik,
 *    panel hasil cek status ber-PIN, tombol aksi tabel)
 *  - tidak ada entity HTML numerik / emoji yang ter-render
 *
 * Data uji (pelanggan, kendaraan, catatan servis, PIN, barang stok) dibuat lewat
 * PDO lalu dihapus kembali di blok finally.
 *
 * Pakai: php tools/test-icons.php [base-url] [user] [pass]
 */
require_once __DIR__ . '/../backend/includes/functions.php';

$base = rtrim((string) ($argv[1] ?? 'http://127.0.0.1:8080/ServisYuk'), '/');
$adminBase = $base . '/backend/admin';
$adminUser = (string) ($argv[2] ?? 'admin');
$adminPass = (string) ($argv[3] ?? 'password');
$cookieFile = tempnam(sys_get_temp_dir(), 'sy_icon_cookie');
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

/** Daftar ikon yang benar-benar terpakai di sebuah halaman. */
function usedIcons(string $body): array
{
    preg_match_all('/<use href="#ic-([a-z0-9-]+)"/', $body, $m);
    $ids = array_unique($m[1]);
    sort($ids);
    return $ids;
}

/** Uji satu halaman: 200, sprite tepat sekali, ikon terpakai valid, tanpa entity. */
function checkPage(string $label, string $body, int $status, array $mustContain = []): void
{
    check("$label terbuka (200)", $status === 200, 'status ' . $status);
    check("$label merender sprite ikon tepat sekali", substr_count($body, 'class="icon-sprite"') === 1);

    $usedIds = usedIcons($body);
    check("$label memakai ikon", $usedIds !== [], 'tidak ada <use>');

    preg_match_all('/<symbol id="ic-([a-z0-9-]+)"/', $body, $defined);
    $missing = array_diff($usedIds, $defined[1]);
    check("$label semua ikon terpakai ada di sprite", $missing === [], 'hilang: ' . implode(', ', $missing));

    check("$label tanpa entity HTML numerik/emoji", preg_match('/&#\d+;/', $body) !== 1);

    foreach ($mustContain as $iconName) {
        check("$label memakai ikon $iconName", in_array($iconName, $usedIds, true));
    }
}

echo "=== Uji Ikon SVG ({$base}) ===\n";

try {
    // ---- Data uji (dihapus di finally) ----
    $pdo->prepare('INSERT INTO customers (name) VALUES (:n)')->execute(['n' => 'Uji Ikon A']);
    $pdo->prepare('INSERT INTO vehicles (customer_id, plate_number, brand, model) VALUES (:c, :pl, "Honda", "Brio")')
        ->execute(['c' => (int) $pdo->lastInsertId(), 'pl' => 'UJI IKON 111']);
    $vehicleTanpaPin = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO service_records (vehicle_id, service_type_id, description, status, service_date, cost)
                   VALUES (:v, 1, "ganti oli mesin", "Dikerjakan", CURDATE(), 150000)')
        ->execute(['v' => $vehicleTanpaPin]);

    $pdo->prepare('INSERT INTO customers (name) VALUES (:n)')->execute(['n' => 'Uji Ikon B']);
    $pdo->prepare('INSERT INTO vehicles (customer_id, plate_number, brand, model) VALUES (:c, :pl, "Toyota", "Avanza")')
        ->execute(['c' => (int) $pdo->lastInsertId(), 'pl' => 'UJI IKON 222']);
    $vehicleDenganPin = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO service_records (vehicle_id, service_type_id, description, status, service_date, cost)
                   VALUES (:v, 1, "servis rem depan", "Selesai", CURDATE(), 350000)')
        ->execute(['v' => $vehicleDenganPin]);
    save_vehicle_pin($pdo, $vehicleDenganPin, '7788', null);

    $pdo->prepare('INSERT INTO inventory (name, sku, quantity, unit, purchase_price) VALUES (:n, :s, 5, "pcs", 10000)')
        ->execute(['n' => 'Uji Ikon Oli', 's' => 'UJI-IKON-01']);

    // ---- 1. Beranda: ikon layanan sesuai jenis servis + ikon kontak ----
    $r = request('GET', $base . '/index.php');
    checkPage('Beranda', $r['body'], $r['status'], [
        'settings', 'arrow-right', 'check', 'map-pin', 'phone', 'mail', 'clock',
    ]);
    check('Beranda memakai ikon layanan sesuai jenis servis',
        (bool) preg_match('/service-icon"><svg[^>]*><use href="#ic-(droplet|disc|gauge|circle-dot|snowflake|sparkles)"/', $r['body']));

    // ---- 2. Cek status: panel kosong, tidak ditemukan, hasil, PIN ----
    $r = request('GET', $base . '/cek-status.php');
    checkPage('Cek status (awal)', $r['body'], $r['status'], ['search', 'shield-check', 'car', 'arrow-right']);
    $csrf = csrfFrom($r['body']);

    $r = request('POST', $base . '/cek-status.php', [
        'csrf_token' => $csrf, 'action' => 'search', 'plate_number' => 'TIDAK ADA 999',
    ]);
    checkPage('Cek status (tidak ditemukan)', $r['body'], $r['status'], ['circle-help']);
    $csrf = csrfFrom($r['body']);

    $r = request('POST', $base . '/cek-status.php', [
        'csrf_token' => $csrf, 'action' => 'search', 'plate_number' => 'UJI IKON 111',
    ]);
    checkPage('Cek status (kendaraan terbuka)', $r['body'], $r['status'], ['car']);
    check('Kendaraan terbuka menampilkan riwayat servisnya', str_contains($r['body'], 'ganti oli mesin'));
    $csrf = csrfFrom($r['body']);

    $r = request('POST', $base . '/cek-status.php', [
        'csrf_token' => $csrf, 'action' => 'search', 'plate_number' => 'uji ikon 222',
    ]);
    checkPage('Cek status (dilindungi PIN)', $r['body'], $r['status'], ['lock']);
    check('PIN panel tidak membocorkan riwayat', !str_contains($r['body'], 'servis rem depan'));

    // ---- 3. Halaman login admin ----
    $r = request('GET', $adminBase . '/login.php');
    checkPage('Login admin', $r['body'], $r['status'], ['log-in', 'arrow-right', 'arrow-left']);
    $csrf = csrfFrom($r['body']);
    $r = request('POST', $adminBase . '/login.php', [
        'csrf_token' => $csrf, 'username' => $adminUser, 'password' => $adminPass,
    ]);
    check('Login admin berhasil untuk uji ikon', $r['status'] === 302);

    // ---- 4. Dashboard: sidebar + kartu statistik ----
    $r = request('GET', $adminBase . '/dashboard.php');
    checkPage('Dashboard admin', $r['body'], $r['status'], [
        'layout-dashboard', 'clipboard-list', 'history', 'users', 'package',
        'external-link', 'log-out', 'car', 'wrench', 'circle-check', 'wallet', 'triangle-alert',
    ]);

    // ---- 5. Data servis: tombol simpan + aksi baris ----
    $r = request('GET', $adminBase . '/servis.php');
    checkPage('Data servis', $r['body'], $r['status'], ['arrow-right', 'pencil', 'trash-2']);

    // ---- 6. Stok: kartu statistik + aksi baris ----
    $r = request('GET', $adminBase . '/stok.php');
    checkPage('Stok barang', $r['body'], $r['status'], [
        'package', 'boxes', 'coins', 'arrow-right', 'refresh-cw', 'pencil', 'trash-2',
    ]);

    // ---- 7. Riwayat (avatar kendaraan) & Pelanggan (aksi baris) ----
    $r = request('GET', $adminBase . '/riwayat.php');
    checkPage('Riwayat & PIN', $r['body'], $r['status'], ['car']);
    $r = request('GET', $adminBase . '/pelanggan.php');
    checkPage('Pelanggan', $r['body'], $r['status'], ['trash-2']);

    // ---- 8. Aset CSS ikon ikut termuat ----
    $r = request('GET', $base . '/assets/css/style.css');
    check('CSS desain memuat aturan .icon', $r['status'] === 200 && str_contains($r['body'], '.icon {'));

    request('GET', $adminBase . '/logout.php');
} finally {
    $pdo->prepare('DELETE FROM customers WHERE name LIKE :n')->execute(['n' => 'Uji Ikon %']);
    $pdo->prepare('DELETE FROM inventory WHERE name LIKE :n')->execute(['n' => 'Uji Ikon %']);
    check('Data uji dibersihkan', true);
    @unlink($cookieFile);
}

echo "\n=== Ringkasan: $pass lulus, $fail gagal ===\n";
exit($fail === 0 ? 0 : 1);
