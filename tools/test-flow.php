<?php
declare(strict_types=1);
/**
 * Skrip uji otomatis (pengujian lokal):
 * Menguji login admin + proteksi halaman admin + lookup publik.
 * Jalankan dengan server berjalan: C:\xampp\php\php.exe tools\test-flow.php
 */
$base = rtrim((string) ($argv[1] ?? 'http://127.0.0.1:8080/ServisYuk'), '/');
$adminUser = (string) ($argv[2] ?? 'admin');
$adminPass = (string) ($argv[3] ?? 'password');
$cookieFile = tempnam(sys_get_temp_dir(), 'sy_cookie');
$pass = 0;
$fail = 0;

function request(string $method, string $url, array $post = [], string $cookie = ''): array
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

function check(string $name, bool $ok): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  [OK]   $name\n"; }
    else { $fail++; echo "  [GAGAL] $name\n"; }
}

function csrfFrom(string $html): string
{
    preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $html, $m);
    return $m[1] ?? '';
}

echo "=== Uji Alur ServisYuk ({$base}) ===\n";

// 1. Halaman publik
$r = request('GET', $base . '/index.php');
check('Halaman beranda 200', $r['status'] === 200);
check('Beranda menampilkan ServisYuk', str_contains($r['body'], 'ServisYuk'));
check('Beranda menampilkan daftar layanan dari DB', str_contains($r['body'], 'Ganti oli'));

// 2. Lookup tidak ditemukan
$r = request('GET', $base . '/cek-status.php');
$csrf = csrfFrom($r['body']);
$r = request('POST', $base . '/cek-status.php', [
    'csrf_token' => $csrf,
    'action' => 'search',
    'plate_number' => 'ZZZ 9999 ZZZ',
]);
check('Pencarian nomor polisi tak diberitahu menampilkan pesan tidak ditemukan',
    str_contains($r['body'], 'belum terdaftar') || str_contains($r['body'], 'belum ditemukan'));

// 3. Halaman admin dilindungi (harus redirect ke login)
$r = request('GET', $base . '/backend/admin/dashboard.php');
check('Dashboard admin terproteksi (redirect ke login)', $r['status'] === 302 && str_contains($r['headers'], 'login.php'));

// 4. Login admin
$r = request('GET', $base . '/backend/admin/login.php');
$csrf = csrfFrom($r['body']);
$r = request('POST', $base . '/backend/admin/login.php', [
    'csrf_token' => $csrf,
    'username' => $adminUser,
    'password' => $adminPass,
]);
check('Login admin berhasil (redirect ke dashboard)', $r['status'] === 302 && str_contains($r['headers'], 'dashboard.php'));

// 5. Setelah login, semua halaman admin terbuka
foreach (['dashboard.php', 'servis.php', 'riwayat.php', 'pelanggan.php', 'stok.php'] as $page) {
    $r = request('GET', $base . '/backend/admin/' . $page);
    check("Halaman admin/{$page} terbuka (200)", $r['status'] === 200);
}

// 6. Login dengan password salah ditolak
request('GET', $base . '/backend/admin/logout.php');
$r = request('GET', $base . '/backend/admin/login.php');
$csrf = csrfFrom($r['body']);
$r = request('POST', $base . '/backend/admin/login.php', [
    'csrf_token' => $csrf,
    'username' => $adminUser,
    'password' => 'salahbanget',
]);
check('Login dengan password salah ditolak', !str_contains($r['headers'], 'dashboard.php'));

echo "\n=== Ringkasan: {$pass} lulus, {$fail} gagal ===\n";
@unlink($cookieFile);
exit($fail > 0 ? 1 : 0);
