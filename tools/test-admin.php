<?php
declare(strict_types=1);
/**
 * Uji alur form admin (POST klasik + CSRF + flash) pada pengujian lokal.
 * Menguji: tambah pelanggan, tambah servis, ubah status, filter, dashboard,
 * pembayaran, validasi input, PIN, stok, proteksi CSRF, lalu membersihkan data uji.
 * Jalankan: php tools/test-admin.php [base_url] [user] [pass]
 */
$base = rtrim((string) ($argv[1] ?? 'http://127.0.0.1:8080/ServisYuk'), '/');
$user = (string) ($argv[2] ?? 'admin');
$pass = (string) ($argv[3] ?? 'password');
$admin = $base . '/backend/admin';
$cookieFile = tempnam(sys_get_temp_dir(), 'sy_admin');
$passCount = 0;
$failCount = 0;

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
    return ['status' => $status, 'headers' => substr($raw, 0, $size), 'body' => substr($raw, $size)];
}

function csrfFrom(string $html): string
{
    preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $html, $m);
    return $m[1] ?? '';
}

/** Ambil nilai <option> yang teksnya mengandung kunci, dari satu blok <select>. */
function optionId(string $html, string $selectName, string $needle): int
{
    if (!preg_match('/<select[^>]*name="' . preg_quote($selectName, '/') . '"[^>]*>(.*?)<\/select>/s', $html, $block)) {
        return 0;
    }
    preg_match_all('/<option value="(\d+)"[^>]*>(.*?)<\/option>/s', $block[1], $all, PREG_SET_ORDER);
    foreach ($all as $option) {
        if (stripos(strip_tags($option[2]), $needle) !== false) {
            return (int) $option[1];
        }
    }
    return 0;
}

/** Semua nilai input tersembunyi bernama tertentu. */
function hiddenValues(string $html, string $name): array
{
    preg_match_all('/name="' . preg_quote($name, '/') . '" value="(\d+)"/', $html, $m);
    return array_map('intval', $m[1]);
}

function check(string $name, bool $ok, string $extra = ''): void
{
    global $passCount, $failCount;
    if ($ok) { $passCount++; echo "  [OK]   $name\n"; }
    else { $failCount++; echo "  [GAGAL] $name" . ($extra ? " — $extra" : '') . "\n"; }
}

echo "=== Uji Form Admin ({$base}) ===\n";

// Login
$r = http('GET', $admin . '/login.php');
$csrf = csrfFrom($r['body']);
$r = http('POST', $admin . '/login.php', [
    'csrf_token' => $csrf, 'username' => $user, 'password' => $pass,
]);
check('Login admin', $r['status'] === 302 && str_contains($r['headers'], 'dashboard.php'));

// --- Pelanggan + kendaraan ---
$r = http('GET', $admin . '/pelanggan.php');
$csrf = csrfFrom($r['body']);
$r = http('POST', $admin . '/pelanggan.php', [
    'csrf_token' => $csrf, 'action' => 'save_customer',
    'name' => 'Pelanggan Uji Admin', 'phone' => '081200001', 'email' => 'uji@admin.test',
    'plate_number' => 'UJI ADM 01', 'brand' => 'Honda', 'model' => 'Brio', 'year' => '2021',
]);
check('Form tambah pelanggan + kendaraan', $r['status'] === 302);
$r = http('GET', $admin . '/pelanggan.php');
check('Flash sukses pelanggan tampil', str_contains($r['body'], 'Pelanggan dan kendaraan berhasil ditambahkan.'));
check('Kendaraan baru tampil di daftar', str_contains($r['body'], 'UJI ADM 01'));

// --- Tambah catatan servis ---
$r = http('GET', $admin . '/servis.php');
$csrf = csrfFrom($r['body']);
$vehicleId = optionId($r['body'], 'vehicle_id', 'UJI ADM 01');
$serviceTypeId = optionId($r['body'], 'service_type_id', 'Ganti oli');
check('Select kendaraan memuat kendaraan baru', $vehicleId > 0, "vehicle_id={$vehicleId}");
check('Select jenis servis memuat katalog layanan', $serviceTypeId > 0, "service_type_id={$serviceTypeId}");

$r = http('POST', $admin . '/servis.php', [
    'csrf_token' => $csrf, 'action' => 'save_service',
    'vehicle_id' => $vehicleId, 'service_type_id' => $serviceTypeId,
    'description' => 'Rem bunyi saat pengereman, oli mesin berganti',
    'status' => 'Menunggu', 'service_date' => date('Y-m-d'), 'cost' => '175000',
]);
check('Form tambah catatan servis', $r['status'] === 302);
$r = http('GET', $admin . '/servis.php');
check('Flash sukses servis tampil', str_contains($r['body'], 'Catatan servis berhasil ditambahkan.'));
$serviceIds = array_values(array_filter(hiddenValues($r['body'], 'service_id')));
$serviceId = $serviceIds[0] ?? 0;
check('Catatan servis baru terdaftar', $serviceId > 0, 'service_id=' . $serviceId);

// --- Filter status & plat ---
$r = http('GET', $admin . '/servis.php?status=Menunggu&plate=uji+adm');
check('Filter status+plat menemukan catatan', str_contains($r['body'], 'oli mesin berganti'));
// Pakai plat yang pasti tidak ada agar hasil kosong tetap teruji walau ada
// data pelanggan asli lain di database (tidak bergantung pada DB kosong).
$r = http('GET', $admin . '/servis.php?status=Dikerjakan&plate=uji+tidak+ada');
check('Filter tanpa hasil menampilkan empty state',
    !str_contains($r['body'], 'oli mesin berganti') && str_contains($r['body'], 'table-empty'));

// --- Ubah status servis ---
$r = http('GET', $admin . '/servis.php');
$csrf = csrfFrom($r['body']);
$r = http('POST', $admin . '/servis.php', [
    'csrf_token' => $csrf, 'action' => 'update_service', 'service_id' => $serviceId,
    'status' => 'Dikerjakan', 'service_date' => date('Y-m-d'), 'cost' => '200000',
    'description' => 'Rem bunyi saat pengereman, oli mesin berganti',
]);
check('Form ubah status -> Dikerjakan', $r['status'] === 302);
$r = http('GET', $admin . '/servis.php?status=Dikerjakan');
check('Status Dikerjakan tampil setelah diubah', str_contains($r['body'], 'oli mesin berganti'));

// --- Ubah status dari dashboard (antrean) ---
$r = http('GET', $admin . '/dashboard.php');
$csrf = csrfFrom($r['body']);
check('Dashboard menampilkan form antrean (csrf ada)', $csrf !== '');
$r = http('POST', $admin . '/dashboard.php', [
    'csrf_token' => $csrf, 'action' => 'update_service_status',
    'service_id' => $serviceId, 'status' => 'Selesai',
]);
check('Ubah status dari dashboard', $r['status'] === 302);
$r = http('GET', $admin . '/dashboard.php');
check('Flash dashboard tampil + status Selesai',
    str_contains($r['body'], 'Status servis berhasil diperbarui.') && str_contains($r['body'], 'Selesai'));

// --- Pembayaran ---
$r = http('GET', $admin . '/pelanggan.php');
$csrf = csrfFrom($r['body']);
$r = http('POST', $admin . '/pelanggan.php', [
    'csrf_token' => $csrf, 'action' => 'save_payment',
    'payment_service_id' => $serviceId, 'payment_status' => 'Lunas',
    'payment_method' => 'Tunai', 'amount' => '200000',
]);
check('Form simpan pembayaran', $r['status'] === 302);
$r = http('GET', $admin . '/pelanggan.php');
check('Flash pembayaran tampil', str_contains($r['body'], 'Pembayaran berhasil disimpan.'));

// --- Validasi ---
$r = http('POST', $admin . '/pelanggan.php', [
    'csrf_token' => $csrf, 'action' => 'save_payment',
    'payment_service_id' => $serviceId, 'payment_status' => 'Hack',
    'payment_method' => 'Tunai', 'amount' => '1000',
]);
$r = http('GET', $admin . '/pelanggan.php');
check('Status pembayaran tidak valid ditolak', str_contains($r['body'], 'Data pembayaran tidak valid.'));

$r = http('POST', $admin . '/pelanggan.php', [
    'csrf_token' => $csrf, 'action' => 'save_customer',
    'name' => 'Pelanggan Duplikat', 'phone' => '', 'email' => '',
    'plate_number' => 'UJI ADM 01', 'brand' => 'Toyota', 'model' => 'Agya', 'year' => '',
]);
$r = http('GET', $admin . '/pelanggan.php');
check('Nomor polisi duplikat ditolak', str_contains($r['body'], 'Data gagal disimpan.'));

$r = http('POST', $admin . '/servis.php', [
    'csrf_token' => $csrf, 'action' => 'save_service',
    'vehicle_id' => 0, 'service_type_id' => $serviceTypeId,
    'status' => 'Menunggu', 'service_date' => date('Y-m-d'), 'cost' => '1000',
]);
$r = http('GET', $admin . '/servis.php');
check('Kendaraan tidak valid ditolak', str_contains($r['body'], 'Pilih kendaraan yang valid.'));

// --- PIN akses kendaraan ---
$r = http('GET', $admin . '/riwayat.php');
$csrf = csrfFrom($r['body']);
$r = http('POST', $admin . '/riwayat.php', [
    'csrf_token' => $csrf, 'action' => 'set_pin', 'vehicle_id' => $vehicleId,
    'pin' => '2468', 'pin_days' => '30',
]);
check('Form pasang PIN', $r['status'] === 302);
$r = http('GET', $admin . '/riwayat.php');
check('Flash PIN tampil', str_contains($r['body'], 'PIN akses'));

$r = http('POST', $admin . '/riwayat.php', [
    'csrf_token' => $csrf, 'action' => 'set_pin', 'vehicle_id' => $vehicleId,
    'pin' => 'abcd', 'pin_days' => '0',
]);
$r = http('GET', $admin . '/riwayat.php');
check('PIN non-angka ditolak', str_contains($r['body'], 'PIN harus terdiri dari 4 sampai 6 digit angka.'));

// --- Pelanggan sekarang diminta PIN; PIN salah ditolak, benar membuka ---
$r = http('GET', $base . '/cek-status.php');
$csrf = csrfFrom($r['body']);
$r = http('POST', $base . '/cek-status.php', [
    'csrf_token' => $csrf, 'action' => 'search', 'plate_number' => '  uji  adm 01 ',
]);
check('Plat ber-PIN meminta verifikasi', str_contains($r['body'], 'dilindungi PIN'));
check('Tantangan PIN dirender tanpa fatal error', !str_contains($r['body'], 'Fatal error'));

$r = http('POST', $base . '/cek-status.php', [
    'csrf_token' => $csrf, 'action' => 'verify_pin', 'plate_number' => 'UJI ADM 01', 'pin' => '1111',
]);
check('PIN salah ditolak', str_contains($r['body'], 'PIN tidak sesuai'));
check('Riwayat tertutup saat PIN salah', !str_contains($r['body'], 'oli mesin berganti'));

$r = http('POST', $base . '/cek-status.php', [
    'csrf_token' => $csrf, 'action' => 'verify_pin', 'plate_number' => 'UJI ADM 01', 'pin' => '2468',
]);
check('PIN benar membuka riwayat', str_contains($r['body'], 'Informasi kendaraan') && str_contains($r['body'], 'oli mesin berganti'));
check('Kartu kendaraan menampilkan tahun (2021, tanpa fatal)',
    str_contains($r['body'], '2021') && !str_contains($r['body'], 'Fatal error'));
check('Data pribadi tidak bocor (telepon/email disembunyikan)',
    !str_contains($r['body'], '081200001') && !str_contains($r['body'], 'uji@admin.test'));

// --- Stok barang ---
$r = http('GET', $admin . '/stok.php');
$csrf = csrfFrom($r['body']);
$r = http('POST', $admin . '/stok.php', [
    'csrf_token' => $csrf, 'action' => 'save_stock', 'inventory_id' => '0',
    'stock_name' => 'Kampas Rem Uji', 'sku' => 'UJI-SKU-01', 'quantity' => '5',
    'unit' => 'pcs', 'purchase_price' => '125000',
]);
check('Form tambah stok', $r['status'] === 302);
$r = http('GET', $admin . '/stok.php');
check('Flash stok tampil + barang ada',
    str_contains($r['body'], 'Barang berhasil ditambahkan ke stok.') && str_contains($r['body'], 'Kampas Rem Uji'));
$stockIds = array_values(array_filter(hiddenValues($r['body'], 'inventory_id')));
$inventoryId = $stockIds[0] ?? 0;
check('Barang stok terdaftar', $inventoryId > 0, 'inventory_id=' . $inventoryId);

$r = http('POST', $admin . '/stok.php', [
    'csrf_token' => $csrf, 'action' => 'save_stock', 'inventory_id' => (string) $inventoryId,
    'stock_name' => 'Kampas Rem Uji', 'sku' => 'UJI-SKU-01', 'quantity' => '9',
    'unit' => 'pcs', 'purchase_price' => '130000',
]);
$r = http('GET', $admin . '/stok.php');
check('Qty stok ter-update menjadi 9', str_contains($r['body'], 'value="9"'));

$r = http('POST', $admin . '/stok.php', [
    'csrf_token' => $csrf, 'action' => 'save_stock', 'inventory_id' => '0',
    'stock_name' => 'Barang Sama', 'sku' => 'UJI-SKU-01', 'quantity' => '1',
    'unit' => 'pcs', 'purchase_price' => '1000',
]);
$r = http('GET', $admin . '/stok.php');
check('SKU duplikat ditolak', str_contains($r['body'], 'Data gagal disimpan.'));

// --- Proteksi CSRF ---
$r = http('POST', $admin . '/servis.php', ['action' => 'save_service']);
check('POST tanpa CSRF token ditolak (403)', $r['status'] === 403, 'status=' . $r['status']);
$r = http('POST', $admin . '/servis.php', ['csrf_token' => 'abc', 'action' => 'save_service']);
check('POST dengan CSRF palsu ditolak (403)', $r['status'] === 403, 'status=' . $r['status']);

// --- Logout ---
$r = http('GET', $admin . '/logout.php');
check('Logout berhasil', $r['status'] === 302 && str_contains($r['headers'], '../index.php'));
$r = http('GET', $admin . '/dashboard.php');
check('Setelah logout, dashboard terproteksi', $r['status'] === 302 && str_contains($r['headers'], 'login.php'));

// --- Pembersihan data uji (login ulang dulu) ---
$r = http('GET', $admin . '/login.php');
$csrf = csrfFrom($r['body']);
http('POST', $admin . '/login.php', ['csrf_token' => $csrf, 'username' => $user, 'password' => $pass]);

$r = http('GET', $admin . '/servis.php');
$csrf = csrfFrom($r['body']);
http('POST', $admin . '/servis.php', [
    'csrf_token' => $csrf, 'action' => 'delete_service', 'service_id' => $serviceId,
]);
$r = http('GET', $admin . '/stok.php');
$csrf = csrfFrom($r['body']);
http('POST', $admin . '/stok.php', [
    'csrf_token' => $csrf, 'action' => 'delete_stock', 'inventory_id' => $inventoryId,
]);
$r = http('GET', $admin . '/riwayat.php');
$csrf = csrfFrom($r['body']);
http('POST', $admin . '/riwayat.php', [
    'csrf_token' => $csrf, 'action' => 'delete_pin', 'vehicle_id' => $vehicleId,
]);
$r = http('GET', $admin . '/pelanggan.php');
$csrf = csrfFrom($r['body']);
$customerIds = hiddenValues($r['body'], 'customer_id');
if ($customerIds) {
    http('POST', $admin . '/pelanggan.php', [
        'csrf_token' => $csrf, 'action' => 'delete_customer', 'customer_id' => $customerIds[0],
    ]);
}

echo "\n=== Ringkasan: {$passCount} lulus, {$failCount} gagal ===\n";
@unlink($cookieFile);
exit($failCount > 0 ? 1 : 0);
