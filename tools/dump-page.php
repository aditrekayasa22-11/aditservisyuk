<?php
declare(strict_types=1);
/** Dump HTML halaman admin yang sudah login untuk inspeksi. */
$base = rtrim((string) ($argv[1] ?? 'http://127.0.0.1:8080/ServisYuk'), '/');
$page = (string) ($argv[2] ?? 'servis.php');
$admin = $base . '/backend/admin';
$jar = tempnam(sys_get_temp_dir(), 'sy_dump');

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
    return ['status' => $status, 'body' => substr($raw, $size)];
}

$r = http('GET', $admin . '/login.php');
preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $r['body'], $m);
http('POST', $admin . '/login.php', ['csrf_token' => $m[1] ?? '', 'username' => 'admin', 'password' => 'password']);

$r = http('GET', $admin . '/' . $page);
echo "GET {$page}: status {$r['status']}, panjang " . strlen($r['body']) . "\n";
echo "Jumlah <option: " . substr_count($r['body'], '<option') . "\n";
preg_match_all('/<option[^>]*>\s*([^<]*)/', $r['body'], $all);
echo "Teks opsi: " . implode(' | ', array_map('trim', $all[1])) . "\n";
echo "Ada fatal/notice: " . (str_contains($r['body'], 'Fatal error') || str_contains($r['body'], 'Warning:') || str_contains($r['body'], 'Notice:') ? 'YA' : 'TIDAK') . "\n";
file_put_contents(sys_get_temp_dir() . '/dump_' . basename($page, '.php') . '.html', $r['body']);
echo "Disimpan ke " . sys_get_temp_dir() . '/dump_' . basename($page, '.php') . ".html\n";
@unlink($jar);
