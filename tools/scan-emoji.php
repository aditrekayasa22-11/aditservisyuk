<?php
declare(strict_types=1);

/**
 * Pemindaian karakter dekoratif: entity HTML numerik, entity panah dekoratif,
 * dan emoji. Dipakai untuk memastikan semua tampilan memakai ikon SVG (icon()),
 * bukan emoji.
 *
 * Pakai: php tools/scan-emoji.php
 * Keluaran: daftar file:baris bermasalah + jumlah total. Exit code 1 bila masih ada.
 */

$root = dirname(__DIR__);

// Karakter tipografi normal yang diizinkan (bukan emoji).
$allowed = ['–', '—', '’', '‘', '“', '”', '…', '×', '°', '·'];
$problems = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    $path = $file->getPathname();
    if (preg_match('/\.(php|js|css|html|sql)$/', $path) !== 1) {
        continue;
    }
    // File sprite ikon memang berisi SVG mentah.
    if (basename($path) === 'icons.php') {
        continue;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        continue;
    }

    foreach ($lines as $index => $line) {
        $relative = substr($path, strlen($root) + 1);
        if (preg_match('/&#\d+;|&(rarr|larr|check|star|hearts);/', $line)) {
            $problems[] = $relative . ':' . ($index + 1) . ' [entity] ' . trim($line);
            continue;
        }
        if (preg_match_all('/[\x{2100}-\x{2BFF}\x{1F000}-\x{1FAFF}]/u', $line, $matches)) {
            foreach (array_unique($matches[0]) as $char) {
                if (in_array($char, $allowed, true)) {
                    continue;
                }
                $problems[] = $relative . ':' . ($index + 1)
                    . ' [' . $char . ' U+' . strtoupper(dechex(mb_ord($char))) . '] '
                    . mb_substr(trim($line), 0, 110);
            }
        }
    }
}

if ($problems === []) {
    echo "BERSIH: tidak ada entity/emoji tersisa, semua ikon memakai SVG.\n";
    exit(0);
}

echo implode(PHP_EOL, $problems) . PHP_EOL;
echo 'TOTAL MASALAH: ' . count($problems) . PHP_EOL;
exit(1);
