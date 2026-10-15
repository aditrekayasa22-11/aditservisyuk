<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();
$allowedStatuses = service_statuses();
$doneStatuses = ['Selesai', 'Siap Diambil'];
$getSelectedServiceTypes = static function (PDO $pdo): array {
    $submitted = $_POST['service_type_ids'] ?? (isset($_POST['service_type_id']) ? [$_POST['service_type_id']] : []);
    if (!is_array($submitted)) {
        throw new InvalidArgumentException('Pilih minimal satu jenis servis yang valid.');
    }

    $ids = [];
    foreach ($submitted as $value) {
        if (!is_scalar($value) || !preg_match('/^[1-9]\d*$/', (string) $value)) {
            throw new InvalidArgumentException('Pilih jenis servis yang valid.');
        }
        $ids[] = (int) $value;
    }
    $ids = array_values(array_unique($ids));
    if (!$ids) {
        throw new InvalidArgumentException('Pilih minimal satu jenis servis.');
    }

    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $statement = $pdo->prepare("SELECT id, base_price FROM service_types WHERE id IN ($placeholders)");
    $statement->execute($ids);
    $prices = $statement->fetchAll();
    if (count($prices) !== count($ids)) {
        throw new InvalidArgumentException('Salah satu jenis servis tidak valid.');
    }

    return [$ids, array_sum(array_map(static fn (array $type): float => (float) $type['base_price'], $prices))];
};

/** Normalisasi input datetime-local (YYYY-MM-DDTHH:MM) menjadi DATETIME MySQL. */
$normalizeDateTime = static function (string $value): ?string {
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $timestamp = strtotime(str_replace('T', ' ', $value));
    if ($timestamp === false) {
        throw new InvalidArgumentException('Perkiraan waktu selesai tidak valid.');
    }
    return date('Y-m-d H:i:s', $timestamp);
};

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
            [$serviceTypeIds, $cost] = $getSelectedServiceTypes($pdo);
            $status = (string) ($_POST['status'] ?? 'Menunggu Pemeriksaan');
            $serviceDate = (string) ($_POST['service_date'] ?? date('Y-m-d'));
            $estimatedDone = $normalizeDateTime((string) ($_POST['estimated_done'] ?? ''));

            if ($vehicleId < 1) {
                throw new InvalidArgumentException('Pilih kendaraan yang valid.');
            }
            if (!in_array($status, $allowedStatuses, true)) {
                throw new InvalidArgumentException('Status servis tidak valid.');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $serviceDate) || strtotime($serviceDate) === false) {
                throw new InvalidArgumentException('Tanggal servis tidak valid.');
            }

            $pdo->beginTransaction();
            $statement = $pdo->prepare(
                'INSERT INTO service_records (vehicle_id, service_type_id, description, status, service_date, estimated_done, cost, completed_at)
                 VALUES (:vehicle_id, :service_type_id, :description, :status, :service_date, :estimated_done, :cost, :completed_at)'
            );
            $statement->execute([
                'vehicle_id' => $vehicleId,
                'service_type_id' => $serviceTypeIds[0],
                'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
                'status' => $status,
                'service_date' => $serviceDate,
                'estimated_done' => $estimatedDone,
                'cost' => $cost,
                'completed_at' => in_array($status, $doneStatuses, true) ? date('Y-m-d H:i:s') : null,
            ]);
            $serviceId = (int) $pdo->lastInsertId();
            $insertType = $pdo->prepare('INSERT INTO service_record_types (service_record_id, service_type_id) VALUES (:service_id, :type_id)');
            foreach ($serviceTypeIds as $serviceTypeId) {
                $insertType->execute(['service_id' => $serviceId, 'type_id' => $serviceTypeId]);
            }
            $pdo->commit();
            flash('Catatan servis berhasil ditambahkan.');
        } elseif ($action === 'update_service') {
            $id = (int) ($_POST['service_id'] ?? 0);
            [$serviceTypeIds, $cost] = $getSelectedServiceTypes($pdo);
            $serviceDate = (string) ($_POST['service_date'] ?? '');
            $estimatedDone = $normalizeDateTime((string) ($_POST['estimated_done'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? '')) ?: null;

            if ($id < 1) {
                throw new InvalidArgumentException('Catatan servis tidak ditemukan.');
            }
            $statement = $pdo->prepare('SELECT status, completed_at FROM service_records WHERE id = :id');
            $statement->execute(['id' => $id]);
            $existing = $statement->fetch();
            if (!$existing) {
                throw new InvalidArgumentException('Catatan servis tidak ditemukan.');
            }
            $status = (string) ($_POST['status'] ?? $existing['status']);
            if (!in_array($status, $allowedStatuses, true)) {
                throw new InvalidArgumentException('Status servis tidak valid.');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $serviceDate) || strtotime($serviceDate) === false) {
                throw new InvalidArgumentException('Tanggal servis tidak valid.');
            }

            // Waktu selesai aktual diisi saat status benar-benar selesai,
            // dan dikosongkan lagi bila pekerjaan dibuka ulang.
            if (in_array($status, $doneStatuses, true)) {
                $completedAt = $existing['completed_at'] ?: date('Y-m-d H:i:s');
            } else {
                $completedAt = null;
            }

            $pdo->beginTransaction();
            $statement = $pdo->prepare(
                'UPDATE service_records
                 SET service_type_id = :service_type_id, status = :status, service_date = :service_date, estimated_done = :estimated_done,
                     cost = :cost, description = :description, completed_at = :completed_at
                 WHERE id = :id'
            );
            $statement->execute([
                'service_type_id' => $serviceTypeIds[0],
                'status' => $status,
                'service_date' => $serviceDate,
                'estimated_done' => $estimatedDone,
                'cost' => $cost,
                'description' => $description,
                'completed_at' => $completedAt,
                'id' => $id,
            ]);
            $pdo->prepare('DELETE FROM service_record_types WHERE service_record_id = :id')->execute(['id' => $id]);
            $insertType = $pdo->prepare('INSERT INTO service_record_types (service_record_id, service_type_id) VALUES (:service_id, :type_id)');
            foreach ($serviceTypeIds as $serviceTypeId) {
                $insertType->execute(['service_id' => $id, 'type_id' => $serviceTypeId]);
            }
            $pdo->commit();
            flash('Catatan servis berhasil diperbarui.');
        } elseif ($action === 'save_progress' || $action === 'update_progress') {
            $id = (int) ($_POST['service_id'] ?? 0);
            $noteType = (string) ($_POST['note_type'] ?? 'Pemeriksaan');
            $note = trim((string) ($_POST['note'] ?? ''));
            if ($id < 1) {
                throw new InvalidArgumentException('Catatan servis tidak ditemukan.');
            }
            if (!in_array($noteType, progress_note_types(), true)) {
                throw new InvalidArgumentException('Jenis catatan tidak valid.');
            }
            if ($note === '') {
                throw new InvalidArgumentException('Isi catatan perkembangan wajib diisi.');
            }
            if ($action === 'update_progress') {
                $statement = $pdo->prepare('UPDATE service_progress SET note_type = :note_type, note = :note WHERE id = :id AND service_record_id = :service_id');
                $statement->execute(['note_type' => $noteType, 'note' => $note, 'id' => (int) ($_POST['progress_id'] ?? 0), 'service_id' => $id]);
                flash('Catatan perkembangan berhasil diperbarui.');
            } else {
                $statement = $pdo->prepare('INSERT INTO service_progress (service_record_id, note_type, note) VALUES (:service_id, :note_type, :note)');
                $statement->execute(['service_id' => $id, 'note_type' => $noteType, 'note' => $note]);
                flash('Catatan perkembangan berhasil ditambahkan.');
            }
        } elseif ($action === 'delete_progress') {
            $statement = $pdo->prepare('DELETE FROM service_progress WHERE id = :id AND service_record_id = :service_id');
            $statement->execute(['id' => (int) ($_POST['progress_id'] ?? 0), 'service_id' => (int) ($_POST['service_id'] ?? 0)]);
            flash('Catatan perkembangan berhasil dihapus.');
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
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash($exception->getMessage(), 'error');
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('Data gagal disimpan. Pastikan data terkait sudah terdaftar dan tidak duplikat.', 'error');
    }
    redirect('servis.php');
}

$vehicles = $pdo->query(
    'SELECT v.id, v.customer_id, v.plate_number, v.brand, v.model, v.created_at, c.name AS customer_name
     FROM vehicles v JOIN customers c ON c.id = v.customer_id
     ORDER BY v.created_at DESC, v.id DESC'
)->fetchAll();
$customers = $pdo->query(
    'SELECT DISTINCT c.id, c.name, c.created_at
     FROM customers c JOIN vehicles v ON v.customer_id = c.id
     ORDER BY c.created_at DESC, c.id DESC'
)->fetchAll();
$serviceTypes = $pdo->query('SELECT id, name, description, base_price FROM service_types ORDER BY name')->fetchAll();

$sql = 'SELECT sr.id, sr.vehicle_id, sr.service_type_id, sr.description, sr.status, sr.service_date, sr.estimated_done, sr.completed_at, sr.cost,
               COALESCE(p.status, "Belum Dibayar") AS payment_status,
               v.plate_number, c.name AS customer_name,
               COALESCE((SELECT GROUP_CONCAT(st_multi.name ORDER BY st_multi.name SEPARATOR ", ")
                         FROM service_record_types srt_multi
                         JOIN service_types st_multi ON st_multi.id = srt_multi.service_type_id
                         WHERE srt_multi.service_record_id = sr.id), st.name) AS service_name
        FROM service_records sr
        JOIN vehicles v ON v.id = sr.vehicle_id
        JOIN customers c ON c.id = v.customer_id
        LEFT JOIN service_types st ON st.id = sr.service_type_id
        LEFT JOIN payments p ON p.service_record_id = sr.id ';
$sql .= ' ORDER BY sr.service_date DESC, sr.id DESC';
$statement = $pdo->query($sql);
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
            'SELECT sr.id, sr.vehicle_id, sr.service_type_id, sr.description, sr.status, sr.service_date, sr.estimated_done, sr.completed_at, sr.cost,
                    COALESCE(p.status, "Belum Dibayar") AS payment_status,
                    v.plate_number, c.name AS customer_name,
                    COALESCE((SELECT GROUP_CONCAT(st_multi.name ORDER BY st_multi.name SEPARATOR ", ")
                              FROM service_record_types srt_multi
                              JOIN service_types st_multi ON st_multi.id = srt_multi.service_type_id
                              WHERE srt_multi.service_record_id = sr.id), st.name) AS service_name
             FROM service_records sr
             JOIN vehicles v ON v.id = sr.vehicle_id
             JOIN customers c ON c.id = v.customer_id
             LEFT JOIN service_types st ON st.id = sr.service_type_id
             LEFT JOIN payments p ON p.service_record_id = sr.id
             WHERE sr.id = :id'
        );
        $find->execute(['id' => $editId]);
        $editing = $find->fetch() ?: null;
    }
}

/** Jenis servis yang terpilih saat catatan diedit, termasuk catatan lama. */
$editingTypeIds = [];
$editingCustomerId = 0;
if ($editing) {
    foreach ($vehicles as $vehicle) {
        if ((int) $vehicle['id'] === (int) $editing['vehicle_id']) {
            $editingCustomerId = (int) $vehicle['customer_id'];
            break;
        }
    }
    $selectedTypes = $pdo->prepare('SELECT service_type_id FROM service_record_types WHERE service_record_id = :id');
    $selectedTypes->execute(['id' => (int) $editing['id']]);
    $editingTypeIds = array_map('intval', $selectedTypes->fetchAll(PDO::FETCH_COLUMN));
    if (!$editingTypeIds && !empty($editing['service_type_id'])) {
        $editingTypeIds = [(int) $editing['service_type_id']];
    }
}

/** Catatan perkembangan milik servis yang sedang diedit + catatan yang ingin diubah. */
$progressNotes = $editing ? service_progress_notes($pdo, (int) $editing['id']) : [];
$editingProgress = null;
if ($editing && isset($_GET['edit_progress'])) {
    foreach ($progressNotes as $progressRow) {
        if ((int) $progressRow['id'] === (int) $_GET['edit_progress']) {
            $editingProgress = $progressRow;
            break;
        }
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
<div class="dashboard-content service-management-page">
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
    <section class="admin-section service-entry-section" id="form-servis">
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

                <div class="service-selection-grid">
                    <div class="service-vehicle-field">
                        <div class="service-field">
                            <label for="service-customer">PELANGGAN</label>
                            <select class="text-input" id="service-customer" data-service-customer>
                                <option value="">Pilih pelanggan (opsional)</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?= (int) $customer['id'] ?>" <?= $editingCustomerId === (int) $customer['id'] ? 'selected' : '' ?>>
                                        <?= e($customer['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="service-field">
                            <label for="service-vehicle">NOMOR POLISI <span aria-hidden="true">*</span></label>
                            <select class="text-input" id="service-vehicle" name="vehicle_id" data-service-vehicle required>
                                <option value="">Pilih nomor polisi</option>
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <option value="<?= (int) $vehicle['id'] ?>" data-customer-id="<?= (int) $vehicle['customer_id'] ?>" <?= $editing && (int) $editing['vehicle_id'] === (int) $vehicle['id'] ? 'selected' : '' ?>>
                                        <?= e($vehicle['plate_number']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <fieldset class="service-type-options" data-service-type-options>
                        <legend>JENIS SERVIS *</legend>
                        <?php foreach ($serviceTypes as $type): ?>
                            <label class="service-type-option">
                                <input type="checkbox" name="service_type_ids[]" value="<?= (int) $type['id'] ?>" data-service-price="<?= e($type['base_price']) ?>" <?= in_array((int) $type['id'], $editingTypeIds, true) ? 'checked' : '' ?>>
                                <span><?= e($type['name']) ?></span>
                                <strong><?= rupiah($type['base_price']) ?></strong>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                </div>

                <div class="form-grid">
                    <div class="service-field">
                        <label>TANGGAL SERVIS</label>
                        <input class="text-input" type="date" name="service_date" value="<?= e($editing['service_date'] ?? date('Y-m-d')) ?>" required>
                    </div>
                    <div class="service-field">
                        <label>PERKIRAAN SELESAI</label>
                        <input class="text-input" type="datetime-local" name="estimated_done" value="<?= e($editing && $editing['estimated_done'] ? date('Y-m-d\TH:i', strtotime($editing['estimated_done'])) : '') ?>">
                        <small class="text-muted">Kosongkan bila waktu selesai belum dapat dipastikan.</small>
                    </div>
                    <div class="service-field service-total-field">
                        <label for="service-total">TOTAL BIAYA</label>
                        <input class="text-input" id="service-total" type="text" value="<?= e(rupiah($editing['cost'] ?? 0)) ?>" data-service-total readonly>
                        <small class="text-muted">Otomatis dihitung dari harga jenis servis yang dipilih.</small>
                    </div>
                </div>

                <div class="service-field">
                    <label>CATATAN / KELUHAN PEKERJAAN</label>
                    <textarea class="text-input" name="description" rows="2" placeholder="Contoh: rem dek bunyi, ganti kampas"><?= e($editing['description'] ?? '') ?></textarea>
                </div>

                <button class="button button-primary" type="submit"><?= $editing ? 'Simpan perubahan' : 'Simpan servis' ?> <?= icon('arrow-right') ?></button>
            </form>
        <?php endif; ?>
    </section>

    <?php if ($editing): ?>
    <!-- Catatan perkembangan servis (US-06) -->
    <section class="admin-section" id="perkembangan">
        <div class="section-heading">
            <div><span class="eyebrow">PERKEMBANGAN PENGERJAAN</span><h2>Catatan perkembangan servis</h2></div>
            <span class="count-chip"><?= count($progressNotes) ?> catatan</span>
        </div>
        <p class="text-muted" style="margin-top:0">
            Catatan ini terlihat pelanggan pada halaman cek status / portal pelanggan kendaraan
            <strong><?= e($editing['plate_number']) ?></strong>. Tuliskan hasil pemeriksaan, tindakan
            perbaikan, atau kendala pengerjaan.
        </p>

        <form class="admin-form form-card" method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= $editingProgress ? 'update_progress' : 'save_progress' ?>">
            <input type="hidden" name="service_id" value="<?= (int) $editing['id'] ?>">
            <?php if ($editingProgress): ?><input type="hidden" name="progress_id" value="<?= (int) $editingProgress['id'] ?>"><?php endif; ?>
            <div class="form-grid">
                <div>
                    <label>JENIS CATATAN</label>
                    <select class="text-input" name="note_type">
                        <?php foreach (progress_note_types() as $type): ?>
                            <option <?= ($editingProgress['note_type'] ?? 'Pemeriksaan') === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <label>ISI CATATAN *</label>
            <textarea class="text-input" name="note" rows="2" placeholder="Contoh: kampas rem depan aus, menunggu penggantian" required><?= e($editingProgress['note'] ?? '') ?></textarea>
            <button class="button button-primary" type="submit"><?= $editingProgress ? 'Simpan perubahan' : 'Tambah catatan' ?> <?= icon('arrow-right') ?></button>
            <?php if ($editingProgress): ?><a class="button button-ghost" href="servis.php?edit=<?= (int) $editing['id'] ?>#perkembangan">Batal edit</a><?php endif; ?>
        </form>

        <?php if (!$progressNotes): ?>
            <div class="empty-state">Belum ada catatan perkembangan untuk servis ini.</div>
        <?php else: ?>
            <div class="history-list" style="margin-top:18px">
                <?php foreach ($progressNotes as $note): ?>
                    <article class="history-item">
                        <div class="history-date"><?= e(date('d M Y', strtotime($note['created_at']))) ?></div>
                        <div class="history-details">
                            <h4><span class="status-badge status-info"><?= e($note['note_type']) ?></span></h4>
                            <p><?= e($note['note']) ?></p>
                            <small class="table-sub">Diperbarui <?= e(format_datetime($note['updated_at'])) ?></small>
                        </div>
                        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                            <a class="text-button" href="servis.php?edit=<?= (int) $editing['id'] ?>&edit_progress=<?= (int) $note['id'] ?>#perkembangan"><?= icon('pencil') ?> Edit</a>
                            <form method="post" data-confirm="Hapus catatan perkembangan ini?">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete_progress">
                                <input type="hidden" name="service_id" value="<?= (int) $editing['id'] ?>">
                                <input type="hidden" name="progress_id" value="<?= (int) $note['id'] ?>">
                                <button class="text-button" type="submit" style="color:var(--red)"><?= icon('trash-2') ?> Hapus</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- Filter -->
    <section class="admin-section service-records-section">
        <div class="section-heading">
            <div><span class="eyebrow">DAFTAR SERVIS</span><h2>Semua catatan servis</h2></div>
            <span class="count-chip"><?= count($records) ?> catatan</span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>TANGGAL</th>
                        <th>KENDARAAN / PELANGGAN</th>
                        <th>PEKERJAAN</th>
                        <th>BIAYA</th>
                        <th>PEMBAYARAN</th>
                        <th>STATUS</th>
                        <th>AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td><?= e(date('d M Y', strtotime($record['service_date']))) ?><?php if ($record['estimated_done']): ?><small class="table-sub">Est. <?= e(date('d M Y H:i', strtotime($record['estimated_done']))) ?></small><?php endif; ?></td>
                            <td><strong><?= e($record['plate_number']) ?></strong><small class="table-sub"><?= e($record['customer_name']) ?></small></td>
                            <td><?= e($record['service_name'] ?: 'Servis') ?><?php if ($record['description']): ?><small class="table-sub"><?= e($record['description']) ?></small><?php endif; ?></td>
                            <td><?= rupiah($record['cost']) ?></td>
                            <td><span class="status-badge <?= status_class($record['payment_status']) ?>"><?= e($record['payment_status']) ?></span></td>
                            <td><span class="status-badge <?= status_class($record['status']) ?>"><?= e($record['status']) ?></span></td>
                            <td>
                                <div class="service-row-actions">
                                    <a class="text-button" href="servis.php?edit=<?= (int) $record['id'] ?>#form-servis"><?= icon('pencil') ?> Edit</a>
                                    <form method="post" data-confirm="Hapus catatan servis ini?">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_service">
                                        <input type="hidden" name="service_id" value="<?= (int) $record['id'] ?>">
                                        <button class="text-button service-delete-action" type="submit"><?= icon('trash-2') ?> Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$records): ?>
                        <tr><td colspan="7" class="table-empty">Belum ada catatan servis.</td></tr>
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
