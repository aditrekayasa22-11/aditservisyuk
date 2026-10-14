<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();
$allowedStatuses = ['Menunggu', 'Dikerjakan', 'Selesai', 'Dibatalkan'];

/**
 * Semua aksi pengelolaan data servis ditangani di bagian ini.
 * Setelah berhasil, halaman dimuat ulang (POST -> redirect -> GET).
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'save_service') {
            $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
            $serviceTypeId = (int) ($_POST['service_type_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'Menunggu');
            $serviceDate = (string) ($_POST['service_date'] ?? date('Y-m-d'));

            if ($vehicleId < 1) {
                throw new InvalidArgumentException('Pilih kendaraan yang valid.');
            }
            if ($serviceTypeId < 1) {
                throw new InvalidArgumentException('Pilih jenis servis yang valid.');
            }
            if (!in_array($status, $allowedStatuses, true)) {
                throw new InvalidArgumentException('Status servis tidak valid.');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $serviceDate) || strtotime($serviceDate) === false) {
                throw new InvalidArgumentException('Tanggal servis tidak valid.');
            }

            $statement = $pdo->prepare(
                'INSERT INTO service_records (vehicle_id, service_type_id, description, status, service_date, cost)
                 VALUES (:vehicle_id, :service_type_id, :description, :status, :service_date, :cost)'
            );
            $statement->execute([
                'vehicle_id' => $vehicleId,
                'service_type_id' => $serviceTypeId,
                'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
                'status' => $status,
                'service_date' => $serviceDate,
                'cost' => max(0, (float) ($_POST['cost'] ?? 0)),
            ]);
            flash('Catatan servis berhasil ditambahkan.');
        } elseif ($action === 'update_service') {
            $id = (int) ($_POST['service_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? '');
            $serviceDate = (string) ($_POST['service_date'] ?? '');
            $cost = max(0, (float) ($_POST['cost'] ?? 0));
            $description = trim((string) ($_POST['description'] ?? '')) ?: null;

            if ($id < 1) {
                throw new InvalidArgumentException('Catatan servis tidak ditemukan.');
            }
            if (!in_array($status, $allowedStatuses, true)) {
                throw new InvalidArgumentException('Status servis tidak valid.');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $serviceDate) || strtotime($serviceDate) === false) {
                throw new InvalidArgumentException('Tanggal servis tidak valid.');
            }

            $statement = $pdo->prepare(
                'UPDATE service_records
                 SET status = :status, service_date = :service_date, cost = :cost, description = :description
                 WHERE id = :id'
            );
            $statement->execute([
                'status' => $status,
                'service_date' => $serviceDate,
                'cost' => $cost,
                'description' => $description,
                'id' => $id,
            ]);
            flash('Catatan servis berhasil diperbarui.');
        } elseif ($action === 'delete_service') {
            $id = (int) ($_POST['service_id'] ?? 0);
            $statement = $pdo->prepare('DELETE FROM service_records WHERE id = :id');
            $statement->execute(['id' => $id]);
            flash('Catatan servis berhasil dihapus.');
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
        } elseif ($action === 'delete_service_type') {
            $id = (int) ($_POST['service_type_id'] ?? 0);
            $statement = $pdo->prepare('DELETE FROM service_types WHERE id = :id');
            $statement->execute(['id' => $id]);
            flash('Jenis servis berhasil dihapus.');
        } else {
            throw new InvalidArgumentException('Aksi tidak dikenali.');
        }
    } catch (InvalidArgumentException $exception) {
        flash($exception->getMessage(), 'error');
    } catch (PDOException $exception) {
        flash('Data gagal disimpan. Pastikan data terkait sudah terdaftar dan tidak duplikat.', 'error');
    }
    redirect('servis.php');
}

/** Filter pencarian dari formulir GET. */
$filterStatus = (string) ($_GET['status'] ?? '');
$filterPlate = strtoupper(trim((string) ($_GET['plate'] ?? '')));

$vehicles = $pdo->query(
    'SELECT v.id, v.plate_number, v.brand, v.model, c.name AS customer_name
     FROM vehicles v JOIN customers c ON c.id = v.customer_id
     ORDER BY v.plate_number'
)->fetchAll();
$serviceTypes = $pdo->query('SELECT id, name, description, base_price FROM service_types ORDER BY name')->fetchAll();

$sql = 'SELECT sr.id, sr.vehicle_id, sr.service_type_id, sr.description, sr.status, sr.service_date, sr.cost,
               v.plate_number, c.name AS customer_name, st.name AS service_name
        FROM service_records sr
        JOIN vehicles v ON v.id = sr.vehicle_id
        JOIN customers c ON c.id = v.customer_id
        LEFT JOIN service_types st ON st.id = sr.service_type_id';
$where = [];
$bindings = [];
if (in_array($filterStatus, $allowedStatuses, true)) {
    $where[] = 'sr.status = :status';
    $bindings['status'] = $filterStatus;
}
if ($filterPlate !== '') {
    $where[] = 'REPLACE(UPPER(v.plate_number), " ", "") LIKE :plate';
    $bindings['plate'] = '%' . str_replace(' ', '', $filterPlate) . '%';
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY sr.service_date DESC, sr.id DESC';
$statement = $pdo->prepare($sql);
$statement->execute($bindings);
$records = $statement->fetchAll();

/** Catatan servis yang sedang diedit (jika ada). */
$editing = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    foreach ($records as $row) {
        if ((int) $row['id'] === $editId) {
            $editing = $row;
            break;
        }
    }
    if (!$editing) {
        $find = $pdo->prepare(
            'SELECT sr.id, sr.vehicle_id, sr.service_type_id, sr.description, sr.status, sr.service_date, sr.cost,
                    v.plate_number, c.name AS customer_name, st.name AS service_name
             FROM service_records sr
             JOIN vehicles v ON v.id = sr.vehicle_id
             JOIN customers c ON c.id = v.customer_id
             LEFT JOIN service_types st ON st.id = sr.service_type_id
             WHERE sr.id = :id'
        );
        $find->execute(['id' => $editId]);
        $editing = $find->fetch() ?: null;
    }
}

$flashMessage = take_flash();
$pageTitle = 'Data Servis';
$adminView = true;
$currentPage = 'servis';
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<div class="dashboard-content">
    <div class="dashboard-topline">
        <div>
            <span class="eyebrow">MANAJEMEN PEKERJAAN</span>
            <h1>Data Servis</h1>
            <p>Tambah, ubah, dan hapus catatan pekerjaan servis kendaraan.</p>
        </div>
        <span class="date-chip"><?= count($records) ?> catatan</span>
    </div>

    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= e($flashMessage['type']) ?> alert-auto"><?= e($flashMessage['message']) ?></div>
    <?php endif; ?>

    <!-- Form tambah / edit servis -->
    <section class="admin-section" id="form-servis">
        <div class="section-heading">
            <div><span class="eyebrow"><?= $editing ? 'UBAH CATATAN' : 'CATATAN BARU' ?></span><h2><?= $editing ? 'Edit catatan servis' : 'Tambah catatan servis' ?></h2></div>
            <?php if ($editing): ?><a class="button button-ghost button-sm" href="servis.php">Batal edit</a><?php endif; ?>
        </div>

        <?php if (!$vehicles || !$serviceTypes): ?>
            <div class="inline-note">Tambahkan data pelanggan/kendaraan terlebih dahulu, dan pastikan minimal ada satu jenis servis.</div>
        <?php else: ?>
            <form class="admin-form form-card" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= $editing ? 'update_service' : 'save_service' ?>">
                <?php if ($editing): ?><input type="hidden" name="service_id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>

                <div class="form-grid">
                    <div>
                        <label>KENDARAAN *</label>
                        <select class="text-input" name="vehicle_id" required>
                            <option value="">Pilih nomor polisi</option>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <option value="<?= (int) $vehicle['id'] ?>" <?= $editing && (int) $editing['vehicle_id'] === (int) $vehicle['id'] ? 'selected' : '' ?>>
                                    <?= e($vehicle['plate_number'] . ' · ' . $vehicle['customer_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>JENIS SERVIS *</label>
                        <select class="text-input" name="service_type_id" required>
                            <option value="">Pilih jenis servis</option>
                            <?php foreach ($serviceTypes as $type): ?>
                                <option value="<?= (int) $type['id'] ?>" <?= $editing && (int) $editing['service_type_id'] === (int) $type['id'] ? 'selected' : '' ?>>
                                    <?= e($type['name']) ?> · <?= rupiah($type['base_price']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>TANGGAL SERVIS</label>
                        <input class="text-input" type="date" name="service_date" value="<?= e($editing['service_date'] ?? date('Y-m-d')) ?>" required>
                    </div>
                    <div>
                        <label>BIAYA (RP)</label>
                        <input class="text-input" type="number" min="0" step="1000" name="cost" value="<?= e((string) ($editing['cost'] ?? '0')) ?>">
                    </div>
                    <div>
                        <label>STATUS</label>
                        <select class="text-input" name="status">
                            <?php foreach ($allowedStatuses as $status): ?>
                                <option <?= ($editing['status'] ?? 'Menunggu') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <label>CATATAN / KELUHAN PEKERJAAN</label>
                <textarea class="text-input" name="description" rows="2" placeholder="Contoh: rem dek bunyi, ganti kampas"><?= e($editing['description'] ?? '') ?></textarea>

                <button class="button button-primary" type="submit"><?= $editing ? 'Simpan perubahan' : 'Simpan servis' ?> <?= icon('arrow-right') ?></button>
            </form>
        <?php endif; ?>
    </section>

    <!-- Filter -->
    <section class="admin-section">
        <div class="section-heading">
            <div><span class="eyebrow">DAFTAR SERVIS</span><h2>Semua catatan servis</h2></div>
        </div>

        <form class="filter-bar" method="get">
            <div class="filter-item">
                <label for="filter-plate">NOMOR POLISI</label>
                <input class="text-input" id="filter-plate" name="plate" value="<?= e($filterPlate) ?>" placeholder="Cari polisi...">
            </div>
            <div class="filter-item">
                <label for="filter-status">STATUS</label>
                <select class="text-input" id="filter-status" name="status">
                    <option value="">Semua status</option>
                    <?php foreach ($allowedStatuses as $status): ?>
                        <option <?= $filterStatus === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="button button-navy button-sm" type="submit">Filter</button>
            <a class="button button-ghost button-sm" href="servis.php">Reset</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>TANGGAL</th>
                        <th>KENDARAAN / PELANGGAN</th>
                        <th>PEKERJAAN</th>
                        <th>BIAYA</th>
                        <th>STATUS</th>
                        <th>AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td><?= e(date('d M Y', strtotime($record['service_date']))) ?></td>
                            <td><strong><?= e($record['plate_number']) ?></strong><small class="table-sub"><?= e($record['customer_name']) ?></small></td>
                            <td><?= e($record['service_name'] ?: 'Servis') ?><?php if ($record['description']): ?><small class="table-sub"><?= e($record['description']) ?></small><?php endif; ?></td>
                            <td><?= rupiah($record['cost']) ?></td>
                            <td><span class="status-badge <?= status_class($record['status']) ?>"><?= e($record['status']) ?></span></td>
                            <td>
                                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                                    <a class="text-button" href="servis.php?edit=<?= (int) $record['id'] ?>#form-servis"><?= icon('pencil') ?> Edit</a>
                                    <form method="post" data-confirm="Hapus catatan servis ini?">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_service">
                                        <input type="hidden" name="service_id" value="<?= (int) $record['id'] ?>">
                                        <button class="text-button" type="submit" style="color:var(--red)"><?= icon('trash-2') ?> Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$records): ?>
                        <tr><td colspan="6" class="table-empty">Belum ada catatan servis<?= $filterStatus || $filterPlate ? ' untuk filter ini' : '' ?>.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Jenis servis -->
    <section class="admin-section">
        <div class="section-heading">
            <div><span class="eyebrow">JENIS SERVIS</span><h2>Katalog layanan</h2></div>
            <span class="count-chip"><?= count($serviceTypes) ?> layanan</span>
        </div>

        <form class="admin-form form-card" method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_service_type">
            <h3>Tambah jenis servis</h3>
            <div class="form-grid">
                <div><label>NAMA SERVIS *</label><input class="text-input" name="service_type_name" placeholder="Contoh: Ganti oli" required></div>
                <div><label>HARGA DASAR (RP)</label><input class="text-input" name="base_price" type="number" min="0" step="1000" value="0"></div>
            </div>
            <label>DESKRIPSI</label>
            <textarea class="text-input" name="service_type_description" rows="2"></textarea>
            <button class="button button-primary" type="submit">Tambah jenis servis <span>+</span></button>
        </form>

        <div class="table-wrap" style="margin-top:18px">
            <table>
                <thead><tr><th>NAMA</th><th>DESKRIPSI</th><th>HARGA DASAR</th><th>AKSI</th></tr></thead>
                <tbody>
                    <?php foreach ($serviceTypes as $type): ?>
                        <tr>
                            <td><strong><?= e($type['name']) ?></strong></td>
                            <td><?= e($type['description'] ?: '—') ?></td>
                            <td><?= rupiah($type['base_price']) ?></td>
                            <td>
                                <form method="post" data-confirm="Hapus jenis servis ini?">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_service_type">
                                    <input type="hidden" name="service_type_id" value="<?= (int) $type['id'] ?>">
                                    <button class="text-button" type="submit" style="color:var(--red)">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$serviceTypes): ?>
                        <tr><td colspan="4" class="table-empty">Belum ada jenis servis.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
