<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Sesi formulir tidak valid. Muat ulang halaman, lalu coba lagi.');
    }
}

function require_admin(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function rupiah(float|int|string|null $amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

function status_class(string $status): string
{
    return match ($status) {
        'Selesai', 'Lunas' => 'status-success',
        'Dikerjakan', 'Menunggu', 'Belum Dibayar' => 'status-pending',
        'Dibatalkan', 'Tidak Ditemukan' => 'status-muted',
        default => 'status-info',
    };
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}
