<?php
declare(strict_types=1);
/** Debug singkat: status kode & keberadaan CSRF pada form admin. */
$base = rtrim((string) ($argv[1] ?? 'http://127.0.0.1:8080/ServisYuk'), '/');
$admin = $base . '/backend/admin';
$cookieFile = tempnam(sys_get_temp_dir(), 'sy_dbg');

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

$r = http('GET', $admin . '/login.php');
$csrf = csrfFrom($r['body']);
$r = http('POST', $admin . '/login.php', ['csrf_token' => $csrf, 'username' => 'admin', 'password' => 'password']);
echo "login status: {$r['status']}\n";

$r = http('GET', $admin . '/dashboard.php');
echo "dashboard GET: {$r['status']}, csrf found: " . (csrfFrom($r['body']) !== '' ? 'YA' : 'TIDAK') . "\n";
echo "  jumlah kemunculan csrf_token: " . substr_count($r['body'], 'name="csrf_token"') . "\n";
$csrf = csrfFrom($r['body']);
$r = http('POST', $admin . '/dashboard.php', [
    'csrf_token' => $csrf, 'action' => 'update_service_status',
    'service_id' => '999999', 'status' => 'Selesai',
]);
echo "dashboard POST (id tak ada): {$r['status']} | headers: " . str_replace("\r\n", ' | ', trim($r['headers'])) . "\n";

$r = http('POST', $admin . '/servis.php', ['action' => 'save_service']);
echo "POST tanpa CSRF: {$r['status']}\n";

// Cek apakah servis.php opsi vehicle punya spasi sebelum '>'
$r = http('GET', $admin . '/servis.php');
preg_match('/<option value="\d+"[^>]*>[^<]{0,40}/', $r['body'], $m);
echo "contoh opsi pertama: " . var_export($m[0] ?? null, true) . "\n";

@unlink($cookieFile);
