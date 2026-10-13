<?php
declare(strict_types=1);

const PAYMENT_METHODS = ['Tunai', 'Transfer', 'Kartu', 'QRIS'];
const PAYMENT_STATUSES = ['Belum Dibayar', 'Lunas'];

function payments_list_items(array $params): array
{
    $pdo = db();
    $status = (string) ($params['status'] ?? '');

    $sql = 'SELECT p.id, p.service_record_id, p.amount, p.method, p.status, p.paid_at, p.created_at,
                   sr.service_date, v.plate_number, c.name AS customer_name
            FROM payments p
            JOIN service_records sr ON sr.id = p.service_record_id
            JOIN vehicles v ON v.id = sr.vehicle_id
            JOIN customers c ON c.id = v.customer_id';
    $bindings = [];
    if (in_array($status, PAYMENT_STATUSES, true)) {
        $sql .= ' WHERE p.status = :status';
        $bindings['status'] = $status;
    }
    $sql .= ' ORDER BY p.id DESC';

    $statement = $pdo->prepare($sql);
    $statement->execute($bindings);

    return $statement->fetchAll();
}

/**
 * Simpan atau perbarui pembayaran untuk satu catatan servis (unique key).
 */
function payments_upsert(array $params): array
{
    $pdo = db();

    $serviceRecordId = api_int('service_record_id');
    require_text($serviceRecordId > 0, 'Pilih catatan servis yang valid.');
    assert_exists($pdo, 'service_records', $serviceRecordId, 'Catatan servis tidak ditemukan.');

    $method = valid_enum(api_nullable('method') ?? 'Tunai', PAYMENT_METHODS, 'Metode pembayaran tidak valid.');
    $status = valid_enum(api_nullable('status') ?? 'Belum Dibayar', PAYMENT_STATUSES, 'Status pembayaran tidak valid.');

    $statement = $pdo->prepare(
        'INSERT INTO payments (service_record_id, amount, method, status, paid_at)
         VALUES (:service_record_id, :amount, :method, :status, :paid_at)
         ON DUPLICATE KEY UPDATE amount = VALUES(amount), method = VALUES(method), status = VALUES(status), paid_at = VALUES(paid_at)'
    );
    $statement->execute([
        'service_record_id' => $serviceRecordId,
        'amount' => max(0, api_float('amount')),
        'method' => $method,
        'status' => $status,
        'paid_at' => $status === 'Lunas' ? date('Y-m-d H:i:s') : null,
    ]);

    $select = $pdo->prepare('SELECT id FROM payments WHERE service_record_id = :service_record_id');
    $select->execute(['service_record_id' => $serviceRecordId]);

    return ['id' => (int) $select->fetchColumn(), 'service_record_id' => $serviceRecordId, 'saved' => true];
}

function payments_delete(array $params): array
{
    $pdo = db();
    $id = (int) ($params['id'] ?? 0);
    assert_exists($pdo, 'payments', $id, 'Pembayaran tidak ditemukan.');

    $statement = $pdo->prepare('DELETE FROM payments WHERE id = :id');
    $statement->execute(['id' => $id]);

    return ['id' => $id, 'deleted' => true];
}
