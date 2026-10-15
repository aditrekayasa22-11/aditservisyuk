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

        $chosenMethod = (string) ($_POST['payment_method'] ?? 'Transfer');
        $validMethods = ['Tunai', 'QRIS', 'Transfer'];
        if (!in_array($chosenMethod, $validMethods, true)) {
            $chosenMethod = 'Transfer';
        }

        $note = null;
        $proofName = null;

        if ($chosenMethod === 'Tunai') {
            $note = 'Pembayaran Tunai di Kasir Bengkel';
            $statement = $pdo->prepare(
                "INSERT INTO payments (service_record_id, amount, method, status, proof_path, paid_at, verified_at, verified_by, note)
                 VALUES (:sid, :amount, 'Tunai', 'Menunggu Verifikasi', NULL, NULL, NULL, NULL, :note)
                 ON DUPLICATE KEY UPDATE amount = VALUES(amount), method = 'Tunai', status = 'Menunggu Verifikasi',
                     proof_path = NULL, paid_at = NULL, verified_at = NULL, verified_by = NULL, note = VALUES(note)"
            );
            $statement->execute([
                'sid' => $serviceId,
                'amount' => max(0, (float) $record['cost']),
                'note' => $note,
            ]);
            flash('Permintaan pembayaran Tunai dikirim. Silakan selesaikan pembayaran di kasir bengkel saat mengambil kendaraan.');
        } else {
            // QRIS atau Transfer Bank: wajib unggah bukti pembayaran
            $bankChoice = strtolower(trim((string) ($_POST['bank_choice'] ?? 'bni')));
            if ($chosenMethod === 'QRIS') {
                $note = 'Pembayaran via QRIS (' . QRIS_MERCHANT_NAME . ')';
            } else {
                $bankAccounts = BANK_ACCOUNTS;
                $bankInfo = $bankAccounts[$bankChoice] ?? $bankAccounts['bni'];
                $note = 'Transfer ' . $bankInfo['name'] . ' (' . $bankInfo['account_number'] . ')';
            }

            $file = $_FILES['proof'] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Pilih berkas bukti pembayaran terlebih dahulu.');
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
            $proofName = 'proof-' . $serviceId . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
            if (!move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $proofName)) {
                throw new RuntimeException('Bukti pembayaran gagal disimpan. Coba lagi.');
            }

            $dbMethod = $chosenMethod === 'QRIS' ? 'QRIS' : 'Transfer';
            $statement = $pdo->prepare(
                "INSERT INTO payments (service_record_id, amount, method, status, proof_path, paid_at, verified_at, verified_by, note)
                 VALUES (:sid, :amount, :method, 'Menunggu Verifikasi', :proof, NULL, NULL, NULL, :note)
                 ON DUPLICATE KEY UPDATE amount = VALUES(amount), method = VALUES(method), status = 'Menunggu Verifikasi',
                     proof_path = VALUES(proof_path), paid_at = NULL, verified_at = NULL, verified_by = NULL, note = VALUES(note)"
            );
            $statement->execute([
                'sid' => $serviceId,
                'amount' => max(0, (float) $record['cost']),
                'method' => $dbMethod,
                'proof' => $proofName,
                'note' => $note,
            ]);
            flash('Bukti pembayaran ' . e($chosenMethod) . ' berhasil dikirim. Mohon tunggu verifikasi dari bengkel.');
        }
    } catch (InvalidArgumentException | RuntimeException $exception) {
        flash($exception->getMessage(), 'error');
    } catch (PDOException $exception) {
        error_log('[ServisYuk unggah bukti] ' . $exception->getMessage());
        flash('Bukti pembayaran gagal diproses. Coba lagi nanti.', 'error');
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
                        <span>Pilih metode pembayaran</span>
                    </div>
                    <div class="detail-grid">
                        <div class="detail-item"><span>Total Tagihan</span><strong><?= rupiah($block['total_cost']) ?></strong></div>
                        <div class="detail-item"><span>Belum Lunas</span><strong><?= rupiah($block['outstanding']) ?></strong></div>
                    </div>

                    <?php if ($payables): ?>
                        <?php foreach ($payables as $service): 
                            $currMethod = $service['payment_method'] ?? 'Transfer';
                            if (!in_array($currMethod, ['Tunai', 'QRIS', 'Transfer'], true)) {
                                $currMethod = 'Transfer';
                            }
                        ?>
                            <form class="admin-form payment-form-interactive" method="post" enctype="multipart/form-data" style="margin-top:14px;border-top:1px solid var(--slate-200);padding-top:14px">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="upload_proof">
                                <input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>">

                                <div class="detail-grid">
                                    <div class="detail-item"><span>Servis</span><strong><?= e($service['service_name'] ?: 'Servis kendaraan') ?></strong></div>
                                    <div class="detail-item"><span>Tanggal</span><strong><?= e(date('d M Y', strtotime($service['service_date']))) ?></strong></div>
                                    <div class="detail-item"><span>Jumlah Tagihan</span><strong style="color:var(--orange-600)"><?= rupiah($service['cost']) ?></strong></div>
                                    <div class="detail-item"><span>Status Tagihan</span><strong><span class="status-badge <?= status_class($service['payment_status'] ?: 'Belum Dibayar') ?>"><?= e($service['payment_status'] ?: 'Belum Dibayar') ?></span></strong></div>
                                </div>

                                <?php if (($service['payment_status'] ?? '') === 'Menunggu Verifikasi'): ?>
                                    <div class="alert alert-warning"><?= icon('clock') ?><div>Pembayaran Anda (<strong><?= e($service['payment_method']) ?></strong>) sedang dalam proses verifikasi kasir bengkel.</div></div>
                                <?php elseif (($service['payment_status'] ?? '') === 'Ditolak'): ?>
                                    <div class="alert alert-error"><?= icon('circle-alert') ?><div>Bukti pembayaran sebelumnya ditolak. Silakan pilih kembali metode dan unggah bukti yang benar.</div></div>
                                <?php endif; ?>

                                <?php if ($service['proof_path']): ?>
                                    <div style="margin-bottom:12px">
                                        <a class="text-button" href="akun-pelanggan.php?proof=<?= (int) $service['payment_id'] ?>" target="_blank" rel="noopener"><?= icon('eye') ?> Lihat bukti pembayaran yang sudah dikirim</a>
                                    </div>
                                <?php endif; ?>

                                <div class="payment-selector-wrap">
                                    <label style="margin-bottom:4px">PILIH METODE PEMBAYARAN</label>
                                    <p class="text-muted" style="font-size:.84rem;margin-bottom:12px">Pilih pembayaran langsung (Cash), scan kode QRIS, atau transfer ke rekening resmi bengkel.</p>
                                    
                                    <div class="payment-methods-grid">
                                        <!-- Opsi 1: Cash / Tunai -->
                                        <label class="method-choice">
                                            <input type="radio" name="payment_method" value="Tunai" <?= $currMethod === 'Tunai' ? 'checked' : '' ?>>
                                            <div class="method-card">
                                                <div class="method-icon"><?= icon('banknote') ?></div>
                                                <div class="method-info">
                                                    <strong>Cash / Tunai</strong>
                                                    <small>Bayar di kasir</small>
                                                </div>
                                            </div>
                                        </label>

                                        <!-- Opsi 2: QRIS -->
                                        <label class="method-choice">
                                            <input type="radio" name="payment_method" value="QRIS" <?= $currMethod === 'QRIS' ? 'checked' : '' ?>>
                                            <div class="method-card">
                                                <div class="method-icon"><?= icon('qr-code') ?></div>
                                                <div class="method-info">
                                                    <strong>QRIS</strong>
                                                    <small>Scan instan</small>
                                                </div>
                                            </div>
                                        </label>

                                        <!-- Opsi 3: Transfer Bank -->
                                        <label class="method-choice">
                                            <input type="radio" name="payment_method" value="Transfer" <?= $currMethod === 'Transfer' ? 'checked' : '' ?>>
                                            <div class="method-card">
                                                <div class="method-icon"><?= icon('landmark') ?></div>
                                                <div class="method-info">
                                                    <strong>Transfer Bank</strong>
                                                    <small>BNI, BRI, BCA, Mandiri</small>
                                                </div>
                                            </div>
                                        </label>
                                    </div>

                                    <!-- Panel Instruksi Cash -->
                                    <div class="payment-guide-box" data-method-box="Tunai" style="display:none">
                                        <div style="display:flex;gap:12px;align-items:flex-start">
                                            <span style="font-size:1.6rem;color:var(--orange-600)"><?= icon('wallet') ?></span>
                                            <div>
                                                <h4 style="margin-bottom:4px">Pembayaran Tunai di Kasir Bengkel</h4>
                                                <p style="margin:0;font-size:.88rem;color:var(--slate-500)">
                                                    Silakan bayar tunai sejumlah <strong><?= rupiah($service['cost']) ?></strong> langsung di kasir ServisYuk ketika Anda mengambil kendaraan. Klik tombol konfirmasi di bawah untuk memberi tahu kasir.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Panel Instruksi QRIS -->
                                    <div class="payment-guide-box" data-method-box="QRIS" style="display:none">
                                        <div class="qris-guide-header">
                                            <div>
                                                <h4 style="margin-bottom:4px">Scan QRIS ServisYuk</h4>
                                                <p style="margin:0;font-size:.88rem;color:var(--slate-500)">Buka aplikasi m-Banking atau e-Wallet (BCA Mobile, Livin by Mandiri, BRImo, BNI Mobile, GoPay, OVO, DANA, ShopeePay), lalu scan kode QR di bawah:</p>
                                            </div>
                                        </div>
                                        
                                        <div class="qris-preview-card">
                                            <div class="qris-card-left">
                                                <div class="qris-img-wrap">
                                                    <img src="<?= e(QRIS_IMAGE_PATH) ?>" alt="QRIS ServisYuk">
                                                </div>
                                                <a class="qris-download-btn button button-ghost button-sm" href="<?= e(QRIS_IMAGE_PATH) ?>" download="QRIS-ServisYuk.svg">
                                                    <?= icon('download') ?> Unduh Kode QR
                                                </a>
                                            </div>
                                            <div class="qris-card-info">
                                                <span class="badge badge-blue">NMID: <?= e(QRIS_NMID) ?></span>
                                                <h3 style="margin:8px 0 2px"><?= e(QRIS_MERCHANT_NAME) ?></h3>
                                                <p style="font-size:.85rem;color:var(--slate-500);margin-bottom:10px">Mendukung seluruh aplikasi pembayaran berstandar QRIS Indonesia.</p>
                                                <div class="qris-amount-tag">
                                                    <small>Total Tagihan Servis</small>
                                                    <strong><?= rupiah($service['cost']) ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Panel Instruksi Bank Transfer (BNI, BRI, BCA, Mandiri) -->
                                    <div class="payment-guide-box" data-method-box="Transfer" style="display:none">
                                        <h4 style="margin-bottom:6px">Pilih Bank Tujuan Transfer:</h4>
                                        
                                        <div class="bank-selector">
                                            <label class="bank-pill">
                                                <input type="radio" name="bank_choice" value="bni" checked>
                                                <div class="bank-pill-inner">BNI</div>
                                            </label>
                                            <label class="bank-pill">
                                                <input type="radio" name="bank_choice" value="bri">
                                                <div class="bank-pill-inner">BRI</div>
                                            </label>
                                            <label class="bank-pill">
                                                <input type="radio" name="bank_choice" value="bca">
                                                <div class="bank-pill-inner">BCA</div>
                                            </label>
                                            <label class="bank-pill">
                                                <input type="radio" name="bank_choice" value="mandiri">
                                                <div class="bank-pill-inner">Bank Mandiri</div>
                                            </label>
                                        </div>

                                        <?php foreach (BANK_ACCOUNTS as $key => $bank): ?>
                                            <div class="bank-account-card" data-bank-box="<?= e($key) ?>" style="display:none">
                                                <div>
                                                    <span style="font-size:.75rem;letter-spacing:.08em;color:var(--slate-400);font-weight:800;text-transform:uppercase"><?= e($bank['name']) ?></span>
                                                    <div class="account-number-box">
                                                        <code><?= e($bank['account_number']) ?></code>
                                                        <button type="button" class="btn-copy-acc" data-copy="<?= e($bank['account_number']) ?>">Salin</button>
                                                    </div>
                                                    <small style="color:var(--slate-500)">a.n. <strong><?= e($bank['account_name']) ?></strong></small>
                                                </div>
                                                <div style="text-align:right">
                                                    <small style="display:block;color:var(--slate-400)">Nominal Transfer</small>
                                                    <strong style="color:var(--orange-600);font-size:1.05rem"><?= rupiah($service['cost']) ?></strong>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Bukti Pembayaran (untuk QRIS & Transfer Bank) -->
                                    <div class="proof-upload-section" style="margin-top:14px">
                                        <label>UNGGAH BUKTI PEMBAYARAN (JPG/PNG/PDF, MAKS 2 MB)</label>
                                        <input class="text-input" type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf">
                                        <small class="text-muted" style="display:block;margin-top:4px">Lampirkan tangkapan layar (screenshot) transfer bank atau bukti pembayaran QRIS.</small>
                                    </div>

                                    <button class="button button-primary button-sm btn-submit-payment" type="submit" style="margin-top:16px">
                                        <?= icon('upload') ?> Kirim Bukti Pembayaran
                                    </button>
                                </div>
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
