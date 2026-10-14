<?php
/**
 * Sidebar admin. Dipakai semua halaman di folder backend/admin.
 * Variabel: $currentPage = 'dashboard' | 'servis' | 'riwayat' | 'pelanggan' | 'stok'
 */
$currentPage = $currentPage ?? 'dashboard';
$menu = [
    'dashboard' => ['dashboard.php', 'layout-dashboard', 'Dashboard'],
    'servis' => ['servis.php', 'clipboard-list', 'Data Servis'],
    'riwayat' => ['riwayat.php', 'history', 'Riwayat &amp; PIN'],
    'pelanggan' => ['pelanggan.php', 'users', 'Pelanggan'],
    'stok' => ['stok.php', 'package', 'Stok Barang'],
];
?>
<aside class="dashboard-sidebar">
    <span class="sidebar-label">MENU UTAMA</span>
    <?php foreach ($menu as $key => $item): ?>
        <a class="sidebar-link <?= $currentPage === $key ? 'active' : '' ?>" href="<?= $item[0] ?>">
            <span><?= icon($item[1]) ?></span> <?= $item[2] ?>
        </a>
    <?php endforeach; ?>

    <div class="sidebar-bottom">
        <span class="sidebar-label">SISTEM</span>
        <a class="sidebar-link" href="../index.php"><span><?= icon('external-link') ?></span> Halaman Pelanggan</a>
        <a class="sidebar-link" href="logout.php"><span><?= icon('log-out') ?></span> Keluar</a>
    </div>
</aside>
