<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

/**
 * Aksi di dashboard: memperbarui status servis langsung dari daftar antrean.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'update_service_status') {
            $allowedStatuses = service_statuses();
            $status = (string) ($_POST['status'] ?? '');
            if (!in_array($status, $allowedStatuses, true)) {
                throw new InvalidArgumentException('Status servis tidak valid.');
            }
            $statement = $pdo->prepare('UPDATE service_records SET status = :status WHERE id = :id');
            $statement->execute(['status' => $status, 'id' => (int) ($_POST['service_id'] ?? 0)]);
            flash('Status servis berhasil diperbarui.');
        } else {
            throw new InvalidArgumentException('Aksi tidak dikenali.');
        }
    } catch (InvalidArgumentException $exception) {
        flash($exception->getMessage(), 'error');
    } catch (PDOException $exception) {
        flash('Perubahan gagal disimpan.', 'error');
    }
    redirect('dashboard.php');
}

/** Statistik ringkas untuk kartu di bagian atas (US-02). */
$counts = [
    'customers' => (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
    'vehicles' => (int) $pdo->query('SELECT COUNT(*) FROM vehicles')->fetchColumn(),
    'checking' => (int) $pdo->query("SELECT COUNT(*) FROM service_records WHERE status = 'Menunggu Pemeriksaan'")->fetchColumn(),
    'in_progress' => (int) $pdo->query("SELECT COUNT(*) FROM service_records WHERE status = 'Sedang Diperbaiki'")->fetchColumn(),
    'waiting_parts' => (int) $pdo->query("SELECT COUNT(*) FROM service_records WHERE status = 'Menunggu Suku Cadang'")->fetchColumn(),
    'ready' => (int) $pdo->query("SELECT COUNT(*) FROM service_records WHERE status = 'Siap Diambil'")->fetchColumn(),
    'unpaid' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status <> 'Lunas'")->fetchColumn(),
    'pending_payments' => (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'Menunggu Verifikasi'")->fetchColumn(),
    'income_today' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'Lunas' AND DATE(paid_at) = CURDATE()")->fetchColumn(),
];

/** Barang dengan stok menipis (<= 5) untuk peringatan cepat. */
$lowStock = $pdo->query(
    'SELECT name, quantity, unit FROM inventory WHERE quantity <= 5 ORDER BY quantity ASC, name ASC LIMIT 5'
)->fetchAll();

/** Antrean servis: pekerjaan yang belum selesai, terbaru di atas. */
$queue = $pdo->query(
    "SELECT sr.id, sr.service_date, sr.status, sr.cost, sr.description, sr.estimated_done,
            v.plate_number, v.brand, v.model, c.name AS customer_name, c.phone,
            st.name AS service_name
     FROM service_records sr
     JOIN vehicles v ON v.id = sr.vehicle_id
     JOIN customers c ON c.id = v.customer_id
     LEFT JOIN service_types st ON st.id = sr.service_type_id
     WHERE sr.status IN ('Menunggu Pemeriksaan','Sedang Diperbaiki','Menunggu Suku Cadang')
     ORDER BY sr.service_date ASC, sr.id ASC"
)->fetchAll();

/** Aktivitas terakhir. */
$recent = $pdo->query(
    "SELECT sr.id, sr.service_date, sr.status, sr.cost,
            v.plate_number, c.name AS customer_name, st.name AS service_name
     FROM service_records sr
     JOIN vehicles v ON v.id = sr.vehicle_id
     JOIN customers c ON c.id = v.customer_id
     LEFT JOIN service_types st ON st.id = sr.service_type_id
     ORDER BY sr.service_date DESC, sr.id DESC
     LIMIT 6"
)->fetchAll();

/** Distribusi status untuk ditampilkan sebagai batang sederhana. */
$statusRows = $pdo->query(
    "SELECT status, COUNT(*) AS total FROM service_records GROUP BY status"
)->fetchAll();
$statusTotals = array_fill_keys(service_statuses(), 0);
foreach ($statusRows as $row) {
    $statusTotals[$row['status']] = (int) $row['total'];
}
$maxStatus = max(1, max($statusTotals));

$flashMessage = take_flash();

$pageTitle = 'Dashboard Admin';
$adminView = true;
$currentPage = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<div class="dashboard-content">
    <div class="admin-hero">
        <div class="admin-hero-copy">
            <span class="eyebrow"><span class="eyebrow-dot"></span> PUSAT KONTROL</span>
            <h1>Halo, <?= e($_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin') ?></h1>
            <p>Kelola operasional bengkel <?= APP_NAME ?> — pantau antrean servis, stok barang, dan pembayaran dalam satu tempat.</p>
            <div class="hero-clock" style="margin-top:12px"><?= icon('clock') ?> <?= e(date('l, d F Y')) ?></div>
        </div>
        <div class="admin-hero-actions">
            <a class="button button-primary" href="servis.php"><?= icon('plus') ?> Catat servis</a>
            <a class="button button-ghost" href="pelanggan.php"><?= icon('user-plus') ?> Pelanggan baru</a>
            <a class="button button-ghost" href="stok.php"><?= icon('package') ?> Cek stok</a>
        </div>
    </div>

    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= e($flashMessage['type']) ?> alert-auto"><?= e($flashMessage['message']) ?></div>
    <?php endif; ?>

    <?php if ($lowStock): ?>
        <div class="alert alert-warning">
            <?= icon('triangle-alert') ?>
            <div>
                <strong>Stok menipis:</strong>
                <?= e(implode(', ', array_map(static fn (array $item): string => $item['name'] . ' (' . (int) $item['quantity'] . ' ' . $item['unit'] . ')', $lowStock))) ?>.
                <a href="stok.php">Kelola stok</a>
            </div>
        </div>
    <?php endif; ?>

    <section class="stat-grid">
        <article class="stat-card accent-navy"><span class="stat-icon icon-navy"><?= icon('bike') ?></span><span class="stat-label">KENDARAAN</span><strong><?= $counts['vehicles'] ?></strong><small>Nomor polisi terdaftar</small></article>
        <article class="stat-card accent-amber"><span class="stat-icon icon-amber"><?= icon('clipboard-list') ?></span><span class="stat-label">MENUNGGU PEMERIKSAAN</span><strong><?= $counts['checking'] ?></strong><small>Belum mulai dikerjakan</small></article>
        <article class="stat-card accent-orange"><span class="stat-icon icon-orange"><?= icon('wrench') ?></span><span class="stat-label">SEDANG DISERVIS</span><strong><?= $counts['in_progress'] ?></strong><small>Sedang diperbaiki</small></article>
        <article class="stat-card accent-green"><span class="stat-icon icon-green"><?= icon('circle-check') ?></span><span class="stat-label">SIAP DIAMBIL</span><strong><?= $counts['ready'] ?></strong><small>Pekerjaan selesai</small></article>
        <article class="stat-card accent-rose"><span class="stat-icon icon-orange"><?= icon('triangle-alert') ?></span><span class="stat-label">TAGIHAN BELUM LUNAS</span><strong class="stat-money"><?= rupiah($counts['unpaid']) ?></strong><small>Belum / menunggu verifikasi</small></article>
        <article class="stat-card accent-amber"><span class="stat-icon icon-amber"><?= icon('wallet') ?></span><span class="stat-label">MENUNGGU VERIFIKASI</span><strong><?= $counts['pending_payments'] ?></strong><small>Bukti transfer perlu dicek</small></article>
    </section>

    <section class="admin-section">
        <div class="section-heading">
            <div><span class="eyebrow">ANTREAN SERVIS</span><h2>Daftar antrean hari ini</h2></div>
            <span class="count-chip"><?= count($queue) ?> antrean</span>
        </div>

        <?php if (!$queue): ?>
            <div class="empty-state">Tidak ada servis dalam antrean. Semua pekerjaan sudah selesai.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>TANGGAL</th>
                            <th>KENDARAAN / PELANGGAN</th>
                            <th>PEKERJAAN</th>
                            <th>KONTAK</th>
                            <th>BIAYA</th>
                            <th>STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($queue as $item): ?>
                            <tr>
                                <td><?= e(date('d M Y', strtotime($item['service_date']))) ?><?php if ($item['estimated_done']): ?><small class="table-sub">Est. <?= e(date('d M Y H:i', strtotime($item['estimated_done']))) ?></small><?php endif; ?></td>
                                <td>
                                    <strong><?= e($item['plate_number']) ?></strong>
                                    <small class="table-sub"><?= e(trim(($item['brand'] ?? '') . ' ' . ($item['model'] ?? ''))) ?></small>
                                    <small class="table-sub"><?= e($item['customer_name']) ?></small>
                                </td>
                                <td>
                                    <?= e($item['service_name'] ?: 'Servis') ?>
                                    <?php if ($item['description']): ?><small class="table-sub"><?= e($item['description']) ?></small><?php endif; ?>
                                </td>
                                <td><?= e($item['phone'] ?: '—') ?></td>
                                <td><?= rupiah($item['cost']) ?></td>
                                <td>
                                    <form method="post" class="inline-status">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="update_service_status">
                                        <input type="hidden" name="service_id" value="<?= (int) $item['id'] ?>">
                                        <select class="status-select <?= status_class($item['status']) ?>" name="status" onchange="this.form.submit()">
                                            <?php foreach (service_statuses() as $status): ?>
                                                <option <?= $item['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <div class="split-forms">
        <section class="admin-section" style="margin-bottom:0">
            <div class="section-heading">
                <div><span class="eyebrow">DISTRIBUSI STATUS</span><h2>Komposisi pekerjaan</h2></div>
            </div>
            <?php if (array_sum($statusTotals) === 0): ?>
                <div class="empty-state">Belum ada catatan servis.</div>
            <?php else: ?>
                <div class="meter-list">
                    <?php foreach ($statusTotals as $statusName => $total): ?>
                        <?php
                        $meterClass = match ($statusName) {
                            'Menunggu Pemeriksaan', 'Menunggu Suku Cadang' => 'meter-amber',
                            'Sedang Diperbaiki' => 'meter-navy',
                            'Selesai', 'Siap Diambil' => 'meter-green',
                            'Dibatalkan' => 'meter-rose',
                            default => '',
                        };
                        ?>
                        <div>
                            <div class="meter-top">
                                <span><?= e($statusName) ?></span>
                                <strong><?= $total ?></strong>
                            </div>
                            <div class="meter <?= $meterClass ?>">
                                <span style="width:<?= (int) round($total / $maxStatus * 100) ?>%"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="admin-section" style="margin-bottom:0">
            <div class="section-heading">
                <div><span class="eyebrow">AKTIVITAS TERAKHIR</span><h2>Servis terbaru</h2></div>
                <a class="text-button" href="servis.php">Kelola servis <?= icon('arrow-right') ?></a>
            </div>
            <?php if (!$recent): ?>
                <div class="empty-state">Belum ada catatan servis.</div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($recent as $item): ?>
                        <?php
                        $dotClass = match ($item['status']) {
                            'Selesai', 'Siap Diambil' => 'is-done',
                            'Menunggu Pemeriksaan', 'Sedang Diperbaiki', 'Menunggu Suku Cadang' => 'is-pending',
                            default => 'is-muted',
                        };
                        $dotIcon = match ($item['status']) {
                            'Selesai', 'Siap Diambil' => 'circle-check',
                            'Menunggu Suku Cadang' => 'package',
                            'Dibatalkan' => 'triangle-alert',
                            default => 'wrench',
                        };
                        ?>
                        <div class="timeline-item">
                            <span class="timeline-dot <?= $dotClass ?>"><?= icon($dotIcon) ?></span>
                            <div class="timeline-body">
                                <h4><?= e($item['plate_number']) ?> &middot; <?= e($item['service_name'] ?: 'Servis') ?></h4>
                                <p><?= e($item['customer_name']) ?> &middot; <?= e(date('d M Y', strtotime($item['service_date']))) ?></p>
                                <div class="timeline-meta">
                                    <span class="status-badge <?= status_class($item['status']) ?>"><?= e($item['status']) ?></span>
                                    <span class="timeline-cost"><?= rupiah($item['cost']) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
