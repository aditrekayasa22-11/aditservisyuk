<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/backup.php';
require_admin();

$pdo = db();
$dir = backup_storage_dir();
$error = null;

/* Unduh berkas backup (sebelum output apa pun). */
if (isset($_GET['download'])) {
    $name = (string) $_GET['download'];
    if (!valid_backup_name($name) || !is_file($dir . '/' . $name)) {
        http_response_code(404);
        exit('Berkas backup tidak ditemukan.');
    }
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . (string) filesize($dir . '/' . $name));
    readfile($dir . '/' . $name);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'create') {
            $filename = 'backup-' . date('Ymd-His') . '.sql';
            if (file_put_contents($dir . '/' . $filename, db_dump($pdo)) === false) {
                throw new RuntimeException('Gagal menyimpan berkas backup. Periksa izin folder storage/backups.');
            }
            flash('Backup "' . $filename . '" berhasil dibuat.');
        } elseif ($action === 'restore') {
            $source = null;

            if (!empty($_FILES['backup_file']['name'])) {
                $file = $_FILES['backup_file'];
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Berkas gagal diunggah (kode ' . (int) $file['error'] . ').');
                }
                if ((int) $file['size'] > 25 * 1024 * 1024) {
                    throw new RuntimeException('Ukuran berkas terlalu besar (maksimal 25 MB).');
                }
                if (strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'sql') {
                    throw new RuntimeException('Berkas harus berformat .sql.');
                }
                $source = (string) $file['tmp_name'];
            } elseif (!empty($_POST['backup_name'])) {
                $name = (string) $_POST['backup_name'];
                if (!valid_backup_name($name) || !is_file($dir . '/' . $name)) {
                    throw new RuntimeException('Berkas backup tidak ditemukan.');
                }
                $source = $dir . '/' . $name;
            }

            if ($source === null) {
                throw new RuntimeException('Pilih berkas backup yang ingin dipulihkan.');
            }

            // Simpan cadangan otomatis sebelum menimpa data saat ini.
            file_put_contents($dir . '/pre-restore-' . date('Ymd-His') . '.sql', db_dump($pdo));

            db_restore($pdo, (string) file_get_contents($source));
            flash('Data berhasil dipulihkan dari backup.');
        } elseif ($action === 'delete') {
            $name = (string) ($_POST['backup_name'] ?? '');
            if (!valid_backup_name($name) || !is_file($dir . '/' . $name)) {
                throw new RuntimeException('Berkas backup tidak ditemukan.');
            }
            unlink($dir . '/' . $name);
            flash('Backup "' . $name . '" dihapus.');
        } else {
            throw new RuntimeException('Aksi tidak dikenali.');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    if ($error === null) {
        redirect('backup.php');
    }
}

$backups = list_backups();
$flashMessage = take_flash();

$pageTitle = 'Backup & Restore';
$adminView = true;
$currentPage = 'backup';
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<div class="dashboard-content">
    <div class="dashboard-topline">
        <div>
            <span class="eyebrow">KEAMANAN DATA</span>
            <h1>Backup &amp; Restore</h1>
            <p>Cadangkan seluruh data bengkel, lalu pulihkan kapan saja bila data hilang atau rusak.</p>
        </div>
        <span class="date-chip"><?= count($backups) ?> backup tersimpan</span>
    </div>

    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= e($flashMessage['type']) ?> alert-auto"><?= e($flashMessage['message']) ?></div>
    <?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <section class="admin-section">
        <div class="section-heading"><div><span class="eyebrow">BUAT CADANGAN</span><h2>Backup data sekarang</h2></div></div>
        <p class="text-muted" style="margin-top:0">Seluruh tabel (pelanggan, kendaraan, servis, pembayaran, stok, akun admin) akan disimpan ke satu berkas <strong>.sql</strong>.</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create">
            <button class="button button-primary" type="submit"><?= icon('database') ?> Buat backup baru</button>
        </form>
    </section>

    <section class="admin-section">
        <div class="section-heading">
            <div><span class="eyebrow">PULIHKAN DATA</span><h2>Restore dari berkas</h2></div>
        </div>
        <div class="alert alert-warning"><?= icon('triangle-alert') ?><div><strong>Perhatian:</strong> restore akan menimpa data yang ada saat ini dengan isi berkas backup. Sistem otomatis membuat backup <em>pre-restore</em> sebelum proses ini berjalan.</div></div>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="restore">
            <label for="backup_file">UNGGAH BERKAS .SQL</label>
            <input class="text-input" id="backup_file" name="backup_file" type="file" accept=".sql">
            <button class="button button-navy" type="submit" style="margin-top:16px"><?= icon('upload') ?> Pulihkan dari berkas ini</button>
        </form>
    </section>

    <section class="admin-section">
        <div class="section-heading">
            <div><span class="eyebrow">RIWAYAT BACKUP</span><h2>Backup tersimpan di server</h2></div>
            <span class="count-chip"><?= count($backups) ?> berkas</span>
        </div>
        <?php if (!$backups): ?>
            <div class="empty-state">Belum ada backup tersimpan. Buat backup pertama Anda di atas.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>NAMA BERKAS</th><th>UKURAN</th><th>WAKTU</th><th>AKSI</th></tr></thead>
                    <tbody>
                        <?php foreach ($backups as $item): ?>
                            <tr>
                                <td><strong><?= e($item['name']) ?></strong></td>
                                <td><?= e(format_bytes($item['size'])) ?></td>
                                <td><?= e(date('d M Y H:i', $item['time'])) ?></td>
                                <td>
                                    <div class="inline-stock-form">
                                        <a class="button button-ghost button-sm" href="backup.php?download=<?= urlencode($item['name']) ?>"><?= icon('download') ?> Unduh</a>
                                        <form method="post" data-confirm="Pulihkan data dari backup ini? Data saat ini akan ditimpa (backup otomatis dibuat lebih dulu).">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="backup_name" value="<?= e($item['name']) ?>">
                                            <button class="button button-navy button-sm" type="submit"><?= icon('refresh-cw') ?> Restore</button>
                                        </form>
                                        <form method="post" data-confirm="Hapus berkas backup ini?">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="backup_name" value="<?= e($item['name']) ?>">
                                            <button class="button button-danger button-sm" type="submit"><?= icon('trash-2') ?> Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
