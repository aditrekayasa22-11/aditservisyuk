<?php
declare(strict_types=1);
/**
 * Uji alur setup admin pertama (pengujian lokal).
 * Alur ini hanya muncul saat tabel admins kosong, jadi skrip ini:
 *   1. membackup baris admin yang ada,
 *   2. mengosongkan tabel, lalu menjalankan setup-admin.php,
 *   3. memverifikasi akun baru bisa dipakai login,
 *   4. mengembalikan baris admin semula persis seperti awal.
 * Jalankan: php tools/test-setup.php [base_url]
 */
require_once __DIR__ . '/../backend/includes/functions.php';

$base = rtrim((string) ($argv[1] ?? 'http://127.0.0.1:8080/ServisYuk'), '/');
$admin = $base . '/backend/admin';
$cookieFile = tempnam(sys_get_temp_dir(), 'sy_setup');
$passCount = 0;
$failCount = 0;

function http(string $method, string $url, array $post = []): array
{
    global $cookieFile;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 15, CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile, CURLOPT_HEADER => true,
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
function check(string $name, bool $ok, string $extra = ''): void
{
    global $passCount, $failCount;
    if ($ok) { $passCount++; echo "  [OK]   $name\n"; }
    else { $failCount++; echo "  [GAGAL] $name" . ($extra ? " — $extra" : '') . "\n"; }
}

echo "=== Uji Setup Admin Pertama ({$base}) ===\n";

$pdo = db();

// 1. Backup baris admin yang ada
$admins = $pdo->query('SELECT id, username, password_hash, name FROM admins ORDER BY id')->fetchAll();
if (!$admins) {
    echo "  [LEWAT] Tidak ada admin untuk dibackup, lanjut tanpa pemulihan.\n";
}

// 2. Kosongkan tabel agar alur setup muncul
$pdo->exec('DELETE FROM admins');
check('Tabel admin dikosongkan sementara', (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0);

// 3. Halaman login menawarkan setup
$r = http('GET', $admin . '/login.php');
check('Login menawarkan setup admin', str_contains($r['body'], 'setup-admin.php'));

// 4. Halaman setup menampilkan formulir lengkap
$r = http('GET', $admin . '/setup-admin.php');
$csrf = csrfFrom($r['body']);
check('Halaman setup terbuka (200)', $r['status'] === 200);
check('Form setup punya CSRF', $csrf !== '');
check('Form setup punya field nama tampilan', str_contains($r['body'], 'name="name"'));

// 5. Validasi: password pendek ditolak (error ditampilkan pada respons POST yang sama)
$r = http('POST', $admin . '/setup-admin.php', [
    'csrf_token' => $csrf, 'username' => 'tempadmin', 'name' => 'Admin Uji', 'password' => 'pendek',
]);
check('Password < 10 karakter ditolak',
    str_contains($r['body'], 'Password harus terdiri dari minimal 10 karakter.'), 'status=' . $r['status']);
check('Setup gagal tidak membuat baris admin',
    (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0);
$csrf = csrfFrom($r['body']) ?: $csrf;

// 6. Setup berhasil
$csrf = csrfFrom($r['body']);
$r = http('POST', $admin . '/setup-admin.php', [
    'csrf_token' => $csrf, 'username' => 'tempadmin', 'name' => 'Admin Uji', 'password' => 'passwordrahasia123',
]);
check('Setup admin berhasil', $r['status'] === 200 || $r['status'] === 302);
$row = $pdo->query("SELECT id, username, name, password_hash FROM admins WHERE username='tempadmin'")->fetch();
check('Baris admin baru tersimpan', (bool) $row);
check('password_hash ter-hash (bukan plaintext)', $row && password_verify('passwordrahasia123', $row['password_hash']));
check('Kolom name terisi (NOT NULL terpenuhi)', $row && $row['name'] === 'Admin Uji');

// 7. Login memakai akun hasil setup
@unlink($cookieFile);
$r = http('GET', $admin . '/login.php');
$csrf = csrfFrom($r['body']);
$r = http('POST', $admin . '/login.php', [
    'csrf_token' => $csrf, 'username' => 'tempadmin', 'password' => 'passwordrahasia123',
]);
check('Login memakai akun hasil setup', $r['status'] === 302 && str_contains($r['headers'], 'dashboard.php'));

// 8. Setup kedua ditutup setelah ada admin
$r = http('POST', $admin . '/setup-admin.php', [
    'csrf_token' => $csrf, 'username' => 'kedua', 'name' => 'Kedua', 'password' => 'passwordrahasia123',
]);
$count = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
check('Setup ditutup setelah ada admin (hanya 1 akun)', $count === 1, 'jumlah=' . $count);

// 9. Pulihkan baris admin semula
$pdo->exec('DELETE FROM admins');
foreach ($admins as $adminRow) {
    $stmt = $pdo->prepare('INSERT INTO admins (id, username, password_hash, name) VALUES (:id, :u, :p, :n)');
    $stmt->execute([
        'id' => $adminRow['id'], 'u' => $adminRow['username'],
        'p' => $adminRow['password_hash'], 'n' => $adminRow['name'],
    ]);
}
$restored = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
check('Baris admin asli dipulihkan', $restored === count($admins), "pulih={$restored} asli=" . count($admins));

// 10. Pastikan login lama masih berfungsi persis seperti semula
@unlink($cookieFile);
$cookieFile = tempnam(sys_get_temp_dir(), 'sy_setup2');
$r = http('GET', $admin . '/login.php');
$csrf = csrfFrom($r['body']);
$r = http('POST', $admin . '/login.php', ['csrf_token' => $csrf, 'username' => 'admin', 'password' => 'password']);
check('Login admin lama tetap berfungsi setelah pemulihan',
    $r['status'] === 302 && str_contains($r['headers'], 'dashboard.php'));

echo "\n=== Ringkasan: {$passCount} lulus, {$failCount} gagal ===\n";
@unlink($cookieFile);
exit($failCount > 0 ? 1 : 0);
