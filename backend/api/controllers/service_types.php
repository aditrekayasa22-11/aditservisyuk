<?php
declare(strict_types=1);

function service_types_list_items(array $params): array
{
    return db()->query('SELECT id, name, description, base_price, created_at FROM service_types ORDER BY name')->fetchAll();
}

function service_types_create(array $params): array
{
    $name = api_str('name');
    require_text($name !== '', 'Nama jenis servis wajib diisi.');

    $statement = db()->prepare('INSERT INTO service_types (name, description, base_price) VALUES (:name, :description, :base_price)');
    $statement->execute([
        'name' => $name,
        'description' => api_nullable('description'),
        'base_price' => max(0, api_float('base_price')),
    ]);

    return ['id' => (int) db()->lastInsertId(), 'created' => true];
}

function service_types_read(array $params): array
{
    $statement = db()->prepare('SELECT id, name, description, base_price, created_at FROM service_types WHERE id = :id');
    $statement->execute(['id' => (int) ($params['id'] ?? 0)]);

    return record_or_404($statement->fetch(), 'Jenis servis tidak ditemukan.');
}

function service_types_update(array $params): array
{
    $pdo = db();
    $id = (int) ($params['id'] ?? 0);
    assert_exists($pdo, 'service_types', $id, 'Jenis servis tidak ditemukan.');

    $name = api_str('name');
    require_text($name !== '', 'Nama jenis servis wajib diisi.');

    $statement = $pdo->prepare('UPDATE service_types SET name = :name, description = :description, base_price = :base_price WHERE id = :id');
    $statement->execute([
        'name' => $name,
        'description' => api_nullable('description'),
        'base_price' => max(0, api_float('base_price')),
        'id' => $id,
    ]);

    return ['id' => $id, 'updated' => true];
}

function service_types_delete(array $params): array
{
    $pdo = db();
    $id = (int) ($params['id'] ?? 0);
    assert_exists($pdo, 'service_types', $id, 'Jenis servis tidak ditemukan.');

    $statement = $pdo->prepare('DELETE FROM service_types WHERE id = :id');
    $statement->execute(['id' => $id]);

    return ['id' => $id, 'deleted' => true];
}
