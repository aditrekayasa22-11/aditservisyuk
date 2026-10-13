<?php
declare(strict_types=1);

/**
 * Lookup status servis kendaraan (publik).
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

    $statement = $pdo->prepare(
        'SELECT sr.id, sr.description, sr.status, sr.service_date, sr.cost,
                st.name AS service_name,
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
