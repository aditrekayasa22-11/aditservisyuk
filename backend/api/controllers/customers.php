<?php
declare(strict_types=1);

function customers_list_items(array $params): array
{
    $pdo = db();
    $q = trim((string) ($params['q'] ?? ''));

    $sql = 'SELECT id, name, phone, email, created_at FROM customers';
    $bindings = [];
    if ($q !== '') {
        $sql .= ' WHERE name LIKE :q OR phone LIKE :q OR email LIKE :q';
        $bindings['q'] = '%' . $q . '%';
    }
    $sql .= ' ORDER BY id DESC';

    $statement = $pdo->prepare($sql);
    $statement->execute($bindings);
    $customers = $statement->fetchAll();

    $vehicleStatement = $pdo->prepare(
        'SELECT id, plate_number, brand, model, year FROM vehicles WHERE customer_id = :customer_id ORDER BY plate_number'
    );
    foreach ($customers as $customer) {
        $vehicleStatement->execute(['customer_id' => $customer['id']]);
        $customer['vehicles'] = $vehicleStatement->fetchAll();
    }

    return $customers;
}

function customers_create(array $params): array
{
    $name = api_str('name');
    require_text($name !== '', 'Nama pelanggan wajib diisi.');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('INSERT INTO customers (name, phone, email) VALUES (:name, :phone, :email)');
        $statement->execute([
            'name' => $name,
            'phone' => api_nullable('phone'),
            'email' => api_nullable('email'),
        ]);
        $customerId = (int) $pdo->lastInsertId();

        // Kendaraan pertama boleh dibuat langsung bersama pelanggan.
        $plate = strtoupper(api_str('plate_number'));
        if ($plate !== '') {
            $statement = $pdo->prepare('INSERT INTO vehicles (customer_id, plate_number, brand, model, year) VALUES (:customer_id, :plate, :brand, :model, :year)');
            $statement->execute([
                'customer_id' => $customerId,
                'plate' => $plate,
                'brand' => api_nullable('brand'),
                'model' => api_nullable('model'),
                'year' => api_int('year') ?: null,
            ]);
        }
        $pdo->commit();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        api_fail_db($exception);
    }

    return ['id' => $customerId, 'created' => true];
}

function customers_read(array $params): array
{
    $pdo = db();
    $statement = $pdo->prepare('SELECT id, name, phone, email, created_at FROM customers WHERE id = :id');
    $statement->execute(['id' => (int) ($params['id'] ?? 0)]);
    $customer = record_or_404($statement->fetch(), 'Pelanggan tidak ditemukan.');

    $vehicleStatement = $pdo->prepare('SELECT id, plate_number, brand, model, year FROM vehicles WHERE customer_id = :customer_id ORDER BY plate_number');
    $vehicleStatement->execute(['customer_id' => $customer['id']]);
    $customer['vehicles'] = $vehicleStatement->fetchAll();

    return $customer;
}

function customers_update(array $params): array
{
    $pdo = db();
    $id = (int) ($params['id'] ?? 0);
    assert_exists($pdo, 'customers', $id, 'Pelanggan tidak ditemukan.');

    $name = api_str('name');
    require_text($name !== '', 'Nama pelanggan wajib diisi.');

    $statement = $pdo->prepare('UPDATE customers SET name = :name, phone = :phone, email = :email WHERE id = :id');
    $statement->execute([
        'name' => $name,
        'phone' => api_nullable('phone'),
        'email' => api_nullable('email'),
        'id' => $id,
    ]);

    return ['id' => $id, 'updated' => true];
}

function customers_delete(array $params): array
{
    $pdo = db();
    $id = (int) ($params['id'] ?? 0);
    assert_exists($pdo, 'customers', $id, 'Pelanggan tidak ditemukan.');

    $statement = $pdo->prepare('DELETE FROM customers WHERE id = :id');
    $statement->execute(['id' => $id]);

    return ['id' => $id, 'deleted' => true];
}
