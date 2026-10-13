<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

/**
 * Kirim respons JSON lalu hentikan eksekusi.
 */
function api_json(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_ok(mixed $data = null): never
{
    api_json(['success' => true, 'data' => $data]);
}

function api_fail(string $message, int $status = 400, string $code = 'error'): never
{
    api_json(['success' => false, 'message' => $message, 'code' => $code], $status);
}

/**
 * Terjemahkan exception database menjadi respons API yang aman.
 */
function api_fail_db(PDOException $exception): never
{
    if ($exception->getCode() === '23000') {
        api_fail('Data sudah terdaftar atau masih digunakan oleh data lain.', 409, 'conflict');
    }
    api_fail('Terjadi kesalahan pada database.', 500, 'database_error');
}

/**
 * Badan request JSON (cached). Masih mendukung form-encoded sebagai cadangan.
 */
function api_body(): array
{
    static $body = null;
    if ($body !== null) {
        return $body;
    }
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || $raw === '') {
        $body = [];
        return $body;
    }
    $decoded = json_decode($raw, true);
    $body = is_array($decoded) ? $decoded : [];
    return $body;
}

function api_input(string $key, mixed $default = null): mixed
{
    $body = api_body();
    if (array_key_exists($key, $body)) {
        return $body[$key];
    }
    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }
    return $default;
}

function api_str(string $key, string $default = ''): string
{
    $value = api_input($key, $default);
    return is_scalar($value) ? trim((string) $value) : $default;
}

function api_int(string $key, int $default = 0): int
{
    $value = api_input($key, $default);
    return is_numeric($value) ? (int) $value : $default;
}

function api_float(string $key, float $default = 0.0): float
{
    $value = api_input($key, $default);
    return is_numeric($value) ? (float) $value : $default;
}

function api_nullable(string $key): ?string
{
    $value = api_str($key);
    return $value === '' ? null : $value;
}

function require_admin_api(): void
{
    if (empty($_SESSION['admin_id'])) {
        api_fail('Sesi admin tidak ditemukan. Silakan login kembali.', 401, 'unauthorized');
    }
}

function require_csrf_api(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        api_fail('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.', 403, 'csrf_failed');
    }
}

function require_text(bool $condition, string $message): void
{
    if (!$condition) {
        api_fail($message, 422, 'validation');
    }
}

/**
 * Validasi nilai enum opsional; mengembalikan nilai valid atau null.
 */
function valid_enum(?string $value, array $allowed, string $message): ?string
{
    if ($value === null || in_array($value, $allowed, true)) {
        return $value;
    }
    api_fail($message, 422, 'validation');
}

/**
 * Pastikan id benar-benar ada di tabel tertentu.
 */
function assert_exists(PDO $pdo, string $table, int $id, string $message): void
{
    require_text($id > 0, $message);
    $statement = $pdo->prepare("SELECT 1 FROM {$table} WHERE id = :id");
    $statement->execute(['id' => $id]);
    if (!$statement->fetchColumn()) {
        api_fail($message, 404, 'not_found');
    }
}

function valid_date(?string $value, string $message): string
{
    if ($value === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || strtotime($value) === false) {
        api_fail($message, 422, 'validation');
    }
    return $value;
}

function record_or_404(mixed $record, string $message): array
{
    if (!$record) {
        api_fail($message, 404, 'not_found');
    }
    return is_array($record) ? $record : [];
}
