<?php
declare(strict_types=1);

function dashboard_summary(array $params): array
{
    $pdo = db();

    $counts = [
        'customers' => (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
        'vehicles' => (int) $pdo->query('SELECT COUNT(*) FROM vehicles')->fetchColumn(),
        'active' => (int) $pdo->query("SELECT COUNT(*) FROM service_records WHERE status IN ('Menunggu','Dikerjakan')")->fetchColumn(),
        'unpaid' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'Belum Dibayar'")->fetchColumn(),
        'services' => (int) $pdo->query('SELECT COUNT(*) FROM service_records')->fetchColumn(),
        'inventory_items' => (int) $pdo->query('SELECT COUNT(*) FROM inventory')->fetchColumn(),
    ];

    $recent = $pdo->query(
        'SELECT sr.id, sr.service_date, sr.status, sr.cost, v.plate_number, st.name AS service_name
         FROM service_records sr
         JOIN vehicles v ON v.id = sr.vehicle_id
         LEFT JOIN service_types st ON st.id = sr.service_type_id
         ORDER BY sr.service_date DESC, sr.id DESC
         LIMIT 5'
    )->fetchAll();

    return [
        'counts' => $counts,
        'recent_services' => $recent,
    ];
}
