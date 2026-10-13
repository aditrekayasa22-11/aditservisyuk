<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

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
        } elseif ($action === 'save_service') {
            $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
            $serviceTypeId = (int) ($_POST['service_type_id'] ?? 0);
            if ($vehicleId < 1 || $serviceTypeId < 1) {
                throw new InvalidArgumentException('Pilih kendaraan dan jenis servis yang valid.');
            }
            $statement = $pdo->prepare('INSERT INTO service_records (vehicle_id, service_type_id, description, status, service_date, cost) VALUES (:vehicle_id, :service_type_id, :description, :status, :service_date, :cost)');
            $statement->execute([
                'vehicle_id' => $vehicleId,
                'service_type_id' => $serviceTypeId,
                'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
                'status' => (string) ($_POST['status'] ?? 'Menunggu'),
                'service_date' => (string) ($_POST['service_date'] ?? date('Y-m-d')),
                'cost' => max(0, (float) ($_POST['cost'] ?? 0)),
            ]);
            flash('Catatan servis berhasil ditambahkan.');
        } elseif ($action === 'update_service_status') {
            $allowedStatuses = ['Menunggu', 'Dikerjakan', 'Selesai', 'Dibatalkan'];
            $status = (string) ($_POST['status'] ?? '');
            if (!in_array($status, $allowedStatuses, true)) {
                throw new InvalidArgumentException('Status servis tidak valid.');
            }
            $statement = $pdo->prepare('UPDATE service_records SET status = :status WHERE id = :id');
            $statement->execute(['status' => $status, 'id' => (int) ($_POST['service_id'] ?? 0)]);
            flash('Status servis berhasil diperbarui.');
        } elseif ($action === 'save_stock') {
            $id = (int) ($_POST['inventory_id'] ?? 0);
            $values = [
                'name' => trim((string) ($_POST['stock_name'] ?? '')),
                'sku' => trim((string) ($_POST['sku'] ?? '')) ?: null,
                'quantity' => max(0, (int) ($_POST['quantity'] ?? 0)),
                'unit' => trim((string) ($_POST['unit'] ?? 'pcs')) ?: 'pcs',
                'purchase_price' => max(0, (float) ($_POST['purchase_price'] ?? 0)),
            ];
            if ($values['name'] === '') {
                throw new InvalidArgumentException('Nama barang wajib diisi.');
            }
            if ($id > 0) {
                $values['id'] = $id;
                $statement = $pdo->prepare('UPDATE inventory SET name=:name, sku=:sku, quantity=:quantity, unit=:unit, purchase_price=:purchase_price WHERE id=:id');
            } else {
                $statement = $pdo->prepare('INSERT INTO inventory (name, sku, quantity, unit, purchase_price) VALUES (:name, :sku, :quantity, :unit, :purchase_price)');
            }
            $statement->execute($values);
            flash($id > 0 ? 'Stok berhasil diperbarui.' : 'Barang berhasil ditambahkan ke stok.');
        } elseif ($action === 'save_payment') {
            $serviceId = (int) ($_POST['payment_service_id'] ?? 0);
            $status = (string) ($_POST['payment_status'] ?? 'Belum Dibayar');
            $method = (string) ($_POST['payment_method'] ?? 'Tunai');
            if ($serviceId < 1 || !in_array($status, ['Belum Dibayar', 'Lunas'], true) || !in_array($method, ['Tunai', 'Transfer', 'Kartu', 'QRIS'], true)) {
                throw new InvalidArgumentException('Data pembayaran tidak valid.');
            }
            $statement = $pdo->prepare(
                'INSERT INTO payments (service_record_id, amount, method, status, paid_at)
                 VALUES (:service_id, :amount, :method, :status, :paid_at)
                 ON DUPLICATE KEY UPDATE amount=VALUES(amount), method=VALUES(method), status=VALUES(status), paid_at=VALUES(paid_at)'
            );
            $statement->execute([
                'service_id' => $serviceId,
                'amount' => max(0, (float) ($_POST['amount'] ?? 0)),
                'method' => $method,
                'status' => $status,
                'paid_at' => $status === 'Lunas' ? date('Y-m-d H:i:s') : null,
            ]);
            flash('Pembayaran berhasil disimpan.');
        } elseif ($action === 'save_service_type') {
            $name = trim((string) ($_POST['service_type_name'] ?? ''));
            if ($name === '') {
                throw new InvalidArgumentException('Nama jenis servis wajib diisi.');
            }
            $statement = $pdo->prepare('INSERT INTO service_types (name, description, base_price) VALUES (:name, :description, :base_price)');
            $statement->execute([
                'name' => $name,
                'description' => trim((string) ($_POST['service_type_description'] ?? '')) ?: null,
                'base_price' => max(0, (float) ($_POST['base_price'] ?? 0)),
            ]);
            flash('Jenis servis berhasil ditambahkan.');
        } elseif ($action === 'delete_stock') {
            $statement = $pdo->prepare('DELETE FROM inventory WHERE id = :id');
            $statement->execute(['id' => (int) ($_POST['inventory_id'] ?? 0)]);
            flash('Barang berhasil dihapus dari stok.');
        } else {
            throw new InvalidArgumentException('Aksi tidak dikenali.');
        }
    } catch (InvalidArgumentException $exception) {
        flash($exception->getMessage(), 'error');
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('Perubahan gagal disimpan. Periksa apakah nomor polisi, SKU, atau data terkait sudah terdaftar.', 'error');
    }
    redirect('dashboard.php');
}

$counts = [
    'customers' => (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
    'vehicles' => (int) $pdo->query('SELECT COUNT(*) FROM vehicles')->fetchColumn(),
    'active' => (int) $pdo->query("SELECT COUNT(*) FROM service_records WHERE status IN ('Menunggu','Dikerjakan')")->fetchColumn(),
    'unpaid' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'Belum Dibayar'")->fetchColumn(),
];
$customers = $pdo->query('SELECT c.id, c.name, c.phone, c.email, c.created_at, v.id AS vehicle_id, v.plate_number, v.brand, v.model FROM customers c LEFT JOIN vehicles v ON v.customer_id=c.id ORDER BY c.id DESC')->fetchAll();
$vehicles = $pdo->query('SELECT v.id, v.plate_number, v.brand, v.model, c.name AS customer_name FROM vehicles v JOIN customers c ON c.id=v.customer_id ORDER BY v.plate_number')->fetchAll();
$serviceTypes = $pdo->query('SELECT id, name, description, base_price FROM service_types ORDER BY name')->fetchAll();
$records = $pdo->query('SELECT sr.id, sr.description, sr.status, sr.service_date, sr.cost, v.plate_number, c.name AS customer_name, st.name AS service_name FROM service_records sr JOIN vehicles v ON v.id=sr.vehicle_id JOIN customers c ON c.id=v.customer_id LEFT JOIN service_types st ON st.id=sr.service_type_id ORDER BY sr.service_date DESC, sr.id DESC')->fetchAll();
$inventory = $pdo->query('SELECT id, name, sku, quantity, unit, purchase_price FROM inventory ORDER BY name')->fetchAll();
$payments = $pdo->query('SELECT p.id, p.service_record_id, p.amount, p.method, p.status, p.paid_at, v.plate_number, c.name AS customer_name, sr.service_date FROM payments p JOIN service_records sr ON sr.id=p.service_record_id JOIN vehicles v ON v.id=sr.vehicle_id JOIN customers c ON c.id=v.customer_id ORDER BY p.id DESC')->fetchAll();
$flashMessage = take_flash();

$pageTitle = 'Dashboard Admin';
$adminView = true;
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
    <aside class="dashboard-sidebar">
        <span class="sidebar-label">WORKSPACE</span>
        <a class="sidebar-link active" href="#overview"><span>◫</span> Ringkasan</a>
        <a class="sidebar-link" href="#pelanggan"><span>♙</span> Pelanggan</a>
        <a class="sidebar-link" href="#servis"><span>⚙</span> Servis</a>
        <a class="sidebar-link" href="#stok"><span>▦</span> Stok barang</a>
        <a class="sidebar-link" href="#pembayaran"><span>◇</span> Pembayaran</a>
        <div class="sidebar-bottom"><span class="sidebar-label">SISTEM</span><a href="../index.php" class="sidebar-link"><span>↗</span> Halaman pengguna</a></div>
    </aside>
    <div class="dashboard-content">
        <div class="dashboard-topline"><div><span class="eyebrow eyebrow-dark">PUSAT KONTROL</span><h1>Dashboard</h1><p>Kelola operasional bengkel Anda hari ini.</p></div><span class="date-chip"><?= e(date('d M Y')) ?></span></div>
        <?php if ($flashMessage): ?><div class="alert alert-<?= e($flashMessage['type']) ?>"><?= e($flashMessage['message']) ?></div><?php endif; ?>
        <section class="stat-grid" id="overview">
            <article class="stat-card"><span class="stat-icon icon-violet">♙</span><span class="stat-label">PELANGGAN</span><strong><?= $counts['customers'] ?></strong><small>Terdaftar di sistem</small></article>
            <article class="stat-card"><span class="stat-icon icon-blue">▱</span><span class="stat-label">KENDARAAN</span><strong><?= $counts['vehicles'] ?></strong><small>Nomor polisi terdaftar</small></article>
            <article class="stat-card"><span class="stat-icon icon-amber">⚙</span><span class="stat-label">SERVIS AKTIF</span><strong><?= $counts['active'] ?></strong><small>Menunggu atau dikerjakan</small></article>
            <article class="stat-card"><span class="stat-icon icon-green">◇</span><span class="stat-label">TAGIHAN TERTUNGGAK</span><strong class="stat-money"><?= rupiah($counts['unpaid']) ?></strong><small>Belum lunas</small></article>
        </section>

        <section class="admin-section" id="pelanggan">
            <div class="section-heading"><div><span class="eyebrow eyebrow-dark">DATA PELANGGAN</span><h2>Pelanggan & kendaraan</h2></div><span class="count-chip"><?= count($customers) ?> data</span></div>
            <form class="admin-form form-card" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_customer">
                <h3>Tambah pelanggan dan kendaraan</h3>
                <div class="form-grid">
                    <div><label>NAMA PELANGGAN *</label><input class="text-input" name="name" required></div>
                    <div><label>NOMOR TELEPON</label><input class="text-input" name="phone" type="tel"></div>
                    <div><label>EMAIL</label><input class="text-input" name="email" type="email"></div>
                    <div><label>NOMOR POLISI *</label><input class="text-input" name="plate_number" placeholder="B 1234 ABC" required></div>
                    <div><label>MEREK</label><input class="text-input" name="brand" placeholder="Toyota"></div>
                    <div><label>MODEL</label><input class="text-input" name="model" placeholder="Avanza"></div>
                    <div><label>TAHUN</label><input class="text-input" name="year" type="number" min="1900" max="<?= date('Y') + 1 ?>"></div>
                </div>
                <button class="button button-primary" type="submit">Simpan pelanggan <span>→</span></button>
            </form>
            <div class="table-wrap"><table><thead><tr><th>PELANGGAN</th><th>KENDARAAN</th><th>TELEPON</th><th>EMAIL</th></tr></thead><tbody>
                <?php foreach ($customers as $customer): ?><tr><td><strong><?= e($customer['name']) ?></strong></td><td><?= e($customer['plate_number'] ?: 'Belum ada kendaraan') ?><small class="table-sub"><?= e(trim(($customer['brand'] ?? '') . ' ' . ($customer['model'] ?? ''))) ?></small></td><td><?= e($customer['phone'] ?: '—') ?></td><td><?= e($customer['email'] ?: '—') ?></td></tr><?php endforeach; ?>
                <?php if (!$customers): ?><tr><td colspan="4" class="table-empty">Belum ada pelanggan.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>

        <section class="admin-section" id="servis">
            <div class="section-heading"><div><span class="eyebrow eyebrow-dark">BENGKEL</span><h2>Servis kendaraan</h2></div></div>
            <div class="split-forms">
                <form class="admin-form form-card" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_service"><h3>Tambah catatan servis</h3>
                    <?php if (!$vehicles || !$serviceTypes): ?><p class="inline-note">Tambahkan pelanggan/kendaraan dan jenis servis terlebih dahulu.</p><?php else: ?>
                    <label>KENDARAAN *</label><select class="text-input" name="vehicle_id" required><option value="">Pilih nomor polisi</option><?php foreach ($vehicles as $vehicle): ?><option value="<?= (int) $vehicle['id'] ?>"><?= e($vehicle['plate_number'] . ' · ' . $vehicle['customer_name']) ?></option><?php endforeach; ?></select>
                    <label>JENIS SERVIS *</label><select class="text-input" name="service_type_id" required><option value="">Pilih jenis servis</option><?php foreach ($serviceTypes as $type): ?><option value="<?= (int) $type['id'] ?>"><?= e($type['name']) ?> · <?= rupiah($type['base_price']) ?></option><?php endforeach; ?></select>
                    <div class="form-grid"><div><label>TANGGAL</label><input class="text-input" type="date" name="service_date" value="<?= e(date('Y-m-d')) ?>" required></div><div><label>BIAYA (RP)</label><input class="text-input" type="number" min="0" step="1000" name="cost" value="0"></div></div>
                    <label>STATUS</label><select class="text-input" name="status"><option>Menunggu</option><option>Dikerjakan</option><option>Selesai</option></select>
                    <label>CATATAN PEKERJAAN</label><textarea class="text-input" name="description" rows="2"></textarea>
                    <button class="button button-primary" type="submit">Simpan servis <span>→</span></button><?php endif; ?>
                </form>
                <form class="admin-form form-card" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_service_type"><h3>Tambah jenis servis</h3>
                    <label>NAMA SERVIS *</label><input class="text-input" name="service_type_name" placeholder="Contoh: Ganti oli" required>
                    <label>DESKRIPSI</label><textarea class="text-input" name="service_type_description" rows="2"></textarea>
                    <label>HARGA DASAR (RP)</label><input class="text-input" name="base_price" type="number" min="0" step="1000" value="0">
                    <button class="button button-secondary" type="submit">Tambah jenis servis <span>+</span></button>
                    <div class="service-type-list"><?php foreach ($serviceTypes as $type): ?><span><?= e($type['name']) ?><small><?= rupiah($type['base_price']) ?></small></span><?php endforeach; ?><?php if (!$serviceTypes): ?><small>Belum ada jenis servis.</small><?php endif; ?></div>
                </form>
            </div>
            <div class="table-wrap"><table><thead><tr><th>TANGGAL</th><th>KENDARAAN / PELANGGAN</th><th>PEKERJAAN</th><th>BIAYA</th><th>STATUS</th></tr></thead><tbody>
                <?php foreach ($records as $record): ?><tr><td><?= e(date('d M Y', strtotime($record['service_date']))) ?></td><td><strong><?= e($record['plate_number']) ?></strong><small class="table-sub"><?= e($record['customer_name']) ?></small></td><td><?= e($record['service_name'] ?: 'Servis') ?><small class="table-sub"><?= e($record['description'] ?: '') ?></small></td><td><?= rupiah($record['cost']) ?></td><td><form method="post" class="inline-status"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_service_status"><input type="hidden" name="service_id" value="<?= (int) $record['id'] ?>"><select class="status-select <?= status_class($record['status']) ?>" name="status" onchange="this.form.submit()"><?php foreach (['Menunggu','Dikerjakan','Selesai','Dibatalkan'] as $status): ?><option <?= $record['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></form></td></tr><?php endforeach; ?>
                <?php if (!$records): ?><tr><td colspan="5" class="table-empty">Belum ada catatan servis.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>

        <section class="admin-section" id="stok">
            <div class="section-heading"><div><span class="eyebrow eyebrow-dark">PERSEDIAAN</span><h2>Stok barang</h2></div></div>
            <form class="admin-form form-card" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_stock">
                <h3><?= $inventory ? 'Tambah atau perbarui stok' : 'Tambah barang pertama' ?></h3><input type="hidden" name="inventory_id" value="0">
                <div class="form-grid"><div><label>NAMA BARANG *</label><input class="text-input" name="stock_name" required></div><div><label>KODE SKU (OPSIONAL)</label><input class="text-input" name="sku"></div><div><label>JUMLAH</label><input class="text-input" name="quantity" type="number" min="0" value="0"></div><div><label>SATUAN</label><input class="text-input" name="unit" value="pcs"></div><div><label>HARGA BELI (RP)</label><input class="text-input" name="purchase_price" type="number" min="0" step="1000" value="0"></div></div>
                <button class="button button-primary" type="submit">Simpan barang <span>→</span></button>
            </form>
            <div class="table-wrap"><table><thead><tr><th>NAMA BARANG</th><th>SKU</th><th>STOK</th><th>HARGA BELI</th><th>AKSI</th></tr></thead><tbody>
                <?php foreach ($inventory as $item): ?><tr><td><strong><?= e($item['name']) ?></strong></td><td><?= e($item['sku'] ?: '—') ?></td><td><?= (int) $item['quantity'] ?> <?= e($item['unit']) ?></td><td><?= rupiah($item['purchase_price']) ?></td><td><form method="post" class="inline-stock-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_stock"><input type="hidden" name="inventory_id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="stock_name" value="<?= e($item['name']) ?>"><input type="hidden" name="sku" value="<?= e($item['sku'] ?? '') ?>"><input type="hidden" name="unit" value="<?= e($item['unit']) ?>"><input type="number" name="quantity" min="0" value="<?= (int) $item['quantity'] ?>" aria-label="Jumlah stok baru"><input type="hidden" name="purchase_price" value="<?= e((string) $item['purchase_price']) ?>"><button class="text-button">Perbarui</button></form></td></tr><?php endforeach; ?>
                <?php if (!$inventory): ?><tr><td colspan="5" class="table-empty">Stok masih kosong.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>

        <section class="admin-section" id="pembayaran">
            <div class="section-heading"><div><span class="eyebrow eyebrow-dark">KEUANGAN</span><h2>Pembayaran</h2></div></div>
            <form class="admin-form form-card" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_payment"><h3>Catat pembayaran servis</h3>
                <?php if (!$records): ?><p class="inline-note">Tambahkan catatan servis sebelum mencatat pembayaran.</p><?php else: ?>
                <div class="form-grid">
                    <div><label>PEKERJAAN SERVIS *</label><select class="text-input" name="payment_service_id" required><option value="">Pilih servis</option><?php foreach ($records as $record): ?><option value="<?= (int) $record['id'] ?>"><?= e($record['plate_number'] . ' · ' . $record['customer_name'] . ' · ' . date('d/m/Y', strtotime($record['service_date']))) ?></option><?php endforeach; ?></select></div>
                    <div><label>JUMLAH (RP)</label><input class="text-input" name="amount" type="number" min="0" step="1000" value="0"></div>
                    <div><label>METODE</label><select class="text-input" name="payment_method"><?php foreach (['Tunai','Transfer','Kartu','QRIS'] as $method): ?><option><?= e($method) ?></option><?php endforeach; ?></select></div>
                    <div><label>STATUS PEMBAYARAN</label><select class="text-input" name="payment_status"><option>Belum Dibayar</option><option>Lunas</option></select></div>
                </div><button class="button button-primary" type="submit">Simpan pembayaran <span>→</span></button><?php endif; ?>
            </form>
            <div class="table-wrap"><table><thead><tr><th>KENDARAAN / PELANGGAN</th><th>TANGGAL SERVIS</th><th>JUMLAH</th><th>METODE</th><th>STATUS</th></tr></thead><tbody>
                <?php foreach ($payments as $payment): ?><tr><td><strong><?= e($payment['plate_number']) ?></strong><small class="table-sub"><?= e($payment['customer_name']) ?></small></td><td><?= e(date('d M Y', strtotime($payment['service_date']))) ?></td><td><?= rupiah($payment['amount']) ?></td><td><?= e($payment['method']) ?></td><td><span class="status-badge <?= status_class($payment['status']) ?>"><?= e($payment['status']) ?></span></td></tr><?php endforeach; ?>
                <?php if (!$payments): ?><tr><td colspan="5" class="table-empty">Belum ada data pembayaran.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
