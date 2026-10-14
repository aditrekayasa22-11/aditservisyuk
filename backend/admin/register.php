<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';

/**
 * Pendaftaran akun admin.
 *  - Admin yang sudah login: menambah admin baru dari dashboard.
 *  - Pengunjung dari halaman login: mendaftar akun admin baru secara langsung.
 */
$isAdmin = !empty($_SESSION['admin_id']);
$pdo = db();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $name = trim((string) ($_POST['name'] ?? '')) ?: $username;
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    if ($error === null) {
        if (!preg_match('/^[a-zA-Z0-9_.-]{4,40}$/', $username)) {
            $error = 'Username harus 4–40 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.';
        } elseif (strlen($password) < 10) {
            $error = 'Password harus terdiri dari minimal 10 karakter.';
        } elseif ($password !== $confirm) {
            $error = 'Konfirmasi password tidak cocok.';
        } else {
            try {
                $statement = $pdo->prepare('INSERT INTO admins (username, password_hash, name) VALUES (:username, :password_hash, :name)');
                $statement->execute([
                    'username' => $username,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'name' => $name,
                ]);
                if ($isAdmin) {
                    flash('Akun admin "' . $username . '" berhasil dibuat.');
                    redirect('register.php');
                }
                flash('Akun admin "' . $username . '" berhasil dibuat. Silakan masuk.');
                redirect('login.php');
            } catch (PDOException $exception) {
                $error = 'Akun gagal dibuat. Username "' . $username . '" mungkin sudah digunakan.';
            }
        }
    }
}

$admins = $isAdmin
    ? $pdo->query('SELECT id, username, name, created_at FROM admins ORDER BY id')->fetchAll()
    : [];

$flashMessage = take_flash();
$pageTitle = $isAdmin ? 'Tambah Admin' : 'Daftar Admin';
$adminView = true;
$currentPage = 'register';

/** Field formulir dipakai bersama oleh versi admin (dashboard) & pengunjung (auth). */
function render_register_fields(): void
{
    ?>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label for="username">USERNAME *</label>
    <input class="text-input" id="username" name="username" minlength="4" maxlength="40" autocomplete="off" required>
    <label for="name">NAMA TAMPILAN (OPSIONAL)</label>
    <input class="text-input" id="name" name="name" maxlength="100" placeholder="Dikosongkan = username">
    <label for="password">PASSWORD *</label>
    <div class="input-password">
        <input class="text-input" id="password" name="password" type="password" minlength="10" autocomplete="new-password" required>
        <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Tampilkan password" aria-pressed="false"><?= icon('eye') ?></button>
    </div>
    <label for="password_confirm">KONFIRMASI PASSWORD *</label>
    <div class="input-password">
        <input class="text-input" id="password_confirm" name="password_confirm" type="password" minlength="10" autocomplete="new-password" required>
        <button class="password-toggle" type="button" data-password-toggle="password_confirm" aria-label="Tampilkan password" aria-pressed="false"><?= icon('eye') ?></button>
    </div>
    <?php
}

require __DIR__ . '/../includes/header.php';

if ($isAdmin): ?>
<div class="dashboard-shell">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<div class="dashboard-content">
    <div class="dashboard-topline">
        <div>
            <span class="eyebrow">AKUN ADMIN</span>
            <h1>Daftar akun admin</h1>
            <p>Tambahkan akun admin baru untuk mengelola operasional bengkel.</p>
        </div>
        <span class="date-chip"><?= count($admins) ?> akun</span>
    </div>

    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= e($flashMessage['type']) ?> alert-auto"><?= e($flashMessage['message']) ?></div>
    <?php endif; ?>

    <section class="admin-section">
        <div class="section-heading"><div><span class="eyebrow">AKUN BARU</span><h2>Buat akun admin</h2></div></div>
        <?php if ($error): ?><div class="alert alert-error" style="margin-bottom:18px"><?= e($error) ?></div><?php endif; ?>
        <form class="admin-form form-card" method="post">
            <?php render_register_fields(); ?>
            <p class="text-muted" style="margin:0;font-size:.83rem">Gunakan password unik dengan minimal 10 karakter. Password disimpan dalam bentuk hash.</p>
            <button class="button button-primary" type="submit"><?= icon('user-plus') ?> Buat akun admin <?= icon('arrow-right') ?></button>
        </form>
    </section>

    <section class="admin-section">
        <div class="section-heading"><div><span class="eyebrow">DAFTAR AKUN</span><h2>Admin terdaftar</h2></div><span class="count-chip"><?= count($admins) ?> akun</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>USERNAME</th><th>NAMA</th><th>DIBUAT</th></tr></thead>
                <tbody>
                    <?php foreach ($admins as $admin): ?>
                        <tr>
                            <td>
                                <strong><?= e($admin['username']) ?></strong>
                                <?php if ((int) $admin['id'] === (int) $_SESSION['admin_id']): ?>
                                    <span class="status-badge status-info" style="margin-left:6px">Anda</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($admin['name']) ?></td>
                            <td><?= e(date('d M Y H:i', strtotime((string) $admin['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$admins): ?>
                        <tr><td colspan="3" class="table-empty">Belum ada akun admin.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</div>
<?php else: ?>
<section class="auth-layout">
    <div class="auth-aside"><span class="eyebrow"><span class="eyebrow-dot"></span> PENDAFTARAN ADMIN</span>
        <h1>Butuh akses?<br><em>Daftar sekarang.</em></h1>
        <p>Buat akun administrator baru untuk mengelola bengkel <?= APP_NAME ?>. Cukup isi username dan password, tanpa kode undangan.</p>
        <div class="auth-aside-mark">SY<span> / SECURE SIGNUP</span></div>
    </div>
    <div class="auth-card">
        <span class="eyebrow eyebrow-dark">AKUN ADMIN BARU</span>
        <h2>Daftar admin</h2><p class="auth-subtitle">Isi data akun Anda. Tidak perlu kode undangan.</p>
        <?php if ($flashMessage): ?>
            <div class="alert alert-<?= e($flashMessage['type']) ?> alert-auto"><?= e($flashMessage['message']) ?></div>
        <?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
            <?php render_register_fields(); ?>
            <button class="button button-primary button-wide" type="submit"><?= icon('user-plus') ?> Buat akun admin <?= icon('arrow-right') ?></button>
        </form>
        <a class="back-link" href="login.php"><?= icon('arrow-left') ?> Kembali ke login</a>
    </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
