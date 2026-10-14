<?php
declare(strict_types=1);

/**
 * Utilitas backup & restore database.
 *
 * - db_dump()   : menghasilkan skrip SQL (CREATE TABLE + INSERT) seluruh tabel.
 * - db_restore(): menjalankan kembali skrip SQL hasil dump (atau file .sql lain).
 *
 * Implementasi murni PHP sehingga tidak bergantung pada mysqldump/mysql client.
 */

/** Folder penyimpanan berkas backup (dibuat otomatis bila belum ada). */
function backup_storage_dir(): string
{
    $dir = dirname(__DIR__) . '/storage/backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

/** Nama berkas backup yang aman (mencegah path traversal). */
function valid_backup_name(string $name): bool
{
    return basename($name) === $name
        && strpos($name, '..') === false
        && (bool) preg_match('/^[A-Za-z0-9._-]+\.sql$/', $name);
}

/** Daftar berkas backup di folder penyimpanan (terbaru lebih dulu). */
function list_backups(): array
{
    $dir = backup_storage_dir();
    $items = [];
    foreach (glob($dir . '/*.sql') ?: [] as $path) {
        $items[] = [
            'name' => basename($path),
            'size' => (int) filesize($path),
            'time' => (int) filemtime($path),
        ];
    }
    usort($items, static fn (array $a, array $b): int => $b['time'] <=> $a['time']);
    return $items;
}

/** Format ukuran berkas agar mudah dibaca. */
function format_bytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $value = (float) $bytes;
    $index = 0;
    while ($value >= 1024 && $index < count($units) - 1) {
        $value /= 1024;
        $index++;
    }
    return ($index === 0 ? (string) (int) $value : number_format($value, 1, ',', '.')) . ' ' . $units[$index];
}

/** Hasilkan skrip SQL backup lengkap (struktur + data seluruh tabel). */
function db_dump(PDO $pdo): string
{
    $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_COLUMN);

    $sql = "-- =========================================================\n";
    $sql .= "-- Backup database ServisYuk\n";
    $sql .= '-- Dibuat: ' . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- =========================================================\n\n";
    $sql .= "SET NAMES utf8mb4;\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($tables as $table) {
        $createRow = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM);
        if (!is_array($createRow) || !isset($createRow[1])) {
            continue;
        }

        $sql .= "--\n-- Struktur tabel `$table`\n--\n";
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $createRow[1] . ";\n\n";

        $rows = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            continue;
        }

        $columns = array_keys($rows[0]);
        $columnList = '`' . implode('`, `', $columns) . '`';
        $values = [];
        foreach ($rows as $row) {
            $encoded = [];
            foreach ($columns as $column) {
                $value = $row[$column] ?? null;
                if ($value === null) {
                    $encoded[] = 'NULL';
                } elseif (is_int($value) || is_float($value)) {
                    $encoded[] = (string) $value;
                } else {
                    $encoded[] = $pdo->quote((string) $value);
                }
            }
            $values[] = '  (' . implode(', ', $encoded) . ')';
        }

        $sql .= "--\n-- Data tabel `$table`\n--\n";
        $sql .= "INSERT INTO `$table` ($columnList) VALUES\n";
        $sql .= implode(",\n", $values) . ";\n\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    return $sql;
}

/**
 * Pisahkan skrip SQL menjadi pernyataan tunggal.
 * Menghormati string terkuote dan membuang komentar (-- , # , / * * /).
 *
 * @return string[]
 */
function split_sql_statements(string $sql): array
{
    $statements = [];
    $current = '';
    $length = strlen($sql);
    $quote = '';

    for ($i = 0; $i < $length; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($quote === '') {
            if ($ch === '-' && $next === '-') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
            if ($ch === '#') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
            if ($ch === '/' && $next === '*') {
                $i += 2;
                while ($i < $length && !($sql[$i] === '*' && $i + 1 < $length && $sql[$i + 1] === '/')) {
                    $i++;
                }
                $i += 1; // lewati '/'
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $quote = $ch;
                $current .= $ch;
                continue;
            }
            if ($ch === ';') {
                if (trim($current) !== '') {
                    $statements[] = $current;
                }
                $current = '';
                continue;
            }
            $current .= $ch;
            continue;
        }

        // Di dalam kutipan.
        $current .= $ch;
        if ($ch === '\\' && $quote !== '`') {
            if ($i + 1 < $length) {
                $current .= $sql[++$i];
            }
            continue;
        }
        if ($ch === $quote) {
            $quote = '';
        }
    }

    if (trim($current) !== '') {
        $statements[] = $current;
    }

    return $statements;
}

/** Jalankan skrip SQL restore. */
function db_restore(PDO $pdo, string $sql): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    try {
        foreach (split_sql_statements($sql) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
