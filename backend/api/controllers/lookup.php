<?php
declare(strict_types=1);

/**
 * Lookup status servis kendaraan (publik).
 * Jika kendaraan dilindungi PIN, data riwayat hanya keluar setelah
 * parameter `pin` benar — sama seperti halaman cek-status pelanggan.
 */
function lookup_public(array $params): array
{
    $plate = strtoupper(trim((string) ($params['plate'] ?? '')));
    if ($plate === '') {
        api_fail('Masukkan nomor polisi kendaraan Anda.', 422, 'validation');
    }

    $pdo = db();
    $statement = $pdo->prepare(
        'SELECT v.id, v.plate_number, v.brand, v.model, v.year, c.name AS customer_name
         FROM vehicles v JOIN customers c ON c.id = v.customer_id
         WHERE REPLACE(UPPER(v.plate_number), " ", "") = REPLACE(UPPER(:plate), " ", "")
         LIMIT 1'
    );
    $statement->execute(['plate' => $plate]);
    $vehicle = $statement->fetch();

    if (!$vehicle) {
        api_fail('Nomor polisi ' . $plate . ' belum terdaftar. Periksa kembali penulisan atau hubungi bengkel kami.', 404, 'vehicle_not_found');
    }

    // Proteksi PIN: riwayat tidak boleh bocor hanya karena orang mengetahui nomor polisi.
    if (vehicle_has_pin($pdo, (int) $vehicle['id'])) {
        if (!pin_attempt_allowed($plate)) {
            api_fail('Terlalu banyak percobaan PIN yang gagal. Muat ulang halaman lalu coba kembali.', 429, 'pin_rate_limited');
        }
        $pin = (string) ($params['pin'] ?? '');
        if (!verify_vehicle_pin($pdo, (int) $vehicle['id'], $pin)) {
            pin_attempt_failed($plate);
            api_fail('Kendaraan dilindungi PIN. Sertakan parameter pin yang Anda terima saat servis.', 401, 'pin_required');
        }
        pin_attempt_reset($plate);
    }

    $statement = $pdo->prepare(
        'SELECT sr.id, sr.description, sr.status, sr.service_date, sr.cost,
                COALESCE((SELECT GROUP_CONCAT(st_multi.name ORDER BY st_multi.name SEPARATOR ", ")
                          FROM service_record_types srt_multi
                          JOIN service_types st_multi ON st_multi.id = srt_multi.service_type_id
                          WHERE srt_multi.service_record_id = sr.id), st.name) AS service_name,
                p.status AS payment_status
         FROM service_records sr
         LEFT JOIN service_types st ON st.id = sr.service_type_id
         LEFT JOIN payments p ON p.service_record_id = sr.id
         WHERE sr.vehicle_id = :vehicle_id
         ORDER BY sr.service_date DESC, sr.id DESC'
    );
    $statement->execute(['vehicle_id' => $vehicle['id']]);

    return [
        'vehicle' => $vehicle,
        'services' => $statement->fetchAll(),
    ];
}
