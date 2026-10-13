<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Cek Status Servis';
$vehicle = null;
$services = [];
$notFound = false;
$error = null;
$plate = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plate = strtoupper(trim((string) ($_POST['plate_number'] ?? '')));
    if ($plate === '') {
        $error = 'Masukkan nomor polisi kendaraan Anda.';
    } else {
        try {
            $pdo = db();
            $statement = $pdo->prepare(
                'SELECT v.id, v.plate_number, v.brand, v.model, v.year, c.name AS customer_name
                 FROM vehicles v JOIN customers c ON c.id = v.customer_id
                 WHERE REPLACE(UPPER(v.plate_number), " ", "") = REPLACE(UPPER(:plate), " ", "")
                 LIMIT 1'
            );
            $statement->execute(['plate' => $plate]);
            $vehicle = $statement->fetch();
            if ($vehicle) {
                $statement = $pdo->prepare(
                    'SELECT sr.id, sr.description, sr.status, sr.service_date, sr.cost,
                            st.name AS service_name,
                            p.status AS payment_status
                     FROM service_records sr
                     LEFT JOIN service_types st ON st.id = sr.service_type_id
                     LEFT JOIN payments p ON p.service_record_id = sr.id
                     WHERE sr.vehicle_id = :vehicle_id
                     ORDER BY sr.service_date DESC, sr.id DESC'
                );
                $statement->execute(['vehicle_id' => $vehicle['id']]);
                $services = $statement->fetchAll();
            } else {
                $notFound = true;
            }
        } catch (PDOException $exception) {
            $error = 'Database belum dapat diakses. Pastikan MySQL aktif dan database sudah diimpor dari database.sql.';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="hero-copy">
        <span class="eyebrow"><span class="eyebrow-dot"></span> SERVIS ANDA, DALAM GENGGAMAN</span>
        <h1>Perjalanan tenang<br>dimulai dari <em>perawatan.</em></h1>
        <p class="hero-description">Pantau status dan riwayat servis kendaraan Anda dengan mudah. Cukup masukkan nomor polisi.</p>
        <div class="hero-points">
            <span><b>✓</b> Informasi servis terkini</span>
            <span><b>✓</b> Riwayat tersimpan aman</span>
        </div>
    </div>
    <div class="hero-art" aria-hidden="true">
        <div class="art-orbit orbit-one"></div><div class="art-orbit orbit-two"></div>
        <div class="art-glow"></div>
        <div class="car-illustration">
            <div class="car-roof"></div><div class="car-body"></div>
            <div class="car-window"></div><div class="car-wheel wheel-left"></div><div class="car-wheel wheel-right"></div>
            <div class="car-light"></div>
        </div>
        <div class="art-label"><span class="label-pulse"></span> READY FOR THE ROAD</div>
        <div class="art-number">AV<span> / 01</span></div>
    </div>
</section>

<section class="lookup-section" id="cek-status">
    <div class="lookup-intro">
        <span class="eyebrow eyebrow-dark">CEK STATUS SERVIS</span>
        <h2>Kendaraan Anda<br><em>baik-baik saja?</em></h2>
        <p>Masukkan nomor polisi untuk melihat status dan riwayat servis kendaraan Anda.</p>
    </div>
    <div class="lookup-panel">
        <div class="panel-heading">
            <span class="panel-icon">⌕</span>
            <div><h3>Lacak kendaraan</h3><p>Gunakan nomor polisi terdaftar</p></div>
        </div>
        <form method="post" action="#hasil">
            <label for="plate_number">NOMOR POLISI</label>
            <div class="input-wrap plate-input">
                <span class="plate-icon">▤</span>
                <input id="plate_number" name="plate_number" value="<?= e($plate) ?>" placeholder="Contoh: B 1234 ABC" maxlength="15" required>
            </div>
            <?php if ($error): ?><p class="form-error"><?= e($error) ?></p><?php endif; ?>
            <button class="button button-primary button-wide" type="submit">Cek status servis <span>→</span></button>
        </form>
        <div class="privacy-note"><span>▣</span> Data kendaraan Anda kami jaga dengan aman.</div>
    </div>
</section>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error): ?>
<section class="result-section" id="hasil">
    <?php if ($notFound): ?>
        <div class="result-card not-found">
            <div class="result-symbol">?</div>
            <div><span class="eyebrow eyebrow-dark">PENCARIAN KENDARAAN</span><h3>Kendaraan belum ditemukan</h3>
            <p>Nomor polisi <strong><?= e($plate) ?></strong> belum terdaftar. Periksa kembali penulisan atau hubungi bengkel kami.</p></div>
        </div>
    <?php else: ?>
        <div class="result-title"><div><span class="eyebrow eyebrow-dark">HASIL PENCARIAN</span><h2>Informasi kendaraan</h2></div>
            <span class="plate-tag"><?= e($vehicle['plate_number']) ?></span></div>
        <div class="vehicle-card">
            <div class="vehicle-avatar">AV</div>
            <div class="vehicle-meta"><span>KENDARAAN TERDAFTAR</span><h3><?= e(trim($vehicle['brand'] . ' ' . $vehicle['model'])) ?></h3><p><?= e($vehicle['year'] ?: 'Tahun tidak dicantumkan') ?> · Pemilik <?= e($vehicle['customer_name']) ?></p></div>
            <span class="status-badge status-success">Terverifikasi</span>
        </div>
        <div class="history-heading"><h3>Riwayat servis</h3><span><?= count($services) ?> catatan</span></div>
        <?php if (!$services): ?>
            <div class="empty-state">Kendaraan terdaftar, tetapi belum memiliki catatan servis.</div>
        <?php else: ?>
            <div class="history-list">
                <?php foreach ($services as $service): ?>
                    <article class="history-item">
                        <div class="history-date"><?= e(date('d M Y', strtotime($service['service_date']))) ?></div>
                        <div class="history-details"><h4><?= e($service['service_name'] ?: 'Servis kendaraan') ?></h4><p><?= e($service['description'] ?: 'Tidak ada keterangan tambahan.') ?></p></div>
                        <span class="status-badge <?= status_class($service['status']) ?>"><?= e($service['status']) ?></span>
                        <div class="history-cost"><?= rupiah($service['cost']) ?><small><?= e($service['payment_status'] ?: 'Belum Dibayar') ?></small></div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="benefits">
    <div class="benefit"><span class="benefit-number">01</span><h3>Transparan</h3><p>Status servis dan rincian pekerjaan selalu bisa Anda pantau.</p></div>
    <div class="benefit"><span class="benefit-number">02</span><h3>Terorganisir</h3><p>Riwayat perawatan kendaraan tersimpan di satu tempat.</p></div>
    <div class="benefit"><span class="benefit-number">03</span><h3>Terpercaya</h3><p>Informasi kendaraan hanya dapat diakses dengan nomor polisi.</p></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
