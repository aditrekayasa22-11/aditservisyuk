<?php
/**
 * Sidebar admin. Dipakai semua halaman di folder backend/admin.
 * Variabel: $currentPage = 'dashboard' | 'servis' | 'pembayaran' | 'riwayat' | 'pelanggan' | 'stok' | 'register'
 */
$currentPage = $currentPage ?? 'dashboard';
$groups = [
    'OPERASIONAL' => [
        'dashboard' => ['dashboard.php', 'layout-dashboard', 'Dashboard'],
        'servis' => ['servis.php', 'clipboard-list', 'Data Servis'],
        'pembayaran' => ['pembayaran.php', 'wallet', 'Verifikasi Pembayaran'],
        'riwayat' => ['riwayat.php', 'history', 'Riwayat &amp; PIN'],
    ],
    'MANAJEMEN' => [
        'pelanggan' => ['pelanggan.php', 'users', 'Pelanggan'],
        'stok' => ['stok.php', 'package', 'Stok Barang'],
    ],
    'SISTEM' => [
        'backup' => ['backup.php', 'database', 'Backup &amp; Restore'],
        'register' => ['register.php', 'user-plus', 'Tambah Admin'],
        'halaman-pelanggan' => ['../index.php', 'external-link', 'Halaman Pelanggan'],
    ],
];
$adminName = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
$adminInitial = strtoupper(substr(trim((string) $adminName), 0, 1)) ?: 'A';
?>
<aside class="dashboard-sidebar">
    <a class="brand sidebar-brand" href="dashboard.php">
        <span class="brand-mark" aria-hidden="true">
            <span class="brand-mark-orbit"></span>
            <?= icon('bike', 'brand-bike') ?>
            <span class="brand-wrench"><?= icon('wrench') ?></span>
        </span>
        <span>Servis<span class="brand-accent">Yuk</span><small>BENGKEL MOTOR</small></span>
    </a>

    <nav class="sidebar-nav">
        <?php foreach ($groups as $label => $items): ?>
            <span class="sidebar-label"><?= $label ?></span>
            <?php foreach ($items as $key => $item): ?>
                <a class="sidebar-link <?= $currentPage === $key ? 'active' : '' ?>" href="<?= $item[0] ?>">
                    <span><?= icon($item[1]) ?></span> <?= $item[2] ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-user">
        <span class="sidebar-user-avatar"><?= e($adminInitial) ?></span>
        <span class="sidebar-user-meta">
            <strong><?= e($adminName) ?></strong>
            <span>Administrator</span>
        </span>
        <a class="sidebar-user-logout" href="logout.php" title="Keluar" aria-label="Keluar"><?= icon('log-out') ?></a>
    </div>
</aside>
