<?php
declare(strict_types=1);

function auth_get_csrf(array $params): array
{
    return ['csrf_token' => csrf_token()];
}

function auth_setup_needed(array $params): array
{
    try {
        $needed = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0;
    } catch (PDOException $exception) {
        api_fail('Database belum siap. Aktifkan MySQL dan impor database.sql terlebih dahulu.', 503, 'database_unavailable');
    }
    return ['setup_needed' => $needed];
}

function auth_setup_first_admin(array $params): array
{
    if (!auth_setup_needed($params)['setup_needed']) {
        api_fail('Akun admin sudah dibuat. Halaman pembuatan akun pertama terkunci.', 409, 'already_setup');
    }

    $username = api_str('username');
    $password = (string) (api_input('password') ?? '');
    $name = api_str('name') ?: $username;

    if (!preg_match('/^[a-zA-Z0-9_.-]{4,40}$/', $username)) {
        api_fail('Username harus 4–40 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.', 422, 'validation');
    }
    if (strlen($password) < 10) {
        api_fail('Password harus terdiri dari minimal 10 karakter.', 422, 'validation');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ((int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0) {
            throw new RuntimeException('Akun admin sudah dibuat.');
        }
        $statement = $pdo->prepare('INSERT INTO admins (username, password_hash, name) VALUES (:username, :password_hash, :name)');
        $statement->execute([
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'name' => $name,
        ]);
        $adminId = (int) $pdo->lastInsertId();
        $pdo->commit();
    } catch (RuntimeException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        api_fail($exception->getMessage(), 409, 'already_setup');
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        api_fail_db($exception);
    }

    return ['created' => true, 'id' => $adminId, 'username' => $username];
}

function auth_login(array $params): array
{
    $username = api_str('username');
    $password = (string) (api_input('password') ?? '');

    if ($username === '' || $password === '') {
        api_fail('Isi username dan password.', 422, 'validation');
    }

    $statement = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = :username LIMIT 1');
    $statement->execute(['username' => $username]);
    $admin = $statement->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        api_fail('Username atau password tidak sesuai.', 401, 'invalid_credentials');
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];

    return [
        'id' => (int) $admin['id'],
        'username' => (string) $admin['username'],
        'csrf_token' => csrf_token(),
    ];
}

function auth_logout(array $params): array
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
    }
    session_destroy();

    return ['logged_out' => true];
}

function auth_me(array $params): array
{
    return [
        'id' => (int) $_SESSION['admin_id'],
        'username' => (string) ($_SESSION['admin_username'] ?? ''),
    ];
}
