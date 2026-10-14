<?php
declare(strict_types=1);
/** Debug status respons POST admin (kode & body). */
$base = rtrim((string) ($argv[1] ?? 'http://127.0.0.1:8080/ServisYuk'), '/');
$admin = $base . '/backend/admin';
$jar = tempnam(sys_get_temp_dir(), 'sy_dbg2');

function http(string $method, string $url, array $post = []): array
{
    global $jar;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 15, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
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

// Login
$r = http('GET', $admin . '/login.php');
$csrf = csrfFrom($r['body']);
http('POST', $admin . '/login.php', ['csrf_token' => $csrf, 'username' => 'admin', 'password' => 'password']);

// 1) POST dashboard TANPA csrf
$r = http('POST', $admin . '/dashboard.php', ['action' => 'update_service_status', 'service_id' => '1', 'status' => 'Selesai']);
echo "1) POST dashboard tanpa csrf -> status {$r['status']} | body: " . trim($r['body']) . "\n";

// 2) POST servis.php TANPA csrf
$r = http('POST', $admin . '/servis.php', ['action' => 'save_service']);
echo "2) POST servis tanpa csrf -> status {$r['status']} | body: " . trim($r['body']) . "\n";

// 3) GET servis.php -> status & csrf
$r = http('GET', $admin . '/servis.php');
echo "3) GET servis -> status {$r['status']} | csrf " . (csrfFrom($r['body']) !== '' ? 'ADA' : 'TIDAK ADA')
    . " | opsi kendaraan: " . substr_count($r['body'], '<option value="') . " | panjang " . strlen($r['body']) . "\n";
preg_match('/<option value="\d+"[^>]*>\s*([^<]*)/', $r['body'], $m);
echo "   opsi pertama: " . var_export(trim($m[1] ?? ''), true) . "\n";
echo "   ada 'UJI ADM 01': " . (str_contains($r['body'], 'UJI ADM 01') ? 'YA' : 'TIDAK') . "\n";

// 4) GET dashboard -> ada form antrean?
$r = http('GET', $admin . '/dashboard.php');
echo "4) GET dashboard -> status {$r['status']} | csrf " . (csrfFrom($r['body']) !== '' ? 'ADA' : 'TIDAK ADA')
    . " | 'update_service_status' " . (str_contains($r['body'], 'update_service_status') ? 'ADA' : 'TIDAK') . "\n";

// 5) POST dashboard dengan csrf diambil dari servis.php (token sesi sama)
$csrf2 = csrfFrom($r['body']) ?: $csrf;
$r = http('POST', $admin . '/dashboard.php', ['csrf_token' => $csrf2, 'action' => 'update_service_status', 'service_id' => '999999', 'status' => 'Selesai']);
echo "5) POST dashboard + csrf -> status {$r['status']} | body: " . substr(trim($r['body']), 0, 120) . "\n";

@unlink($jar);
