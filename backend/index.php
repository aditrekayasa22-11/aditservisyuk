<?php
declare(strict_types=1);

/**
 * Halaman beranda lama di folder backend.
 * Halaman publik kini berada di root project (index.php & cek-status.php)
 * agar alamatnya pendek dan konsisten dengan tools/test-flow.php.
 */
header('Location: ../index.php', true, 302);
exit;
