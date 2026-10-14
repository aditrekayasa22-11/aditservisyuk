<?php
declare(strict_types=1);
/**
 * Uji alur PIN akses pelanggan (pengujian lokal).
 * Data contoh dibuat lalu dihapus kembali supaya database tetap bersih.
 */
require_once __DIR__ . '/../backend/includes/functions.php';

$pdo = db();
$pass = 0;
$fail = 0;

function check(string $name, bool $ok): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  [OK]   $name\n"; }
    else { $fail++; echo "  [GAGAL] $name\n"; }
}

echo "=== Uji Alur PIN Pelanggan ===\n";

// Siapkan data contoh (nomor polisi unik untuk pengujian)
$plate = 'UJI PIN 901';
try {
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO customers (name, phone) VALUES (:n, :p)')
        ->execute(['n' => 'Pelanggan Uji PIN', 'p' => '0811111111']);
    $customerId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO vehicles (customer_id, plate_number, brand, model) VALUES (:c, :pl, :b, :m)')
        ->execute(['c' => $customerId, 'pl' => $plate, 'b' => 'Suzuki', 'm' => 'Ertiga']);
    $vehicleId = (int) $pdo->lastInsertId();
    $pdo->commit();

    // 1. Tanpa PIN -> kendaraan bisa dibuka langsung
    $vehicle = find_vehicle_by_plate($pdo, $plate);
    check('Kendaraan ditemukan lewat nomor polisi', $vehicle !== null);
    check('Tanpa PIN, kendaraan tidak dilindungi', !vehicle_has_pin($pdo, $vehicleId));

    // 2. Admin memasang PIN
    save_vehicle_pin($pdo, $vehicleId, '4829', null);
    check('Setelah dipasang PIN, kendaraan dilindungi', vehicle_has_pin($pdo, $vehicleId));

    // 3. Verifikasi PIN
    check('PIN benar diterima', verify_vehicle_pin($pdo, $vehicleId, '4829'));
    check('PIN salah ditolak', !verify_vehicle_pin($pdo, $vehicleId, '1111'));
    check('PIN berbukan angka ditolak', !verify_vehicle_pin($pdo, $vehicleId, 'abcd'));

    // 4. PIN yang sama diganti, PIN lama tidak berlaku
    save_vehicle_pin($pdo, $vehicleId, '7712', null);
    check('PIN lama tidak berlaku setelah diganti', !verify_vehicle_pin($pdo, $vehicleId, '4829'));
    check('PIN baru berlaku', verify_vehicle_pin($pdo, $vehicleId, '7712'));

    // 5. PIN kedaluwarsa
    save_vehicle_pin($pdo, $vehicleId, '5566', date('Y-m-d', strtotime('-1 day')));
    check('PIN kedaluwarsa tidak diterima', !verify_vehicle_pin($pdo, $vehicleId, '5566'));
    check('PIN kedaluwarsa dianggap tidak aktif', !vehicle_has_pin($pdo, $vehicleId));

    // 6. Riwayat kendaraan terisi dengan benar
    $pdo->prepare('INSERT INTO service_records (vehicle_id, service_type_id, description, status, service_date, cost)
                   VALUES (:v, 1, "rem dek bunyi", "Selesai", CURDATE(), 250000)')
        ->execute(['v' => $vehicleId]);
    $services = vehicle_services($pdo, $vehicleId);
    check('Riwayat servis menampilkan catatan baru', count($services) >= 1 && $services[0]['description'] === 'rem dek bunyi');
} finally {
    // Bersihkan data uji
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $pdo->prepare('DELETE FROM customers WHERE name = :n')->execute(['n' => 'Pelanggan Uji PIN']);
    check('Data uji berhasil dibersihkan', true);
}

echo "\n=== Ringkasan: {$pass} lulus, {$fail} gagal ===\n";
exit($fail > 0 ? 1 : 0);
