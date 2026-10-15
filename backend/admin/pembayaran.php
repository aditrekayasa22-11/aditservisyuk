<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();
$proofDir = proof_storage_dir();

/**
 * Aliran berkas bukti transfer (hanya untuk admin yang sudah login).
 * Nama berkas divalidasi agar tidak bisa keluar dari folder storage/proofs.
 */
if (isset($_GET['proof'])) {
    $statement = $pdo->prepare('SELECT proof_path FROM payments WHERE id = :id');
    $statement->execute(['id' => (int) $_GET['proof']]);
    $name = (string) ($statement->fetchColumn() ?: '');
    if ($name === '' || !valid_proof_name($name) || !is_file($proofDir . '/' . $name)) {
        http_response_code(404);
        exit('Bukti transfer tidak ditemukan.');
    }
    header('Content-Type: ' . proof_content_type($name));
    header('Content-Length: ' . (string) filesize($proofDir . '/' . $name));
    header('Content-Disposition: inline; filename="' . basename($name) . '"');
    readfile($proofDir . '/' . $name);
    exit;
}

/**
 * Aksi verifikasi pembayaran (US-07):
 *  - verify : konfirmasi bukti transfer -> Lunas
 *  - reject : tolak bukti transfer -> Ditolak
 *  - reset  : kembalikan ke Belum Dibayar (mis. salah konfirmasi)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $action = (string) ($_POST['action'] ?? '');
        $paymentId = (int) ($_POST['payment_id'] ?? 0);
        if ($paymentId < 1) {
            throw new InvalidArgumentException('Pembayaran tidak ditemukan.');
        }

        if ($action === 'verify') {
            $statement = $pdo->prepare(
                "UPDATE payments
                 SET status = 'Lunas', paid_at = COALESCE(paid_at, NOW()), verified_at = NOW(), verified_by = :admin, note = NULL
                 WHERE id = :id"
            );
            $statement->execute(['admin' => (int) $_SESSION['admin_id'], 'id' => $paymentId]);
            flash('Pembayaran dikonfirmasi lunas.');
        } elseif ($action === 'reject') {
            $note = trim((string) ($_POST['note'] ?? '')) ?: null;
            $statement = $pdo->prepare(
                "UPDATE payments
                 SET status = 'Ditolak', verified_at = NOW(), verified_by = :admin, paid_at = NULL, note = :note
                 WHERE id = :id"
            );
            $statement->execute(['admin' => (int) $_SESSION['admin_id'], 'note' => $note, 'id' => $paymentId]);
            flash('Bukti pembayaran ditolak.');
        } elseif ($action === 'reset') {
            $statement = $pdo->prepare(
                "UPDATE payments
                 SET status = 'Belum Dibayar', paid_at = NULL, verified_at = NULL, verified_by = NULL, note = NULL
                 WHERE id = :id"
            );
            $statement->execute(['id' => $paymentId]);
            flash('Status pembayaran dikembalikan ke Belum Dibayar.');
        } else {
            throw new InvalidArgumentException('Aksi tidak dikenali.');
        }
    } catch (InvalidArgumentException $exception) {
        flash($exception->getMessage(), 'error');
    } catch (PDOException $exception) {
        flash('Perubahan pembayaran gagal disimpan.', 'error');
    }
    redirect('pembayaran.php');
}

/** Filter status (default: yang menunggu verifikasi). */
$filterStatus = (string) ($_GET['status'] ?? 'Menunggu Verifikasi');
$allowedStatuses = payment_statuses();

$sql = 'SELECT p.id, p.service_record_id, p.amount, p.method, p.status, p.paid_at, p.proof_path,
               p.verified_at, p.verified_by, p.note,
               a.name AS verifier_name,
               sr.service_date, sr.description,
               v.plate_number, c.name AS customer_name,
               COALESCE((SELECT GROUP_CONCAT(st_multi.name ORDER BY st_multi.name SEPARATOR ", ")
                         FROM service_record_types srt_multi
                         JOIN service_types st_multi ON st_multi.id = srt_multi.service_type_id
                         WHERE srt_multi.service_record_id = sr.id), st.name) AS service_name
        FROM payments p
        JOIN service_records sr ON sr.id = p.service_record_id
        JOIN vehicles v ON v.id = sr.vehicle_id
        JOIN customers c ON c.id = v.customer_id
        LEFT JOIN service_types st ON st.id = sr.service_type_id
        LEFT JOIN admins a ON a.id = p.verified_by';
$bindings = [];
if (in_array($filterStatus, $allowedStatuses, true)) {
    $sql .= ' WHERE p.status = :status';
    $bindings['status'] = $filterStatus;
}
$sql .= ' ORDER BY p.verified_at DESC, p.id DESC';
$statement = $pdo->prepare($sql);
$statement->execute($bindings);
$payments = $statement->fetchAll();

/** Ringkasan jumlah per status. */
$summary = array_fill_keys($allowedStatuses, 0);
foreach ($pdo->query('SELECT status, COUNT(*) AS total FROM payments GROUP BY status') as $row) {
    $summary[$row['status']] = (int) $row['total'];
}

$flashMessage = take_flash();
$pageTitle = 'Verifikasi Pembayaran';
$adminView = true;
$currentPage = 'pembayaran';
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<div class="dashboard-content">
    <div class="dashboard-topline">
        <div>
            <span class="eyebrow">VERIFIKASI PEMBAYARAN</span>
            <h1>Pembayaran Servis</h1>
            <p>Periksa bukti transfer pelanggan, lalu konfirmasi atau tolak pembayaran.</p>
        </div>
        <span class="date-chip"><?= $summary['Menunggu Verifikasi'] ?> menunggu verifikasi</span>
    </div>

    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= e($flashMessage['type']) ?> alert-auto"><?= e($flashMessage['message']) ?></div>
    <?php endif; ?>

    <section class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
        <article class="stat-card accent-amber"><span class="stat-icon icon-amber"><?= icon('clock') ?></span><span class="stat-label">MENUNGGU VERIFIKASI</span><strong><?= $summary['Menunggu Verifikasi'] ?></strong><small>Bukti perlu dicek</small></article>
        <article class="stat-card accent-rose"><span class="stat-icon icon-orange"><?= icon('triangle-alert') ?></span><span class="stat-label">BELUM DIBAYAR</span><strong><?= $summary['Belum Dibayar'] ?></strong><small>Tanpa bukti transfer</small></article>
        <article class="stat-card accent-green"><span class="stat-icon icon-green"><?= icon('circle-check') ?></span><span class="stat-label">LUNAS</span><strong><?= $summary['Lunas'] ?></strong><small>Sudah dikonfirmasi</small></article>
        <article class="stat-card accent-navy"><span class="stat-icon icon-navy"><?= icon('circle-alert') ?></span><span class="stat-label">DITOLAK</span><strong><?= $summary['Ditolak'] ?></strong><small>Bukti tidak sesuai</small></article>
    </section>

    <section class="admin-section">
        <div class="section-heading">
            <div><span class="eyebrow">DAFTAR PEMBAYARAN</span><h2>Daftar pembayaran</h2></div>
            <span class="count-chip"><?= count($payments) ?> data</span>
        </div>

        <form class="filter-bar" method="get">
            <div class="filter-item">
                <label for="status">STATUS PEMBAYARAN</label>
                <select class="text-input" id="status" name="status" onchange="this.form.submit()">
                    <option value="">Semua status</option>
                    <?php foreach ($allowedStatuses as $status): ?>
                        <option <?= $filterStatus === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <noscript><button class="button button-navy button-sm" type="submit">Filter</button></noscript>
            <a class="button button-ghost button-sm" href="pembayaran.php">Reset</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>KENDARAAN / PELANGGAN</th>
                        <th>PEKERJAAN</th>
                        <th>JUMLAH</th>
                        <th>METODE</th>
                        <th>STATUS</th>
                        <th>BUKTI</th>
                        <th>AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><strong><?= e($payment['plate_number']) ?></strong><small class="table-sub"><?= e($payment['customer_name']) ?></small></td>
                            <td><?= e($payment['service_name'] ?: 'Servis') ?><small class="table-sub"><?= e(date('d M Y', strtotime($payment['service_date']))) ?></small></td>
                            <td><?= rupiah($payment['amount']) ?></td>
                            <td><?= e($payment['method']) ?></td>
                            <td>
                                <span class="status-badge <?= status_class($payment['status']) ?>"><?= e($payment['status']) ?></span>
                                <?php if ($payment['verified_at']): ?><small class="table-sub"><?= e($payment['verifier_name'] ?: 'Admin') ?> &middot; <?= e(format_datetime($payment['verified_at'])) ?></small><?php endif; ?>
                                <?php if ($payment['note']): ?><small class="table-sub">Catatan: <?= e($payment['note']) ?></small><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($payment['proof_path']): ?>
                                    <a class="text-button" href="pembayaran.php?proof=<?= (int) $payment['id'] ?>" target="_blank" rel="noopener"><?= icon('eye') ?> Lihat</a>
                                <?php else: ?>
                                    <span class="text-muted">Tidak ada</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                                    <?php if (in_array($payment['status'], ['Menunggu Verifikasi', 'Belum Dibayar', 'Ditolak'], true)): ?>
                                        <form method="post" data-confirm="Konfirmasi pembayaran ini sebagai LUNAS?">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="verify">
                                            <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                                            <button class="text-button" type="submit" style="color:var(--green-600)"><?= icon('check') ?> Konfirmasi</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (in_array($payment['status'], ['Menunggu Verifikasi', 'Lunas'], true)): ?>
                                        <form method="post" data-confirm="Tolak bukti pembayaran ini?">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                                            <input type="hidden" name="note" value="Bukti transfer tidak sesuai.">
                                            <button class="text-button" type="submit" style="color:var(--red)"><?= icon('circle-alert') ?> Tolak</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($payment['status'] === 'Lunas'): ?>
                                        <form method="post" data-confirm="Kembalikan status pembayaran ke Belum Dibayar?">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="reset">
                                            <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                                            <button class="text-button" type="submit"><?= icon('refresh-cw') ?> Reset</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$payments): ?>
                        <tr><td colspan="7" class="table-empty">Tidak ada pembayaran<?= $filterStatus ? ' berstatus "' . e($filterStatus) . '"' : '' ?>.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="privacy-note"><?= icon('shield-check') ?> Bukti transfer disimpan di server dan hanya dapat dibuka oleh admin yang sudah login.</div>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
