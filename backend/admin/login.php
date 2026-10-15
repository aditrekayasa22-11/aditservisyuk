<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['admin_id'])) {
    redirect('dashboard.php');
}

$error = null;
$setupNeeded = false;
try {
    $setupNeeded = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0;
} catch (PDOException $exception) {
    $error = 'Database belum siap. Aktifkan MySQL dan impor database.sql terlebih dahulu.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $statement = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = :username LIMIT 1');
    $statement->execute(['username' => $username]);
    $admin = $statement->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        redirect('dashboard.php');
    }
    $error = 'Username atau password tidak sesuai.';
}

$pageTitle = 'Login Admin';
$adminView = true;
require __DIR__ . '/../includes/header.php';
?>
<section class="auth-layout">
    <div class="auth-aside"><span class="eyebrow"><span class="eyebrow-dot"></span> AREA ADMINISTRATOR</span>
        <h1>Semua operasional.<br><em>Satu dashboard.</em></h1>
        <p>Kelola pelanggan, pekerjaan servis, stok, dan pembayaran <?= APP_NAME ?> dari satu tempat.</p>
        <div class="auth-aside-mark">SY<span> / CONTROL CENTER</span></div>
    </div>
    <div class="auth-card">
        <span class="eyebrow eyebrow-dark">SELAMAT DATANG KEMBALI</span>
        <h2>Login admin</h2><p class="auth-subtitle">Masuk untuk mengelola operasional bengkel.</p>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($setupNeeded): ?><div class="alert alert-info">Belum ada akun admin. <a href="setup-admin.php">Buat akun admin pertama</a>.</div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label for="username">USERNAME</label><input class="text-input" id="username" name="username" autocomplete="username" required>
            <label for="password">PASSWORD</label>
            <div class="input-password">
                <input class="text-input" id="password" name="password" type="password" autocomplete="current-password" required>
                <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Tampilkan password" aria-pressed="false"><?= icon('eye') ?></button>
            </div>
            <button class="button button-primary button-wide" type="submit"><?= icon('log-in') ?> Masuk ke dashboard <?= icon('arrow-right') ?></button>
        </form>
        <a class="back-link" href="../index.php"><?= icon('arrow-left') ?> Kembali ke halaman utama</a>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
