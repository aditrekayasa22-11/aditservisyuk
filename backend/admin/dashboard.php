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
            $allowedStatuses = ['Menunggu', 'Dikerjakan', 'Selesai', 'Dibatalkan'];
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

/** Statistik ringkas untuk kartu di bagian atas. */
$counts = [
    'customers' => (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
    'vehicles' => (int) $pdo->query('SELECT COUNT(*) FROM vehicles')->fetchColumn(),
    'active' => (int) $pdo->query("SELECT COUNT(*) FROM service_records WHERE status IN ('Menunggu','Dikerjakan')")->fetchColumn(),
    'unpaid' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'Belum Dibayar'")->fetchColumn(),
    'done_today' => (int) $pdo->query("SELECT COUNT(*) FROM service_records WHERE status = 'Selesai' AND service_date = CURDATE()")->fetchColumn(),
    'income_today' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'Lunas' AND DATE(paid_at) = CURDATE()")->fetchColumn(),
];

/** Antrean servis: menunggu dan sedang dikerjakan, terbaru di atas. */
$queue = $pdo->query(
    "SELECT sr.id, sr.service_date, sr.status, sr.cost, sr.description,
            v.plate_number, v.brand, v.model, c.name AS customer_name, c.phone,
            st.name AS service_name
     FROM service_records sr
     JOIN vehicles v ON v.id = sr.vehicle_id
     JOIN customers c ON c.id = v.customer_id
     LEFT JOIN service_types st ON st.id = sr.service_type_id
     WHERE sr.status IN ('Menunggu','Dikerjakan')
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
$statusTotals = array_fill_keys(['Menunggu', 'Dikerjakan', 'Selesai', 'Dibatalkan'], 0);
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
    <div class="dashboard-topline">
        <div>
            <span class="eyebrow">PUSAT KONTROL</span>
            <h1>Dashboard</h1>
            <p>Kelola operasional bengkel ServisYuk hari ini.</p>
        </div>
        <span class="date-chip"><?= e(date('d M Y')) ?></span>
    </div>

    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= e($flashMessage['type']) ?> alert-auto"><?= e($flashMessage['message']) ?></div>
    <?php endif; ?>

    <section class="stat-grid">
        <article class="stat-card"><span class="stat-icon icon-orange"><?= icon('users') ?></span><span class="stat-label">PELANGGAN</span><strong><?= $counts['customers'] ?></strong><small>Terdaftar di sistem</small></article>
        <article class="stat-card"><span class="stat-icon icon-navy"><?= icon('car') ?></span><span class="stat-label">KENDARAAN</span><strong><?= $counts['vehicles'] ?></strong><small>Nomor polisi terdaftar</small></article>
        <article class="stat-card"><span class="stat-icon icon-amber"><?= icon('wrench') ?></span><span class="stat-label">SERVIS AKTIF</span><strong><?= $counts['active'] ?></strong><small>Menunggu atau dikerjakan</small></article>
        <article class="stat-card"><span class="stat-icon icon-green"><?= icon('circle-check') ?></span><span class="stat-label">SELESAI HARI INI</span><strong><?= $counts['done_today'] ?></strong><small>Pekerjaan tuntas</small></article>
        <article class="stat-card"><span class="stat-icon icon-green"><?= icon('wallet') ?></span><span class="stat-label">PENDAPATAN HARI INI</span><strong class="stat-money"><?= rupiah($counts['income_today']) ?></strong><small>Pembayaran lunas</small></article>
        <article class="stat-card"><span class="stat-icon icon-orange"><?= icon('triangle-alert') ?></span><span class="stat-label">TAGIHAN TERTUNGGAK</span><strong class="stat-money"><?= rupiah($counts['unpaid']) ?></strong><small>Belum dibayar</small></article>
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
                                <td><?= e(date('d M Y', strtotime($item['service_date']))) ?></td>
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
                                            <?php foreach (['Menunggu', 'Dikerjakan', 'Selesai', 'Dibatalkan'] as $status): ?>
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
            <?php foreach ($statusTotals as $statusName => $total): ?>
                <div style="margin-bottom:14px">
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:5px">
                        <span><?= e($statusName) ?></span><strong><?= $total ?></strong>
                    </div>
                    <div style="height:9px;background:var(--paper);border-radius:999px;overflow:hidden">
                        <div style="height:100%;width:<?= (int) round($total / $maxStatus * 100) ?>%;background:var(--orange-500);border-radius:999px;transition:width .4s ease"></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (array_sum($statusTotals) === 0): ?>
                <div class="empty-state">Belum ada catatan servis.</div>
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
                <div class="history-list">
                    <?php foreach ($recent as $item): ?>
                        <article class="history-item" style="grid-template-columns:1fr auto">
                            <div class="history-details">
                                <h4><?= e($item['plate_number']) ?> &middot; <?= e($item['service_name'] ?: 'Servis') ?></h4>
                                <p><?= e($item['customer_name']) ?> &middot; <?= e(date('d M Y', strtotime($item['service_date']))) ?></p>
                            </div>
                            <span class="status-badge <?= status_class($item['status']) ?>"><?= e($item['status']) ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
