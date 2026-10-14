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

    $statusSteps = ['Menunggu', 'Dikerjakan', 'Selesai'];

    foreach ($vehicles as $vehicle) {
        $services = vehicle_services($pdo, (int) $vehicle['id']);
        $stats['services'] += count($services);
        foreach ($services as $service) {
            if (in_array($service['status'], ['Menunggu', 'Dikerjakan'], true)) {
                $stats['active']++;
            } elseif ($service['status'] === 'Selesai') {
                $stats['done']++;
            }
        }
        $latest = $services[0] ?? null;
        $blocks[] = [
            'vehicle' => $vehicle,
            'services' => $services,
            'latest' => $latest,
            'status_index' => $latest ? array_search($latest['status'], $statusSteps, true) : false,
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
            <strong><?= $stats['active'] ?></strong><small>Menunggu / dikerjakan</small>
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
                        <?php foreach (['Menunggu', 'Dikerjakan', 'Selesai'] as $index => $step): ?>
                            <span class="<?= $block['status_index'] !== false && $index <= $block['status_index'] ? 'done' : '' ?>"></span>
                        <?php endforeach; ?>
                    </div>

                    <div class="detail-grid">
                        <div class="detail-item"><span>Status</span><strong><?= e($latest['status']) ?></strong></div>
                        <div class="detail-item"><span>Jenis Servis</span><strong><?= e($latest['service_name'] ?: 'Servis umum') ?></strong></div>
                        <div class="detail-item"><span>Tanggal Masuk</span><strong><?= e(date('d M Y', strtotime($latest['service_date']))) ?></strong></div>
                        <div class="detail-item"><span>Estimasi Biaya</span><strong><?= rupiah($latest['cost']) ?></strong></div>
                        <div class="detail-item"><span>Pembayaran</span><strong><?= e($latest['payment_status'] ?: 'Belum ada data') ?></strong></div>
                    </div>

                    <div class="detail-item" style="margin-bottom:6px">
                        <span>Keluhan / Catatan Pekerjaan</span>
                        <strong style="font-weight:600;color:var(--slate-700)"><?= e($latest['description'] ?: 'Tidak ada keterangan tambahan.') ?></strong>
                    </div>

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
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="privacy-note"><?= icon('shield-check') ?> Portal ini hanya menampilkan kendaraan yang terdaftar atas nama Anda. Data pribadi pelanggan lain tidak pernah ditampilkan di sini.</div>
</section>
<?php require __DIR__ . '/backend/includes/footer.php'; ?>
