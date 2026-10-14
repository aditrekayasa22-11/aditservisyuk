<?php
declare(strict_types=1);
/**
 * Penggabung set ikon Lucide menjadi sprite PHP.
 * Jalankan sekali: php tools/build-icons.php
 * Hasil: backend/includes/icons.php
 */
$src = getenv('TEMP') ?: sys_get_temp_dir();
$src = rtrim($src, '\\/') . '/sy-icons';

$names = [
    'users', 'car', 'wrench', 'circle-check', 'wallet', 'triangle-alert',
    'package', 'boxes', 'coins', 'layout-dashboard', 'clipboard-list', 'history',
    'external-link', 'log-out', 'droplet', 'disc', 'gauge', 'circle-dot',
    'snowflake', 'sparkles', 'settings', 'check', 'map-pin', 'phone',
    'mail', 'clock', 'arrow-right', 'search', 'shield-check', 'lock',
    'circle-help', 'pencil', 'trash-2', 'refresh-cw', 'plus', 'key-round',
    'arrow-left', 'log-in', 'user-plus', 'circle-alert',
];

$symbols = [];
$missing = [];

foreach ($names as $name) {
    $file = $src . '/' . $name . '.svg';
    if (!is_file($file)) {
        $missing[] = $name;
        continue;
    }
    $svg = file_get_contents($file);
    // Buang komentar lisensi & tag svg pembuka (semua atributnya pindah ke <symbol>).
    $svg = preg_replace('/<!--.*?-->/s', '', $svg);
    if (!preg_match('/<svg\b[^>]*>(.*)<\/svg>/s', $svg, $m)) {
        $missing[] = $name;
        continue;
    }
    $inner = trim(preg_replace('/\s+/', ' ', $m[1]));
    $symbols[] = '<symbol id="ic-' . $name . '" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
        . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $inner . '</symbol>';
}

if ($missing) {
    fwrite(STDERR, "Ikon gagal diproses: " . implode(', ', $missing) . "\n");
    exit(1);
}

$sprite = implode('', $symbols);

$php = <<<'PHP'
<?php
declare(strict_types=1);

/**
 * Sistem ikon SVG inline (set ikon Lucide, lisensi ISC — https://lucide.dev).
 *
 * Dipakai agar tampilan memakai ikon vektor sesungguhnya, bukan emoji/entity
 * HTML, dan tetap tampil tanpa internet (semua ikon disimpan di halaman).
 *
 * Pemakaian:
 *   di header, sekali per halaman : <?= icon_sprite() ?>
 *   di template                   : <?= icon('wrench') ?>
 *                                    <?= icon('trash-2', 'icon-sm') ?>
 */

const ICON_SPRITE = '__SPRITE__';

/** Render sprite ikon. Panggil tepat sekali setelah <body> (ada di header.php). */
function icon_sprite(): void
{
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="icon-sprite" aria-hidden="true" focusable="false">'
        . ICON_SPRITE . '</svg>';
}

/**
 * Render satu ikon.
 * @param string $name  nama ikon di sprite, mis. 'wrench', 'circle-check'
 * @param string $class kelas tambahan opsional, mis. 'icon-lg'
 */
function icon(string $name, string $class = ''): string
{
    $name = preg_replace('/[^a-z0-9-]/', '', strtolower($name)) ?? '';
    if ($name === '') {
        return '';
    }
    $class = $class !== '' ? ' icon ' . trim($class) : ' icon';
    return '<svg class="' . trim($class) . '" aria-hidden="true" focusable="false">'
        . '<use href="#ic-' . $name . '"></use></svg>';
}
PHP;

$php = str_replace('__SPRITE__', $sprite, $php);

$out = dirname(__DIR__) . '/backend/includes/icons.php';
file_put_contents($out, $php);
echo "Ditulis: {$out} (" . strlen($php) . " bytes, " . count($symbols) . " ikon)\n";
