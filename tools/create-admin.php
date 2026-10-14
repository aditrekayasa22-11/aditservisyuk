<?php
declare(strict_types=1);
/**
 * Skrip bantuan (hanya untuk pengujian lokal):
 * membuat akun admin jika belum ada.
 * Cara pakai: C:\xampp\php\php.exe tools\create-admin.php <username> <password>
 */
require_once __DIR__ . '/../backend/includes/functions.php';

$username = $argv[1] ?? 'admin';
$password = $argv[2] ?? 'password1234';
$name = $argv[3] ?? $username;

try {
    $pdo = db();
    $count = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    if ($count > 0) {
        echo "Akun admin sudah ada (" . $count . " akun). Tidak ada perubahan.\n";
        exit(0);
    }
    $pdo->prepare('INSERT INTO admins (username, password_hash, name) VALUES (:u, :p, :n)')
        ->execute(['u' => $username, 'p' => password_hash($password, PASSWORD_DEFAULT), 'n' => $name]);
    echo "Akun admin '{$username}' berhasil dibuat.\n";
} catch (PDOException $exception) {
    echo "Gagal: " . $exception->getMessage() . "\n";
    exit(1);
}
