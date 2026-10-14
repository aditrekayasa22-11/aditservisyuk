<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

/**
 * Aksi di halaman riwayat: atur PIN akses kendaraan.
 * PIN membuat pelanggan wajib memasukkan PIN sebelum melihat riwayat servis,
 * sehingga data pribadi pemilik tidak dibuka hanya dengan nomor polisi.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $action = (string) ($_POST['action'] ?? '');
        $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
        $vehicle = null;
        if ($vehicleId > 0) {
            $find = $pdo->prepare('SELECT id, plate_number FROM vehicles WHERE id = :id');
            $find->execute(['id' => $vehicleId]);
            $vehicle = $find->fetch() ?: null;
        }
        if (!$vehicle) {
            throw new InvalidArgumentException('Kendaraan tidak ditemukan.');
        }

        if ($action === 'set_pin') {
            $pin = trim((string) ($_POST['pin'] ?? ''));
            $days = (int) ($_POST['pin_days'] ?? 0);
            if (!preg_match('/^\d{4,6}$/', $pin)) {
                throw new InvalidArgumentException('PIN harus terdiri dari 4 sampai 6 digit angka.');
            }
            $expiresAt = $days > 0 ? date('Y-m-d', strtotime('+' . $days . ' days')) : null;
            save_vehicle_pin($pdo, $vehicleId, $pin, $expiresAt);
            flash('PIN akses untuk kendaraan ' . $vehicle['plate_number'] . ' berhasil disimpan. Berikan PIN tersebut kepada pemilik kendaraan.');
        } elseif ($action === 'delete_pin') {
            delete_vehicle_pin($pdo, $vehicleId);
            flash('PIN akses kendaraan ' . $vehicle['plate_number'] . ' dihapus. Riwayat kini dapat dibuka hanya dengan nomor polisi.');
        } else {
            throw new InvalidArgumentException('Aksi tidak dikenali.');
        }
    } catch (InvalidArgumentException $exception) {
        flash($exception->getMessage(), 'error');
    } catch (PDOException $exception) {
        flash('Gagal menyimpan PIN. Pastikan tabel service_pins sudah dibuat (jalankan database_upgrade.sql).', 'error');
    }
    redirect('riwayat.php');
}

/** Daftar semua kendaraan + status PIN untuk dipilih di dropdown. */
$vehicles = $pdo->query(
    'SELECT v.id, v.plate_number, v.brand, v.model, c.name AS customer_name, c.phone,
            (SELECT COUNT(*) FROM service_records sr WHERE sr.vehicle_id = v.id) AS service_count,
            (SELECT COUNT(*) FROM service_pins sp
              WHERE sp.vehicle_id = v.id AND (sp.expires_at IS NULL OR sp.expires_at >= CURDATE())) AS has_pin
     FROM vehicles v
     JOIN customers c ON c.id = v.customer_id
     ORDER BY v.plate_number'
)->fetchAll();

/** Kendaraan yang sedang dilihat riwayatnya (default: kendaraan pertama). */
$selectedId = (int) ($_GET['vehicle_id'] ?? 0);
$selected = null;
foreach ($vehicles as $vehicle) {
    if ((int) $vehicle['id'] === $selectedId) {
        $selected = $vehicle;
        break;
    }
}
if (!$selected && $vehicles) {
    $selected = $vehicles[0];
    $selectedId = (int) $selected['id'];
}

$services = [];
if ($selected) {
    $services = vehicle_services($pdo, $selectedId);
}

/** Total biaya kumulatif kendaraan terpilih. */
$totalCost = 0.0;
foreach ($services as $service) {
    $totalCost += (float) $service['cost'];
}

$pinInfo = null;
if ($selected) {
    $find = $pdo->prepare(
        'SELECT expires_at FROM service_pins
         WHERE vehicle_id = :vehicle_id
           AND (expires_at IS NULL OR expires_at >= CURDATE())
         LIMIT 1'
    );
    $find->execute(['vehicle_id' => $selectedId]);
    $pinInfo = $find->fetch() ?: null;
}

$flashMessage = take_flash();
$pageTitle = 'Riwayat Servis';
$adminView = true;
$currentPage = 'riwayat';
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<div class="dashboard-content">
    <div class="dashboard-topline">
        <div>
            <span class="eyebrow">RIWAYAT PELANGGAN</span>
            <h1>Riwayat Servis</h1>
            <p>Lihat seluruh riwayat servis per kendaraan dan atur PIN akses pelanggan.</p>
        </div>
    </div>

    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= e($flashMessage['type']) ?> alert-auto"><?= e($flashMessage['message']) ?></div>
    <?php endif; ?>

    <section class="admin-section">
        <div class="section-heading">
            <div><span class="eyebrow">PILIH KENDARAAN</span><h2>Cari riwayat pelanggan</h2></div>
        </div>

        <?php if (!$vehicles): ?>
            <div class="empty-state">Belum ada kendaraan terdaftar. Tambahkan pelanggan terlebih dahulu.</div>
        <?php else: ?>
            <form class="filter-bar" method="get">
                <div class="filter-item">
                    <label for="vehicle_id">KENDARAAN / PELANGGAN</label>
                    <select class="text-input" id="vehicle_id" name="vehicle_id" onchange="this.form.submit()">
                        <?php foreach ($vehicles as $vehicle): ?>
                            <option value="<?= (int) $vehicle['id'] ?>" <?= $selectedId === (int) $vehicle['id'] ? 'selected' : '' ?>>
                                <?= e($vehicle['plate_number'] . ' · ' . $vehicle['customer_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <noscript><button class="button button-navy button-sm" type="submit">Tampilkan</button></noscript>
            </form>

            <?php if ($selected): ?>
                <div class="vehicle-card">
                    <div class="vehicle-avatar"><?= icon('car') ?></div>
                    <div class="vehicle-meta">
                        <span>KENDARAAN</span>
                        <h3><?= e(trim(($selected['brand'] ?? '') . ' ' . ($selected['model'] ?? '')) ?: 'Kendaraan pelanggan') ?></h3>
                        <p>Pemilik <?= e($selected['customer_name']) ?><?= $selected['phone'] ? ' · ' . e($selected['phone']) : '' ?></p>
                    </div>
                    <span class="plate-tag"><?= e($selected['plate_number']) ?></span>
                </div>

                <div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
                    <article class="stat-card"><span class="stat-label">TOTAL SERVIS</span><strong><?= count($services) ?></strong><small>Kunjungan tercatat</small></article>
                    <article class="stat-card"><span class="stat-label">TOTAL BIAYA</span><strong class="stat-money"><?= rupiah($totalCost) ?></strong><small>Akumulasi biaya servis</small></article>
                    <article class="stat-card"><span class="stat-label">PIN AKSES</span><strong style="font-size:20px"><?= $pinInfo ? 'Aktif' : 'Tidak aktif' ?></strong><small><?= $pinInfo ? ($pinInfo['expires_at'] ? 'Berlaku s/d ' . e(date('d M Y', strtotime($pinInfo['expires_at']))) : 'Tanpa batas waktu') : 'Dibuka dengan nomor polisi' ?></small></article>
                </div>

                <div class="history-heading"><h3>Riwayat servis</h3><span><?= count($services) ?> catatan</span></div>
                <?php if (!$services): ?>
                    <div class="empty-state">Kendaraan ini belum memiliki catatan servis.</div>
                <?php else: ?>
                    <div class="history-list">
                        <?php foreach ($services as $service): ?>
                            <article class="history-item">
                                <div class="history-date"><?= e(date('d M Y', strtotime($service['service_date']))) ?></div>
                                <div class="history-details">
                                    <h4><?= e($service['service_name'] ?: 'Servis kendaraan') ?></h4>
                                    <p><?= e($service['description'] ?: 'Tidak ada keterangan tambahan.') ?></p>
                                </div>
                                <span class="status-badge <?= status_class($service['status']) ?>"><?= e($service['status']) ?></span>
                                <div class="history-cost"><?= rupiah($service['cost']) ?><small><?= e($service['payment_status'] ?: 'Belum Dibayar') ?></small></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if ($selected): ?>
    <section class="admin-section">
        <div class="section-heading">
            <div><span class="eyebrow">KEAMANAN DATA</span><h2>PIN akses kendaraan</h2></div>
        </div>
        <p class="text-muted" style="margin-bottom:16px">
            Jika PIN diaktifkan, pelanggan yang mengetik nomor polisi <strong><?= e($selected['plate_number']) ?></strong>
            harus memasukkan PIN ini sebelum riwayat servis ditampilkan. Ini melindungi data pribadi pemilik kendaraan.
        </p>

        <form class="admin-form form-card" method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="vehicle_id" value="<?= (int) $selected['id'] ?>">
            <div class="form-grid">
                <div>
                    <label>PIN BARU (4-6 DIGIT)</label>
                    <input class="text-input" name="pin" inputmode="numeric" pattern="\d{4,6}" maxlength="6" placeholder="Contoh: 4829" required>
                </div>
                <div>
                    <label>BERLAKU SELAMA</label>
                    <select class="text-input" name="pin_days">
                        <option value="0">Tanpa batas waktu</option>
                        <option value="7">7 hari</option>
                        <option value="30">30 hari</option>
                        <option value="90">90 hari</option>
                    </select>
                </div>
            </div>
            <button class="button button-primary" type="submit" name="action" value="set_pin">Simpan PIN <?= icon('arrow-right') ?></button>
            <?php if ($pinInfo): ?>
                <button class="button button-danger" type="submit" name="action" value="delete_pin" data-confirm="Nonaktifkan PIN kendaraan ini?">Nonaktifkan PIN</button>
            <?php endif; ?>
        </form>
    </section>
    <?php endif; ?>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
