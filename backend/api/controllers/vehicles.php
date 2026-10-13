<?php
declare(strict_types=1);

function vehicles_list_items(array $params): array
{
    $pdo = db();
    $customerId = (int) ($params['customer_id'] ?? 0);
    $q = strtoupper(trim((string) ($params['q'] ?? '')));

    $sql = 'SELECT v.id, v.customer_id, v.plate_number, v.brand, v.model, v.year, v.created_at,
                   c.name AS customer_name
            FROM vehicles v JOIN customers c ON c.id = v.customer_id';
    $where = [];
    $bindings = [];
    if ($customerId > 0) {
        $where[] = 'v.customer_id = :customer_id';
        $bindings['customer_id'] = $customerId;
    }
    if ($q !== '') {
        $where[] = 'UPPER(v.plate_number) LIKE :q';
        $bindings['q'] = '%' . $q . '%';
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY v.plate_number';

    $statement = $pdo->prepare($sql);
    $statement->execute($bindings);

    return $statement->fetchAll();
}

function vehicles_create(array $params): array
{
    $plate = strtoupper(api_str('plate_number'));
    require_text($plate !== '', 'Nomor polisi wajib diisi.');

    $pdo = db();
    $customerId = api_int('customer_id');
    assert_exists($pdo, 'customers', $customerId, 'Pilih pelanggan yang valid.');

    $statement = $pdo->prepare('INSERT INTO vehicles (customer_id, plate_number, brand, model, year) VALUES (:customer_id, :plate, :brand, :model, :year)');
    $statement->execute([
        'customer_id' => $customerId,
        'plate' => $plate,
        'brand' => api_nullable('brand'),
        'model' => api_nullable('model'),
        'year' => api_int('year') ?: null,
    ]);

    return ['id' => (int) $pdo->lastInsertId(), 'created' => true];
}

function vehicles_read(array $params): array
{
    $statement = db()->prepare(
        'SELECT v.id, v.customer_id, v.plate_number, v.brand, v.model, v.year, v.created_at, c.name AS customer_name
         FROM vehicles v JOIN customers c ON c.id = v.customer_id WHERE v.id = :id'
    );
    $statement->execute(['id' => (int) ($params['id'] ?? 0)]);

    return record_or_404($statement->fetch(), 'Kendaraan tidak ditemukan.');
}

function vehicles_update(array $params): array
{
    $pdo = db();
    $id = (int) ($params['id'] ?? 0);
    assert_exists($pdo, 'vehicles', $id, 'Kendaraan tidak ditemukan.');

    $plate = strtoupper(api_str('plate_number'));
    require_text($plate !== '', 'Nomor polisi wajib diisi.');

    $customerId = api_int('customer_id');
    assert_exists($pdo, 'customers', $customerId, 'Pilih pelanggan yang valid.');

    $statement = $pdo->prepare('UPDATE vehicles SET customer_id = :customer_id, plate_number = :plate, brand = :brand, model = :model, year = :year WHERE id = :id');
    $statement->execute([
        'customer_id' => $customerId,
        'plate' => $plate,
        'brand' => api_nullable('brand'),
        'model' => api_nullable('model'),
        'year' => api_int('year') ?: null,
        'id' => $id,
    ]);

    return ['id' => $id, 'updated' => true];
}

function vehicles_delete(array $params): array
{
    $pdo = db();
    $id = (int) ($params['id'] ?? 0);
    assert_exists($pdo, 'vehicles', $id, 'Kendaraan tidak ditemukan.');

    $statement = $pdo->prepare('DELETE FROM vehicles WHERE id = :id');
    $statement->execute(['id' => $id]);

    return ['id' => $id, 'deleted' => true];
}
