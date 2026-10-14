<?php
declare(strict_types=1);
/**
 * Uji API REST ServisYuk (pengujian lokal).
 * Menguji seluruh endpoint: auth, dashboard, customers, vehicles,
 * service-types, services, payments, inventory, lookup.
 * Jalankan: php tools/test-api.php [base_url] [user] [pass]
 */
$base = rtrim((string) ($argv[1] ?? 'http://127.0.0.1:8080/ServisYuk'), '/');
$user = (string) ($argv[2] ?? 'admin');
$pass = (string) ($argv[3] ?? 'password');
$api = $base . '/backend/api/index.php';
$cookieFile = tempnam(sys_get_temp_dir(), 'sy_api');
$passCount = 0;
$failCount = 0;

// Dimuat di awal agar tidak ada output sebelum sesi dimulai.
require_once __DIR__ . '/../backend/includes/functions.php';

function call(string $method, string $path, array $body = [], array $headers = []): array
{
    global $cookieFile, $api;
    // Pisahkan query string dari path, lalu encode tiap nilainya dengan benar.
    $query = '';
    if (strpos($path, '?') !== false) {
        [$path, $qs] = explode('?', $path, 2);
        $pairs = [];
        foreach (explode('&', $qs) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $pairs[] = rawurlencode($key) . '=' . rawurlencode($value);
        }
        $query = $pairs ? '&' . implode('&', $pairs) : '';
    }
    $url = $api . '?path=' . urlencode($path) . $query;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HEADER => false,
    ]);
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        $headers[] = 'Content-Type: application/json';
    }
    if ($headers) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    return ['status' => $status, 'body' => is_array($decoded) ? $decoded : []];
}

function check(string $name, bool $ok, string $extra = ''): void
{
    global $passCount, $failCount;
    if ($ok) { $passCount++; echo "  [OK]   $name\n"; }
    else { $failCount++; echo "  [GAGAL] $name" . ($extra ? " — $extra" : '') . "\n"; }
}

echo "=== Uji API ServisYuk ({$base}) ===\n";

// Ambil CSRF token
$r = call('GET', '/auth/csrf');
$csrf = $r['body']['data']['csrf_token'] ?? '';
check('GET /auth/csrf mengembalikan token', $csrf !== '');

// Setup needed (sudah ada admin)
$r = call('GET', '/auth/setup-needed');
check('GET /auth/setup-needed (sudah setup)', $r['body']['data']['setup_needed'] === false);

// Login (POST memerlukan header X-CSRF-Token)
$r = call('POST', '/auth/login', ['username' => $user, 'password' => $pass], ['X-CSRF-Token: ' . $csrf]);
check('POST /auth/login berhasil', $r['status'] === 200 && isset($r['body']['data']['id']), json_encode($r['body']));
$csrfAfterLogin = $r['body']['data']['csrf_token'] ?? $csrf;

// Auth me
$r = call('GET', '/auth/me');
check('GET /auth/me mengembalikan admin', ($r['body']['data']['username'] ?? '') === $user, json_encode($r['body']));

// Dashboard summary
$r = call('GET', '/dashboard');
check('GET /dashboard mengembalikan counts', isset($r['body']['data']['counts']['customers']), json_encode($r['body']));

// ---- Customers CRUD ----
$r = call('POST', '/customers', ['name' => 'Uji API', 'phone' => '08123', 'email' => 'uji@api.test', 'plate_number' => 'API 100', 'brand' => 'Toyota', 'model' => 'Avanza', 'year' => 2020], ['X-CSRF-Token: ' . $csrfAfterLogin]);
$customerId = $r['body']['data']['id'] ?? 0;
check('POST /customers membuat pelanggan + kendaraan', $r['status'] === 200 && $customerId > 0, json_encode($r['body']));

$r = call('GET', '/customers?q=Uji');
check('GET /customers?q= menemukan pelanggan', count($r['body']['data'] ?? []) >= 1, json_encode($r['body']));

$r = call('GET', '/customers/' . $customerId);
check('GET /customers/{id} mengembalikan data + kendaraan', isset($r['body']['data']['vehicles'][0]['plate_number']));

$r = call('PUT', '/customers/' . $customerId, ['name' => 'Uji API Updated', 'phone' => '08999', 'email' => 'uji2@api.test'], ['X-CSRF-Token: ' . $csrfAfterLogin]);
check('PUT /customers/{id} memperbarui pelanggan', ($r['body']['data']['updated'] ?? false) === true, json_encode($r['body']));

// ---- Vehicles ----
$r = call('GET', '/vehicles?q=API');
$vehicleId = $r['body']['data'][0]['id'] ?? 0;
check('GET /vehicles?q= menemukan kendaraan', $vehicleId > 0);

// ---- Service types ----
$r = call('GET', '/service-types');
$serviceTypeId = $r['body']['data'][0]['id'] ?? 0;
check('GET /service-types mengembalikan daftar', count($r['body']['data'] ?? []) >= 1, json_encode($r['body']));

// ---- Services CRUD ----
$r = call('POST', '/services', ['vehicle_id' => $vehicleId, 'service_type_id' => $serviceTypeId, 'description' => 'Uji API servis', 'status' => 'Menunggu', 'service_date' => date('Y-m-d'), 'cost' => 200000], ['X-CSRF-Token: ' . $csrfAfterLogin]);
$serviceId = $r['body']['data']['id'] ?? 0;
check('POST /services membuat catatan servis', $r['status'] === 200 && $serviceId > 0, json_encode($r['body']));

$r = call('GET', '/services?status=Menunggu');
check('GET /services?status= filter berfungsi', count($r['body']['data'] ?? []) >= 1, json_encode($r['body']));

$r = call('PUT', '/services/' . $serviceId, ['status' => 'Dikerjakan', 'cost' => 250000], ['X-CSRF-Token: ' . $csrfAfterLogin]);
check('PUT /services/{id} memperbarui status & biaya', ($r['body']['data']['updated'] ?? false) === true, json_encode($r['body']));

$r = call('GET', '/services/' . $serviceId);
check('GET /services/{id} mengembalikan data', ($r['body']['data']['status'] ?? '') === 'Dikerjakan', json_encode($r['body']));

// ---- Payments ----
$r = call('POST', '/payments', ['service_record_id' => $serviceId, 'amount' => 250000, 'method' => 'Tunai', 'status' => 'Lunas'], ['X-CSRF-Token: ' . $csrfAfterLogin]);
check('POST /payments mencatat pembayaran', ($r['body']['data']['saved'] ?? false) === true, json_encode($r['body']));

$r = call('GET', '/payments?status=Lunas');
check('GET /payments?status= Lunas', count($r['body']['data'] ?? []) >= 1, json_encode($r['body']));

// ---- Inventory ----
$r = call('POST', '/inventory', ['name' => 'Oli Mesin', 'sku' => 'OLI-001', 'quantity' => 10, 'unit' => 'liter', 'purchase_price' => 50000], ['X-CSRF-Token: ' . $csrfAfterLogin]);
$inventoryId = $r['body']['data']['id'] ?? 0;
check('POST /inventory menambah barang', $r['status'] === 200 && $inventoryId > 0, json_encode($r['body']));

$r = call('PUT', '/inventory/' . $inventoryId, ['quantity' => 25], ['X-CSRF-Token: ' . $csrfAfterLogin]);
check('PUT /inventory/{id} memperbarui stok', ($r['body']['data']['updated'] ?? false) === true, json_encode($r['body']));

$r = call('GET', '/inventory?q=Oli');
check('GET /inventory?q= menemukan barang', count($r['body']['data'] ?? []) >= 1, json_encode($r['body']));

// ---- Lookup publik (tanpa PIN) ----
$r = call('GET', '/lookup?plate=API 100');
check('GET /lookup?plate= menemukan kendaraan', $r['status'] === 200 && isset($r['body']['data']['vehicle']), json_encode($r['body']));
check('Lookup mengembalikan riwayat servis', count($r['body']['data']['services'] ?? []) >= 1, json_encode($r['body']));

// Lookup salah
$r = call('GET', '/lookup?plate=TIDAK ADA');
check('Lookup nomor tak ditemukan -> 404', $r['status'] === 404);

// ---- Lookup dengan PIN ----
$pdo = db();
save_vehicle_pin($pdo, $vehicleId, '5678', null);

$r = call('GET', '/lookup?plate=API 100');
check('Lookup kendaraan ber-PIN -> 401 pin_required', $r['status'] === 401 && $r['body']['code'] === 'pin_required');

$r = call('GET', '/lookup?plate=API 100&pin=5678');
check('Lookup dengan PIN benar -> 200', $r['status'] === 200 && count($r['body']['data']['services'] ?? []) >= 1, json_encode($r['body']));

$r = call('GET', '/lookup?plate=API 100&pin=9999');
check('Lookup dengan PIN salah -> 401', $r['status'] === 401);

// ---- Proteksi CSRF ----
$r = call('POST', '/services', ['vehicle_id' => $vehicleId, 'service_type_id' => $serviceTypeId, 'status' => 'Menunggu', 'service_date' => date('Y-m-d'), 'cost' => 100], []);
check('POST tanpa X-CSRF-Token -> 403', $r['status'] === 403);

// ---- Cleanup ----
$r = call('DELETE', '/services/' . $serviceId, [], ['X-CSRF-Token: ' . $csrfAfterLogin]);
$r = call('DELETE', '/inventory/' . $inventoryId, [], ['X-CSRF-Token: ' . $csrfAfterLogin]);
$r = call('DELETE', '/customers/' . $customerId, [], ['X-CSRF-Token: ' . $csrfAfterLogin]);
$pdo->prepare('DELETE FROM customers WHERE name LIKE :n')->execute(['n' => 'Uji API%']);
$pdo->prepare('DELETE FROM inventory WHERE sku = :s')->execute(['s' => 'OLI-001']);
check('Data uji dibersihkan', true);

echo "\n=== Ringkasan: {$passCount} lulus, {$failCount} gagal ===\n";
@unlink($cookieFile);
exit($failCount > 0 ? 1 : 0);
