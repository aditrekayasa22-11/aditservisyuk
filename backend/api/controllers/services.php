<?php
declare(strict_types=1);

const SERVICE_STATUSES = ['Menunggu', 'Dikerjakan', 'Selesai', 'Dibatalkan'];

function services_list_items(array $params): array
{
    $pdo = db();
    $vehicleId = (int) ($params['vehicle_id'] ?? 0);
    $status = (string) ($params['status'] ?? '');

    $sql = 'SELECT sr.id, sr.vehicle_id, sr.service_type_id, sr.description, sr.status, sr.service_date, sr.cost, sr.created_at,
                   v.plate_number, c.name AS customer_name, st.name AS service_name,
                   p.status AS payment_status, p.amount AS payment_amount, p.method AS payment_method
            FROM service_records sr
            JOIN vehicles v ON v.id = sr.vehicle_id
            JOIN customers c ON c.id = v.customer_id
            LEFT JOIN service_types st ON st.id = sr.service_type_id
            LEFT JOIN payments p ON p.service_record_id = sr.id';
    $where = [];
    $bindings = [];
    if ($vehicleId > 0) {
        $where[] = 'sr.vehicle_id = :vehicle_id';
        $bindings['vehicle_id'] = $vehicleId;
    }
    if (in_array($status, SERVICE_STATUSES, true)) {
        $where[] = 'sr.status = :status';
        $bindings['status'] = $status;
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY sr.service_date DESC, sr.id DESC';

    $statement = $pdo->prepare($sql);
    $statement->execute($bindings);

    return $statement->fetchAll();
}

function services_create(array $params): array
{
    $pdo = db();

    $vehicleId = api_int('vehicle_id');
    assert_exists($pdo, 'vehicles', $vehicleId, 'Pilih kendaraan yang valid.');

    $serviceTypeId = api_int('service_type_id');
    require_text($serviceTypeId > 0, 'Pilih jenis servis yang valid.');
    assert_exists($pdo, 'service_types', $serviceTypeId, 'Pilih jenis servis yang valid.');

    $status = valid_enum(api_nullable('status') ?? 'Menunggu', SERVICE_STATUSES, 'Status servis tidak valid.');
    $serviceDate = valid_date(api_nullable('service_date') ?? date('Y-m-d'), 'Tanggal servis tidak valid.');

    $statement = $pdo->prepare('INSERT INTO service_records (vehicle_id, service_type_id, description, status, service_date, cost) VALUES (:vehicle_id, :service_type_id, :description, :status, :service_date, :cost)');
    $statement->execute([
        'vehicle_id' => $vehicleId,
        'service_type_id' => $serviceTypeId,
        'description' => api_nullable('description'),
        'status' => $status,
        'service_date' => $serviceDate,
        'cost' => max(0, api_float('cost')),
    ]);

    return ['id' => (int) $pdo->lastInsertId(), 'created' => true];
}

function services_read(array $params): array
{
    $statement = db()->prepare(
        'SELECT sr.id, sr.vehicle_id, sr.service_type_id, sr.description, sr.status, sr.service_date, sr.cost, sr.created_at,
                v.plate_number, c.name AS customer_name, st.name AS service_name
         FROM service_records sr
         JOIN vehicles v ON v.id = sr.vehicle_id
         JOIN customers c ON c.id = v.customer_id
         LEFT JOIN service_types st ON st.id = sr.service_type_id
         WHERE sr.id = :id'
    );
    $statement->execute(['id' => (int) ($params['id'] ?? 0)]);

    return record_or_404($statement->fetch(), 'Catatan servis tidak ditemukan.');
}

function services_update(array $params): array
{
    $pdo = db();
    $id = (int) ($params['id'] ?? 0);
    assert_exists($pdo, 'service_records', $id, 'Catatan servis tidak ditemukan.');

    $fields = api_body();
    $updates = [];
    $bindings = ['id' => $id];

    if (array_key_exists('status', $fields)) {
        $status = valid_enum(api_nullable('status'), SERVICE_STATUSES, 'Status servis tidak valid.');
        require_text($status !== null, 'Status servis tidak valid.');
        $updates[] = 'status = :status';
        $bindings['status'] = $status;
    }
    if (array_key_exists('description', $fields)) {
        $updates[] = 'description = :description';
        $bindings['description'] = api_nullable('description');
    }
    if (array_key_exists('service_date', $fields)) {
        $updates[] = 'service_date = :service_date';
        $bindings['service_date'] = valid_date(api_nullable('service_date'), 'Tanggal servis tidak valid.');
    }
    if (array_key_exists('cost', $fields)) {
        $updates[] = 'cost = :cost';
        $bindings['cost'] = max(0, api_float('cost'));
    }
    if (array_key_exists('service_type_id', $fields)) {
        $serviceTypeId = api_int('service_type_id');
        assert_exists($pdo, 'service_types', $serviceTypeId, 'Pilih jenis servis yang valid.');
        $updates[] = 'service_type_id = :service_type_id';
        $bindings['service_type_id'] = $serviceTypeId;
    }

    require_text($updates !== [], 'Tidak ada perubahan yang dikirim.');

    $statement = $pdo->prepare('UPDATE service_records SET ' . implode(', ', $updates) . ' WHERE id = :id');
    $statement->execute($bindings);

    return ['id' => $id, 'updated' => true];
}

function services_delete(array $params): array
{
    $pdo = db();
    $id = (int) ($params['id'] ?? 0);
    assert_exists($pdo, 'service_records', $id, 'Catatan servis tidak ditemukan.');

    $statement = $pdo->prepare('DELETE FROM service_records WHERE id = :id');
    $statement->execute(['id' => $id]);

    return ['id' => $id, 'deleted' => true];
}
