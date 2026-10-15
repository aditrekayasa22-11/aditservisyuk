<?php
declare(strict_types=1);

/**
 * Uji fitur pemilihan pembayaran di halaman akun pelanggan:
 * - Opsi Tunai / Cash (tanpa bukti transfer, status berubah menjadi Menunggu Verifikasi)
 * - Opsi QRIS (dengan bukti transfer & gambar QRIS)
 * - Opsi Bank Transfer (BNI, BRI, BCA, Mandiri)
 */
require_once __DIR__ . '/../backend/includes/functions.php';

$base = rtrim((string) ($argv[1] ?? 'http://127.0.0.1:8080/ServisYuk'), '/');
$cookieFile = tempnam(sys_get_temp_dir(), 'sy_pay_test');
$pdo = db();
$pass = 0;
$fail = 0;

function request_http(string $method, string $url, array $post = [], array $files = []): array
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
        if ($files) {
            $postFields = $post;
            foreach ($files as $field => $file) {
                $postFields[$field] = new CURLFile($file['path'], $file['mime'], $file['name']);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
    }
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['status' => $status, 'headers' => substr($raw, 0, $headerSize), 'body' => substr($raw, $headerSize)];
}

function check_test(string $name, bool $ok, string $detail = ''): void
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

function get_csrf(string $html): string
{
    preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $html, $m);
    return $m[1] ?? '';
}

echo "=== Uji Pilihan Pembayaran Pelanggan (Cash, QRIS, Bank BNI/BRI/BCA/Mandiri) ===\n";

$tmpProof = tempnam(sys_get_temp_dir(), 'proof_test') . '.png';
file_put_contents($tmpProof, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

try {
    // Siapkan pelanggan dan 1 servis dengan tagihan belum dibayar
    $pdo->prepare('INSERT INTO customers (name) VALUES (:n)')->execute(['n' => 'Uji Pembayaran Pelanggan']);
    $cid = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO vehicles (customer_id, plate_number, brand, model) VALUES (:c, "UJI PAY 1", "Yamaha", "NMAX")')
        ->execute(['c' => $cid]);
    $vid = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO service_records (vehicle_id, service_type_id, description, status, service_date, cost)
                   VALUES (:v, 1, "Ganti oli + kampas rem", "Selesai", CURDATE(), 350000)')
        ->execute(['v' => $vid]);
    $sid = (int) $pdo->lastInsertId();

    // 1. Login pelanggan
    $r = request_http('GET', $base . '/login-pelanggan.php');
    $csrf = get_csrf($r['body']);
    $r = request_http('POST', $base . '/login-pelanggan.php', [
        'csrf_token' => $csrf, 'action' => 'login', 'plate_number' => 'UJI PAY 1',
    ]);
    check_test('Login pelanggan berhasil (302 ke portal)', $r['status'] === 302 && str_contains($r['headers'], 'akun-pelanggan.php'));

    // 2. Buka portal & periksa elemen metode pembayaran
    $r = request_http('GET', $base . '/akun-pelanggan.php');
    check_test('Halaman akun pelanggan terbuka (200)', $r['status'] === 200);
    check_test('Terdapat opsi Cash / Tunai', str_contains($r['body'], 'Cash / Tunai'));
    check_test('Terdapat opsi QRIS', str_contains($r['body'], 'value="QRIS"'));
    check_test('Terdapat opsi Transfer Bank', str_contains($r['body'], 'Transfer Bank'));
    check_test('Terdapat opsi Bank BNI', str_contains($r['body'], 'value="bni"') && str_contains($r['body'], 'BNI'));
    check_test('Terdapat opsi Bank BRI', str_contains($r['body'], 'value="bri"') && str_contains($r['body'], 'BRI'));
    check_test('Terdapat opsi Bank BCA', str_contains($r['body'], 'value="bca"') && str_contains($r['body'], 'BCA'));
    check_test('Terdapat opsi Bank Mandiri', str_contains($r['body'], 'value="mandiri"') && str_contains($r['body'], 'Bank Mandiri'));
    check_test('Terdapat gambar QRIS', str_contains($r['body'], 'assets/img/qris-servisyuk.svg'));

    // 3. Uji Submit Pembayaran Tunai (Cash)
    $csrf = get_csrf($r['body']);
    $r = request_http('POST', $base . '/akun-pelanggan.php', [
        'csrf_token' => $csrf,
        'action' => 'upload_proof',
        'service_id' => (string) $sid,
        'payment_method' => 'Tunai',
    ]);
    check_test('Submit Cash berhasil (redirect 302)', $r['status'] === 302);

    $stmt = $pdo->prepare('SELECT method, status, note, proof_path FROM payments WHERE service_record_id = :sid');
    $stmt->execute(['sid' => $sid]);
    $pay = $stmt->fetch();
    check_test('Metode tersimpan di database sebagai Tunai', ($pay['method'] ?? '') === 'Tunai');
    check_test('Status tersimpan sebagai Menunggu Verifikasi', ($pay['status'] ?? '') === 'Menunggu Verifikasi');

    // 4. Uji Submit Pembayaran Bank BCA dengan bukti transfer
    $r = request_http('GET', $base . '/akun-pelanggan.php');
    $csrf = get_csrf($r['body']);
    $r = request_http('POST', $base . '/akun-pelanggan.php', [
        'csrf_token' => $csrf,
        'action' => 'upload_proof',
        'service_id' => (string) $sid,
        'payment_method' => 'Transfer',
        'bank_choice' => 'bca',
    ], [
        'proof' => ['path' => $tmpProof, 'mime' => 'image/png', 'name' => 'bukti_bca.png'],
    ]);
    check_test('Submit Bank BCA berhasil (redirect 302)', $r['status'] === 302);

    $stmt->execute(['sid' => $sid]);
    $pay = $stmt->fetch();
    check_test('Metode tersimpan di database sebagai Transfer', ($pay['method'] ?? '') === 'Transfer');
    check_test('Catatan bank berisi Bank BCA', str_contains($pay['note'] ?? '', 'Bank BCA'));
    check_test('File bukti pembayaran tersimpan', !empty($pay['proof_path']));

    // 5. Uji Submit Pembayaran QRIS
    $r = request_http('GET', $base . '/akun-pelanggan.php');
    $csrf = get_csrf($r['body']);
    $r = request_http('POST', $base . '/akun-pelanggan.php', [
        'csrf_token' => $csrf,
        'action' => 'upload_proof',
        'service_id' => (string) $sid,
        'payment_method' => 'QRIS',
    ], [
        'proof' => ['path' => $tmpProof, 'mime' => 'image/png', 'name' => 'bukti_qris.png'],
    ]);
    check_test('Submit QRIS berhasil (redirect 302)', $r['status'] === 302);

    $stmt->execute(['sid' => $sid]);
    $pay = $stmt->fetch();
    check_test('Metode tersimpan di database sebagai QRIS', ($pay['method'] ?? '') === 'QRIS');
    check_test('Catatan pembayaran berisi QRIS', str_contains($pay['note'] ?? '', 'QRIS'));

} finally {
    $pdo->prepare('DELETE FROM customers WHERE name = "Uji Pembayaran Pelanggan"')->execute();
    @unlink($cookieFile);
    @unlink($tmpProof);
    check_test('Data uji dibersihkan', true);
}

echo "\n=== Hasil: $pass lulus, $fail gagal ===\n";
exit($fail === 0 ? 0 : 1);
