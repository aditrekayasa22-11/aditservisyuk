<?php
declare(strict_types=1);

/**
 * Keluar dari portal pelanggan: sesi dihapus total, lalu kembali ke halaman login.
 */
require_once __DIR__ . '/backend/includes/functions.php';

customer_logout();
flash('Anda telah keluar dari portal pelanggan.', 'success');
redirect('login-pelanggan.php');
