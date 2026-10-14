<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

/**
 * Aksi halaman pelanggan:
 *  - save_customer : tambah pelanggan + kendaraan sekaligus (transaksi)
 *  - save_vehicle  : tambah kendaraan lain untuk pelanggan yang sudah ada
 *  - save_payment  : catat pembayaran sebuah servis
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'save_customer') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $plate = strtoupper(trim((string) ($_POST['plate_number'] ?? '')));
            if ($name === '' || $plate === '') {
                throw new InvalidArgumentException('Nama pelanggan dan nomor polisi wajib diisi.');
            }
            $pdo->beginTransaction();
            $statement = $pdo->prepare('INSERT INTO customers (name, phone, email) VALUES (:name, :phone, :email)');
            $statement->execute([
                'name' => $name,
                'phone' => trim((string) ($_POST['phone'] ?? '')) ?: null,
                'email' => trim((string) ($_POST['email'] ?? '')) ?: null,
            ]);
            $customerId = (int) $pdo->lastInsertId();
            $statement = $pdo->prepare('INSERT INTO vehicles (customer_id, plate_number, brand, model, year) VALUES (:customer_id, :plate, :brand, :model, :year)');
            $statement->execute([
                'customer_id' => $customerId,
                'plate' => $plate,
                'brand' => trim((string) ($_POST['brand'] ?? '')) ?: null,
                'model' => trim((string) ($_POST['model'] ?? '')) ?: null,
                'year' => ($_POST['year'] ?? '') !== '' ? (int) $_POST['year'] : null,
            ]);
            $pdo->commit();
            flash('Pelanggan dan kendaraan berhasil ditambahkan.');
        } elseif ($action === 'save_vehicle') {
            $customerId = (int) ($_POST['customer_id'] ?? 0);
            $plate = strtoupper(trim((string) ($_POST['plate_number'] ?? '')));
            if ($customerId < 1 || $plate === '') {
                throw new InvalidArgumentException('Pilih pelanggan dan isi nomor polisi.');
            }
            $statement = $pdo->prepare('INSERT INTO vehicles (customer_id, plate_number, brand, model, year) VALUES (:customer_id, :plate, :brand, :model, :year)');
            $statement->execute([
                'customer_id' => $customerId,
                'plate' => $plate,
                'brand' => trim((string) ($_POST['brand'] ?? '')) ?: null,
                'model' => trim((string) ($_POST['model'] ?? '')) ?: null,
                'year' => ($_POST['year'] ?? '') !== '' ? (int) $_POST['year'] : null,
            ]);
            flash('Kendaraan berhasil ditambahkan.');
        } elseif ($action === 'delete_customer') {
            $customerId = (int) ($_POST['customer_id'] ?? 0);
            $statement = $pdo->prepare('DELETE FROM customers WHERE id = :id');
            $statement->execute(['id' => $customerId]);
            flash('Pelanggan beserta kendaraan dan riwayat servisnya berhasil dihapus.');
        } elseif ($action === 'save_payment') {
            $serviceId = (int) ($_POST['payment_service_id'] ?? 0);
            $status = (string) ($_POST['payment_status'] ?? 'Belum Dibayar');
            $method = (string) ($_POST['payment_method'] ?? 'Tunai');
            $methods = ['Tunai', 'Transfer', 'Kartu', 'QRIS'];
            if ($serviceId < 1 || !in_array($status, payment_statuses(), true) || !in_array($method, $methods, true)) {
                throw new InvalidArgumentException('Data pembayaran tidak valid.');
            }
            $isPaid = $status === 'Lunas';
            $statement = $pdo->prepare(
                'INSERT INTO payments (service_record_id, amount, method, status, paid_at, verified_at, verified_by)
                 VALUES (:service_id, :amount, :method, :status, :paid_at, :verified_at, :verified_by)
                 ON DUPLICATE KEY UPDATE amount=VALUES(amount), method=VALUES(method), status=VALUES(status),
                     paid_at=VALUES(paid_at), verified_at=VALUES(verified_at), verified_by=VALUES(verified_by)'
            );
            $statement->execute([
                'service_id' => $serviceId,
                'amount' => max(0, (float) ($_POST['amount'] ?? 0)),
                'method' => $method,
                'status' => $status,
                'paid_at' => $isPaid ? date('Y-m-d H:i:s') : null,
                'verified_at' => $isPaid ? date('Y-m-d H:i:s') : null,
                'verified_by' => $isPaid ? (int) $_SESSION['admin_id'] : null,
            ]);
            flash('Pembayaran berhasil disimpan.');
        } else {
            throw new InvalidArgumentException('Aksi tidak dikenali.');
        }
    } catch (InvalidArgumentException $exception) {
        flash($exception->getMessage(), 'error');
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('Data gagal disimpan. Periksa apakah nomor polisi sudah terdaftar.', 'error');
    }
    redirect('pelanggan.php');
}

$customers = $pdo->query(
    'SELECT c.id, c.name, c.phone, c.email, c.created_at,
            COUNT(v.id) AS vehicle_count
     FROM customers c
     LEFT JOIN vehicles v ON v.customer_id = c.id
     GROUP BY c.id
     ORDER BY c.id DESC'
)->fetchAll();

$vehicles = $pdo->query(
    'SELECT v.id, v.plate_number, v.brand, v.model, v.year, v.customer_id, c.name AS customer_name
     FROM vehicles v JOIN customers c ON c.id = v.customer_id
     ORDER BY v.plate_number'
)->fetchAll();

$records = $pdo->query(
    'SELECT sr.id, sr.service_date, sr.cost, sr.status, v.plate_number, c.name AS customer_name
     FROM service_records sr
     JOIN vehicles v ON v.id = sr.vehicle_id
     JOIN customers c ON c.id = v.customer_id
     ORDER BY sr.service_date DESC, sr.id DESC'
)->fetchAll();

$payments = $pdo->query(
    'SELECT p.id, p.service_record_id, p.amount, p.method, p.status, p.paid_at,
            v.plate_number, c.name AS customer_name, sr.service_date
     FROM payments p
     JOIN service_records sr ON sr.id = p.service_record_id
     JOIN vehicles v ON v.id = sr.vehicle_id
     JOIN customers c ON c.id = v.customer_id
     ORDER BY p.id DESC'
)->fetchAll();

/** Kelompokkan kendaraan per pelanggan untuk ditampilkan di tabel. */
$vehiclesByCustomer = [];
foreach ($vehicles as $vehicle) {
    $vehiclesByCustomer[(int) $vehicle['customer_id']][] = $vehicle;
}

$flashMessage = take_flash();
$pageTitle = 'Data Pelanggan';
$adminView = true;
$currentPage = 'pelanggan';
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<div class="dashboard-content">
    <div class="dashboard-topline">
        <div>
            <span class="eyebrow">DATA PELANGGAN</span>
            <h1>Pelanggan &amp; Kendaraan</h1>
            <p>Kelola data pelanggan, kendaraan, dan pembayaran servis.</p>
        </div>
        <span class="date-chip"><?= count($customers) ?> pelanggan</span>
    </div>

    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= e($flashMessage['type']) ?> alert-auto"><?= e($flashMessage['message']) ?></div>
    <?php endif; ?>

    <section class="admin-section">
        <div class="section-heading"><div><span class="eyebrow">PELANGGAN BARU</span><h2>Tambah pelanggan &amp; kendaraan</h2></div></div>
        <form class="admin-form form-card" method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_customer">
            <div class="form-grid">
                <div><label>NAMA PELANGGAN *</label><input class="text-input" name="name" required></div>
                <div><label>NOMOR TELEPON</label><input class="text-input" name="phone" type="tel"></div>
                <div><label>EMAIL</label><input class="text-input" name="email" type="email"></div>
                <div><label>NOMOR POLISI *</label><input class="text-input" name="plate_number" placeholder="B 1234 SYK" required></div>
                <div><label>MEREK</label><input class="text-input" name="brand" placeholder="Toyota"></div>
                <div><label>MODEL</label><input class="text-input" name="model" placeholder="Avanza"></div>
                <div><label>TAHUN</label><input class="text-input" name="year" type="number" min="1900" max="<?= date('Y') + 1 ?>"></div>
            </div>
            <button class="button button-primary" type="submit">Simpan pelanggan <?= icon('arrow-right') ?></button>
        </form>
    </section>

    <section class="admin-section">
        <div class="section-heading">
            <div><span class="eyebrow">DAFTAR PELANGGAN</span><h2>Pelanggan terdaftar</h2></div>
            <span class="count-chip"><?= count($customers) ?> data</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>PELANGGAN</th><th>KENDARAAN</th><th>TELEPON</th><th>EMAIL</th><th>AKSI</th></tr></thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><strong><?= e($customer['name']) ?></strong><small class="table-sub">Terdaftar <?= e(date('d M Y', strtotime($customer['created_at']))) ?></small></td>
                            <td>
                                <?php $list = $vehiclesByCustomer[(int) $customer['id']] ?? []; ?>
                                <?php if (!$list): ?>
                                    <span class="text-muted">Belum ada kendaraan</span>
                                <?php else: ?>
                                    <?php foreach ($list as $vehicle): ?>
                                        <span class="status-badge status-info" style="margin:2px"><?= e($vehicle['plate_number']) ?></span>
                                        <small class="table-sub"><?= e(trim(($vehicle['brand'] ?? '') . ' ' . ($vehicle['model'] ?? ''))) ?></small>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td><?= e($customer['phone'] ?: '—') ?></td>
                            <td><?= e($customer['email'] ?: '—') ?></td>
                            <td>
                                <form method="post" class="inline-stock-form" data-confirm="Hapus pelanggan ini beserta kendaraan dan seluruh riwayat servisnya?">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_customer">
                                    <input type="hidden" name="customer_id" value="<?= (int) $customer['id'] ?>">
                                    <button class="text-button" type="submit" style="color:var(--red)"><?= icon('trash-2') ?> Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$customers): ?>
                        <tr><td colspan="5" class="table-empty">Belum ada pelanggan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-section">
        <div class="section-heading"><div><span class="eyebrow">KENDARAAN TAMBAHAN</span><h2>Tambah kendaraan pelanggan lama</h2></div></div>
        <?php if (!$customers): ?>
            <div class="inline-note">Tambahkan pelanggan terlebih dahulu.</div>
        <?php else: ?>
            <form class="admin-form form-card" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_vehicle">
                <div class="form-grid">
                    <div>
                        <label>PELANGGAN *</label>
                        <select class="text-input" name="customer_id" required>
                            <option value="">Pilih pelanggan</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?= (int) $customer['id'] ?>"><?= e($customer['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><label>NOMOR POLISI *</label><input class="text-input" name="plate_number" required></div>
                    <div><label>MEREK</label><input class="text-input" name="brand"></div>
                    <div><label>MODEL</label><input class="text-input" name="model"></div>
                    <div><label>TAHUN</label><input class="text-input" name="year" type="number" min="1900" max="<?= date('Y') + 1 ?>"></div>
                </div>
                <button class="button button-primary" type="submit">Tambah kendaraan <span>+</span></button>
            </form>
        <?php endif; ?>
    </section>

    <section class="admin-section">
        <div class="section-heading"><div><span class="eyebrow">PEMBAYARAN</span><h2>Catat pembayaran servis</h2></div><span class="count-chip"><?= count($payments) ?> transaksi</span></div>
        <form class="admin-form form-card" method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_payment">
            <?php if (!$records): ?>
                <div class="inline-note">Tambahkan catatan servis terlebih dahulu melalui menu Data Servis.</div>
            <?php else: ?>
                <div class="form-grid">
                    <div>
                        <label>PEKERJAAN SERVIS *</label>
                        <select class="text-input" name="payment_service_id" required>
                            <option value="">Pilih servis</option>
                            <?php foreach ($records as $record): ?>
                                <option value="<?= (int) $record['id'] ?>"><?= e($record['plate_number'] . ' · ' . $record['customer_name'] . ' · ' . date('d/m/Y', strtotime($record['service_date']))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><label>JUMLAH (RP)</label><input class="text-input" name="amount" type="number" min="0" step="1000" value="0"></div>
                    <div>
                        <label>METODE</label>
                        <select class="text-input" name="payment_method"><?php foreach (['Tunai', 'Transfer', 'Kartu', 'QRIS'] as $method): ?><option><?= e($method) ?></option><?php endforeach; ?></select>
                    </div>
                    <div>
                        <label>STATUS PEMBAYARAN</label>
                        <select class="text-input" name="payment_status"><?php foreach (payment_statuses() as $paymentStatus): ?><option><?= e($paymentStatus) ?></option><?php endforeach; ?></select>
                    </div>
                </div>
                <button class="button button-primary" type="submit">Simpan pembayaran <?= icon('arrow-right') ?></button>
            <?php endif; ?>
        </form>

        <div class="table-wrap" style="margin-top:18px">
            <table>
                <thead><tr><th>KENDARAAN / PELANGGAN</th><th>TANGGAL SERVIS</th><th>JUMLAH</th><th>METODE</th><th>STATUS</th></tr></thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><strong><?= e($payment['plate_number']) ?></strong><small class="table-sub"><?= e($payment['customer_name']) ?></small></td>
                            <td><?= e(date('d M Y', strtotime($payment['service_date']))) ?></td>
                            <td><?= rupiah($payment['amount']) ?></td>
                            <td><?= e($payment['method']) ?></td>
                            <td><span class="status-badge <?= status_class($payment['status']) ?>"><?= e($payment['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$payments): ?>
                        <tr><td colspan="5" class="table-empty">Belum ada data pembayaran.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
