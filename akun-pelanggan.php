<?php
declare(strict_types=1);

/**
 * Portal pelanggan — halaman utama setelah login (tanpa username/password).
 *
 * Ditampilkan: data kendaraan, keluhan/catatan pekerjaan, tanggal masuk,
 * status pengerjaan, estimasi biaya, dan riwayat servis.
 *
 * KEAMANAN DATA: seluruh query terikat customer_id dari sesi login,
 * sehingga data pribadi pelanggan lain tidak pernah bisa ikut terbaca.
 */
require_once __DIR__ . '/backend/includes/functions.php';

$customer = require_customer();

/**
 * Unggah bukti transfer pembayaran (US-11).
 * Hanya boleh untuk servis milik kendaraan pelanggan yang sedang login.
 */
if (isset($_GET['proof'])) {
    try {
        $statement = db()->prepare(
            'SELECT p.proof_path
             FROM payments p
             JOIN service_records sr ON sr.id = p.service_record_id
             JOIN vehicles v ON v.id = sr.vehicle_id
             WHERE p.id = :id AND v.customer_id = :cid'
        );
        $statement->execute(['id' => (int) $_GET['proof'], 'cid' => (int) $customer['customer_id']]);
        $name = (string) ($statement->fetchColumn() ?: '');
        if ($name === '' || !valid_proof_name($name) || !is_file(proof_storage_dir() . '/' . $name)) {
            http_response_code(404);
            exit('Bukti transfer tidak ditemukan.');
        }
        header('Content-Type: ' . proof_content_type($name));
        header('Content-Length: ' . (string) filesize(proof_storage_dir() . '/' . $name));
        header('Content-Disposition: inline; filename="' . basename($name) . '"');
        readfile(proof_storage_dir() . '/' . $name);
        exit;
    } catch (PDOException $exception) {
        error_log('[ServisYuk lihat bukti] ' . $exception->getMessage());
        http_response_code(404);
        exit('Bukti transfer tidak dapat dibuka.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if ((string) ($_POST['action'] ?? '') !== 'upload_proof') {
            throw new InvalidArgumentException('Aksi tidak dikenali.');
        }

        $pdo = db();
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $statement = $pdo->prepare(
            'SELECT sr.id, sr.cost, v.customer_id
             FROM service_records sr JOIN vehicles v ON v.id = sr.vehicle_id
             WHERE sr.id = :id'
        );
        $statement->execute(['id' => $serviceId]);
        $record = $statement->fetch();
        if (!$record || (int) $record['customer_id'] !== (int) $customer['customer_id']) {
            throw new InvalidArgumentException('Data servis tidak ditemukan.');
        }

        $file = $_FILES['proof'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Pilih berkas bukti transfer terlebih dahulu.');
        }
        if ((int) $file['size'] > PAYMENT_PROOF_MAX_BYTES) {
            throw new RuntimeException('Ukuran berkas melebihi 2 MB.');
        }
        $extension = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, PAYMENT_PROOF_EXTENSIONS, true)) {
            throw new RuntimeException('Format berkas harus JPG, PNG, atau PDF.');
        }
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
            throw new RuntimeException('Isi berkas tidak sesuai format gambar atau PDF.');
        }

        $directory = proof_storage_dir();
        $name = 'proof-' . $serviceId . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        if (!move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $name)) {
            throw new RuntimeException('Bukti transfer gagal disimpan. Coba lagi.');
        }

        $statement = $pdo->prepare(
            "INSERT INTO payments (service_record_id, amount, method, status, proof_path, paid_at, verified_at, verified_by, note)
             VALUES (:sid, :amount, 'Transfer', 'Menunggu Verifikasi', :proof, NULL, NULL, NULL, NULL)
             ON DUPLICATE KEY UPDATE amount = VALUES(amount), method = 'Transfer', status = 'Menunggu Verifikasi',
                 proof_path = VALUES(proof_path), paid_at = NULL, verified_at = NULL, verified_by = NULL, note = NULL"
        );
        $statement->execute([
            'sid' => $serviceId,
            'amount' => max(0, (float) $record['cost']),
            'proof' => $name,
        ]);
        flash('Bukti transfer berhasil dikirim. Mohon tunggu verifikasi dari bengkel.');
    } catch (InvalidArgumentException | RuntimeException $exception) {
        flash($exception->getMessage(), 'error');
    } catch (PDOException $exception) {
        error_log('[ServisYuk unggah bukti] ' . $exception->getMessage());
        flash('Bukti transfer gagal diproses. Coba lagi nanti.', 'error');
    }
    redirect('akun-pelanggan.php');
}

$pageTitle = 'Portal Pelanggan';

$profile = null;
$blocks = [];
$stats = ['vehicles' => 0, 'services' => 0, 'active' => 0, 'done' => 0];
$error = null;

try {
    $pdo = db();

    $statement = $pdo->prepare('SELECT id, name FROM customers WHERE id = :id');
    $statement->execute(['id' => $customer['customer_id']]);
    $profile = $statement->fetch();

    if (!$profile) {
        // Akun dihapus admin selagi sesi masih aktif -> tutup sesi bersih.
        customer_logout();
        flash('Sesi berakhir karena data pelanggan sudah tidak ada. Silakan login kembali.', 'error');
        redirect('login-pelanggan.php');
    }

    // Satu-satunya sumber kendaraan: milik pelanggan yang sedang login.
    $vehicles = customer_vehicles($pdo, (int) $profile['id']);
    $stats['vehicles'] = count($vehicles);

    $statusSteps = ['Menunggu Pemeriksaan', 'Sedang Diperbaiki', 'Menunggu Suku Cadang', 'Selesai', 'Siap Diambil'];

    foreach ($vehicles as $vehicle) {
        $services = vehicle_services($pdo, (int) $vehicle['id']);
        $stats['services'] += count($services);
        $outstanding = 0.0;
        $totalCost = 0.0;
        foreach ($services as $service) {
            $totalCost += (float) $service['cost'];
            if (($service['payment_status'] ?? 'Belum Dibayar') !== 'Lunas') {
                $outstanding += (float) $service['cost'];
            }
            if (in_array($service['status'], service_active_statuses(), true)) {
                $stats['active']++;
            } elseif (in_array($service['status'], ['Selesai', 'Siap Diambil'], true)) {
                $stats['done']++;
            }
        }
        $latest = $services[0] ?? null;
        $blocks[] = [
            'vehicle' => $vehicle,
            'services' => $services,
            'latest' => $latest,
            'status_index' => $latest ? array_search($latest['status'], $statusSteps, true) : false,
            'progress' => $latest ? service_progress_notes($pdo, (int) $latest['id']) : [],
            'total_cost' => $totalCost,
            'outstanding' => $outstanding,
        ];
    }
} catch (PDOException $exception) {
    error_log('[ServisYuk portal pelanggan] ' . $exception->getMessage());
    $error = 'Data pelanggan belum dapat dimuat. Pastikan MySQL aktif, lalu muat ulang halaman.';
}

$flashMessage = take_flash();

require __DIR__ . '/backend/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <span class="eyebrow eyebrow-light"><span class="eyebrow-dot"></span> PORTAL PELANGGAN</span>
        <h1>Selamat datang, <?= e($profile['name'] ?? 'Pelanggan') ?></h1>
        <p>Pantau status pengerjaan, keluhan, estimasi biaya, dan riwayat servis seluruh kendaraan Anda dalam satu halaman.</p>
    </div>
</section>

<section class="container lookup-wrap" style="padding-bottom:80px">
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= e($flashMessage['type']) ?> alert-auto"><?= e($flashMessage['message']) ?></div>
    <?php endif; ?>

    <div class="portal-top">
        <div>
            <span class="eyebrow">RINGKASAN AKUN</span>
            <h2>Kendaraan Anda di ServisYuk</h2>
        </div>
        <div class="portal-actions">
            <a class="button button-ghost button-sm" href="cek-status.php"><?= icon('search') ?> Cek tanpa login</a>
            <a class="button button-ghost button-sm" href="logout-pelanggan.php"><?= icon('log-out') ?> Keluar</a>
        </div>
    </div>

    <section class="stat-grid">
        <article class="stat-card">
            <span class="stat-icon icon-navy"><?= icon('car') ?></span>
            <span class="stat-label">KENDARAAN</span>
            <strong><?= $stats['vehicles'] ?></strong><small>Terdaftar atas nama Anda</small>
        </article>
        <article class="stat-card">
            <span class="stat-icon icon-orange"><?= icon('clipboard-list') ?></span>
            <span class="stat-label">CATATAN SERVIS</span>
            <strong><?= $stats['services'] ?></strong><small>Seluruh riwayat</small>
        </article>
        <article class="stat-card">
            <span class="stat-icon icon-amber"><?= icon('wrench') ?></span>
            <span class="stat-label">SEDANG DIKERJAKAN</span>
            <strong><?= $stats['active'] ?></strong><small>Sedang diproses</small>
        </article>
        <article class="stat-card">
            <span class="stat-icon icon-green"><?= icon('circle-check') ?></span>
            <span class="stat-label">SELESAI</span>
            <strong><?= $stats['done'] ?></strong><small>Pekerjaan tuntas</small>
        </article>
    </section>

    <?php if ($profile && $blocks === []): ?>
        <div class="result-card">
            <div class="empty-state">Belum ada kendaraan terdaftar atas nama Anda. Daftarkan kendaraan saat datang ke bengkel, lalu status servisnya akan tampil di sini.</div>
        </div>
    <?php else: ?>
        <?php foreach ($blocks as $block): $vehicle = $block['vehicle']; $latest = $block['latest']; $services = $block['services']; ?>
            <div class="result-card portal-vehicle">
                <div class="vehicle-card">
                    <div class="vehicle-avatar"><?= icon('car') ?></div>
                    <div class="vehicle-meta">
                        <span>Informasi kendaraan</span>
                        <h3><?= e(trim(($vehicle['brand'] ?? '') . ' ' . ($vehicle['model'] ?? '')) ?: 'Kendaraan pelanggan') ?><?= ($vehicle['year'] ?? null) ? ' ' . e((string) $vehicle['year']) : '' ?></h3>
                        <p>Pemilik: <?= e($profile['name'] ?? '') ?></p>
                    </div>
                    <span class="plate-tag"><?= e($vehicle['plate_number']) ?></span>
                </div>

                <?php if (!$latest): ?>
                    <div class="empty-state">Kendaraan ini belum memiliki catatan servis. Silakan hubungi bengkel untuk pendaftaran servis.</div>
                <?php else: ?>
                    <h3>Status terkini</h3>
                    <div class="progress-track" aria-hidden="true">
                        <?php foreach ($statusSteps as $index => $step): ?>
                            <span class="<?= $block['status_index'] !== false && $index <= $block['status_index'] ? 'done' : '' ?>"></span>
                        <?php endforeach; ?>
                    </div>

                    <div class="detail-grid">
                        <div class="detail-item"><span>Status</span><strong><?= e($latest['status']) ?></strong></div>
                        <div class="detail-item"><span>Jenis Servis</span><strong><?= e($latest['service_name'] ?: 'Servis umum') ?></strong></div>
                        <div class="detail-item"><span>Tanggal Masuk</span><strong><?= e(date('d M Y', strtotime($latest['service_date']))) ?></strong></div>
                        <div class="detail-item"><span>Estimasi Biaya</span><strong><?= rupiah($latest['cost']) ?></strong></div>
                        <?php if (in_array($latest['status'], ['Selesai', 'Siap Diambil'], true)): ?>
                            <div class="detail-item"><span>Waktu Selesai</span><strong><?= e($latest['completed_at'] ? format_datetime($latest['completed_at']) : 'Sudah selesai') ?></strong></div>
                        <?php elseif ($latest['estimated_done']): ?>
                            <div class="detail-item"><span>Perkiraan Selesai</span><strong><?= e(format_datetime($latest['estimated_done'])) ?></strong></div>
                        <?php else: ?>
                            <div class="detail-item"><span>Perkiraan Selesai</span><strong>Belum dapat dipastikan</strong></div>
                        <?php endif; ?>
                        <div class="detail-item"><span>Pembayaran</span><strong><?= e($latest['payment_status'] ?: 'Belum ada data') ?></strong></div>
                    </div>

                    <div class="detail-item" style="margin-bottom:6px">
                        <span>Keluhan / Catatan Pekerjaan</span>
                        <strong style="font-weight:600;color:var(--slate-700)"><?= e($latest['description'] ?: 'Tidak ada keterangan tambahan.') ?></strong>
                    </div>

                    <?php if ($block['progress']): ?>
                        <div class="history-heading">
                            <h3>Perkembangan servis</h3>
                            <span><?= count($block['progress']) ?> catatan</span>
                        </div>
                        <div class="history-list">
                            <?php foreach ($block['progress'] as $note): ?>
                                <article class="history-item">
                                    <div class="history-date"><?= e(date('d M Y', strtotime($note['created_at']))) ?></div>
                                    <div class="history-details">
                                        <h4><span class="status-badge status-info"><?= e($note['note_type']) ?></span></h4>
                                        <p><?= e($note['note']) ?></p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="history-heading">
                        <h3>Riwayat servis</h3>
                        <span><?= count($services) ?> catatan</span>
                    </div>
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

                    <?php $payables = array_values(array_filter($services, static fn (array $s): bool => ($s['payment_status'] ?? 'Belum Dibayar') !== 'Lunas')); ?>
                    <div class="history-heading">
                        <h3>Tagihan &amp; Pembayaran</h3>
                        <span>Transfer bank</span>
                    </div>
                    <div class="detail-grid">
                        <div class="detail-item"><span>Total Tagihan</span><strong><?= rupiah($block['total_cost']) ?></strong></div>
                        <div class="detail-item"><span>Belum Lunas</span><strong><?= rupiah($block['outstanding']) ?></strong></div>
                    </div>

                    <?php if ($payables): ?>
                        <div class="alert alert-warning" style="margin-top:8px">
                            <?= icon('wallet') ?>
                            <div>Transfer ke <strong><?= e(BANK_NAME) ?></strong> rekening <strong><?= e(BANK_ACCOUNT_NUMBER) ?></strong> a.n. <strong><?= e(BANK_ACCOUNT_NAME) ?></strong>, lalu unggah bukti transfer di bawah.</div>
                        </div>
                        <?php foreach ($payables as $service): ?>
                            <form class="admin-form" method="post" enctype="multipart/form-data" style="margin-top:14px;border-top:1px solid var(--slate-200);padding-top:14px">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="upload_proof">
                                <input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>">
                                <div class="detail-grid">
                                    <div class="detail-item"><span>Servis</span><strong><?= e($service['service_name'] ?: 'Servis kendaraan') ?></strong></div>
                                    <div class="detail-item"><span>Tanggal</span><strong><?= e(date('d M Y', strtotime($service['service_date']))) ?></strong></div>
                                    <div class="detail-item"><span>Jumlah</span><strong><?= rupiah($service['cost']) ?></strong></div>
                                    <div class="detail-item"><span>Status</span><strong><span class="status-badge <?= status_class($service['payment_status'] ?: 'Belum Dibayar') ?>"><?= e($service['payment_status'] ?: 'Belum Dibayar') ?></span></strong></div>
                                </div>
                                <?php if (($service['payment_status'] ?? '') === 'Menunggu Verifikasi'): ?>
                                    <div class="alert alert-warning"><?= icon('clock') ?><div>Bukti transfer sudah dikirim dan sedang menunggu verifikasi dari bengkel.</div></div>
                                <?php elseif (($service['payment_status'] ?? '') === 'Ditolak'): ?>
                                    <div class="alert alert-error"><?= icon('circle-alert') ?><div>Bukti transfer sebelumnya ditolak. Silakan unggah ulang bukti yang benar.</div></div>
                                <?php endif; ?>
                                <?php if ($service['proof_path']): ?>
                                    <a class="text-button" href="akun-pelanggan.php?proof=<?= (int) $service['payment_id'] ?>" target="_blank" rel="noopener"><?= icon('eye') ?> Lihat bukti yang sudah dikirim</a>
                                <?php endif; ?>
                                <label>UNGGAH BUKTI TRANSFER (JPG/PNG/PDF, MAKS 2 MB)</label>
                                <input class="text-input" type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" required>
                                <button class="button button-primary button-sm" type="submit" style="margin-top:12px"><?= icon('upload') ?> Kirim bukti transfer</button>
                            </form>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">Semua tagihan kendaraan ini sudah lunas. Terima kasih.</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="privacy-note"><?= icon('shield-check') ?> Portal ini hanya menampilkan kendaraan yang terdaftar atas nama Anda. Data pribadi pelanggan lain tidak pernah ditampilkan di sini.</div>
</section>
<?php require __DIR__ . '/backend/includes/footer.php'; ?>
