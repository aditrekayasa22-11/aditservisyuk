<?php
declare(strict_types=1);

function inventory_list_items(array $params): array
{
    $q = trim((string) ($params['q'] ?? ''));

    $sql = 'SELECT id, name, sku, quantity, unit, purchase_price, updated_at FROM inventory';
    $bindings = [];
    if ($q !== '') {
        // Placeholder unik: prepared statement native tidak boleh memakai
        // parameter bernama yang sama lebih dari sekali.
        $sql .= ' WHERE name LIKE :q_name OR sku LIKE :q_sku';
        $bindings['q_name'] = '%' . $q . '%';
        $bindings['q_sku'] = '%' . $q . '%';
    }
    $sql .= ' ORDER BY name';

    $statement = db()->prepare($sql);
    $statement->execute($bindings);

    return $statement->fetchAll();
}

function inventory_create(array $params): array
{
    $name = api_str('name');
    require_text($name !== '', 'Nama barang wajib diisi.');

    $statement = db()->prepare('INSERT INTO inventory (name, sku, quantity, unit, purchase_price) VALUES (:name, :sku, :quantity, :unit, :purchase_price)');
    $statement->execute([
        'name' => $name,
        'sku' => api_nullable('sku'),
        'quantity' => max(0, api_int('quantity')),
        'unit' => api_str('unit', 'pcs') ?: 'pcs',
        'purchase_price' => max(0, api_float('purchase_price')),
    ]);

    return ['id' => (int) db()->lastInsertId(), 'created' => true];
}

function inventory_read(array $params): array
{
    $statement = db()->prepare('SELECT id, name, sku, quantity, unit, purchase_price, updated_at FROM inventory WHERE id = :id');
    $statement->execute(['id' => (int) ($params['id'] ?? 0)]);

    return record_or_404($statement->fetch(), 'Barang tidak ditemukan.');
}

function inventory_update(array $params): array
{
    $pdo = db();
    $id = (int) ($params['id'] ?? 0);
    assert_exists($pdo, 'inventory', $id, 'Barang tidak ditemukan.');

    $fields = api_body();

    if (array_key_exists('quantity', $fields) && count($fields) === 1) {
        // Percepat pembaruan stok dari tabel.
        $statement = $pdo->prepare('UPDATE inventory SET quantity = :quantity WHERE id = :id');
        $statement->execute(['quantity' => max(0, api_int('quantity')), 'id' => $id]);
        return ['id' => $id, 'updated' => true];
    }

    $name = api_str('name');
    require_text($name !== '', 'Nama barang wajib diisi.');

    $statement = $pdo->prepare('UPDATE inventory SET name = :name, sku = :sku, quantity = :quantity, unit = :unit, purchase_price = :purchase_price WHERE id = :id');
    $statement->execute([
        'name' => $name,
        'sku' => api_nullable('sku'),
        'quantity' => max(0, api_int('quantity')),
        'unit' => api_str('unit', 'pcs') ?: 'pcs',
        'purchase_price' => max(0, api_float('purchase_price')),
        'id' => $id,
    ]);

    return ['id' => $id, 'updated' => true];
}

function inventory_delete(array $params): array
{
    $pdo = db();
    $id = (int) ($params['id'] ?? 0);
    assert_exists($pdo, 'inventory', $id, 'Barang tidak ditemukan.');

    $statement = $pdo->prepare('DELETE FROM inventory WHERE id = :id');
    $statement->execute(['id' => $id]);

    return ['id' => $id, 'deleted' => true];
}
