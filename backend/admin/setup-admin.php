<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';

$error = null;
$complete = false;
try {
    $complete = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
} catch (PDOException $exception) {
    $error = 'Database belum siap. Aktifkan MySQL dan impor database.sql terlebih dahulu.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && !$complete) {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9_.-]{4,40}$/', $username)) {
        $error = 'Username harus 4–40 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.';
    } elseif (strlen($password) < 10) {
        $error = 'Password harus terdiri dari minimal 10 karakter.';
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ((int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0) {
                throw new RuntimeException('Akun admin sudah dibuat. Silakan login.');
            }
            $statement = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (:username, :password_hash)');
            $statement->execute(['username' => $username, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
            $pdo->commit();
            $complete = true;
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Akun admin tidak berhasil dibuat. Username mungkin sudah digunakan.';
        }
    }
}

$pageTitle = 'Buat Admin Pertama';
$adminView = true;
require __DIR__ . '/../includes/header.php';
?>
<section class="auth-layout setup-layout">
    <div class="auth-aside"><span class="eyebrow"><span class="eyebrow-dot"></span> PENGATURAN AWAL</span>
        <h1>Siapkan akses<br><em>administrator.</em></h1>
        <p>Buat satu akun admin untuk mulai mengelola data AutoVance. Password disimpan dalam bentuk hash.</p>
        <div class="auth-aside-mark">AV<span> / SECURE SETUP</span></div>
    </div>
    <div class="auth-card">
        <span class="eyebrow eyebrow-dark">KONFIGURASI AKUN</span><h2>Buat admin pertama</h2>
        <p class="auth-subtitle">Gunakan password unik dengan minimal 10 karakter.</p>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($complete): ?>
            <div class="alert alert-success">Akun admin telah tersedia. Halaman pembuatan akun pertama sekarang terkunci.</div>
            <a class="button button-primary button-wide" href="login.php">Lanjut ke login <span>→</span></a>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label for="username">USERNAME</label><input class="text-input" id="username" name="username" minlength="4" maxlength="40" required>
                <label for="password">PASSWORD BARU</label><input class="text-input" id="password" name="password" type="password" minlength="10" autocomplete="new-password" required>
                <button class="button button-primary button-wide" type="submit">Buat akun admin <span>→</span></button>
            </form>
        <?php endif; ?>
        <a class="back-link" href="login.php">← Kembali ke login</a>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
