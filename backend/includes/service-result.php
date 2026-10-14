<?php
/**
 * Partial hasil pencarian servis kendaraan.
 * Dipakai oleh: cek-status.php, riwayat.php (admin).
 * Variabel yang harus sudah tersedia:
 *   $vehicle (array), $services (array)
 */
?>
<div class="result-title" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:16px">
    <div>
        <span class="eyebrow">HASIL PENCARIAN</span>
        <h2 style="font-size:26px;color:var(--navy-800);margin-top:8px">Informasi kendaraan</h2>
    </div>
    <span class="plate-tag"><?= e($vehicle['plate_number']) ?></span>
</div>

<div class="vehicle-card">
    <div class="vehicle-avatar"><?= icon('car') ?></div>
    <div class="vehicle-meta">
        <span>KENDARAAN TERDAFTAR</span>
        <h3><?= e(trim(($vehicle['brand'] ?? '') . ' ' . ($vehicle['model'] ?? '')) ?: 'Kendaraan pelanggan') ?></h3>
        <p><?= e($vehicle['year'] ?: 'Tahun tidak dicantumkan') ?> &middot; Pemilik <?= e($vehicle['customer_name']) ?></p>
    </div>
    <span class="status-badge status-success">Terverifikasi</span>
</div>

<div class="history-heading">
    <h3>Riwayat servis</h3>
    <span><?= count($services) ?> catatan</span>
</div>

<?php if (!$services): ?>
    <div class="empty-state">Kendaraan terdaftar, tetapi belum memiliki catatan servis.</div>
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
                <div class="history-cost">
                    <?= rupiah($service['cost']) ?>
                    <small><?= e($service['payment_status'] ?: 'Belum Dibayar') ?></small>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
