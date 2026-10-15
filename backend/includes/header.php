<?php
/**
 * Header bersama: navbar responsif untuk halaman publik dan halaman admin.
 * Variabel yang bisa diatur oleh halaman pemanggil:
 *   $pageTitle  -> judul halaman
 *   $adminView  -> true jika halaman berada di folder backend/admin
 */
$pageTitle = $pageTitle ?? APP_NAME;
$adminView = $adminView ?? false;
$base = $adminView ? '../' : '';                    // prefix halaman publik & aset
$adminBase = $adminView ? '' : 'backend/admin/';    // prefix halaman admin
$current = basename($_SERVER['SCRIPT_NAME']);
$root = $adminView ? '../../' : '';              // path menuju root proyek (footer/header sama)
// Area admin (setelah login) memakai sidebar kiri saja, tanpa navbar atas.
$showNavbar = !($adminView && !empty($_SESSION['admin_id']));
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#08152b">
    <meta name="description" content="ServisYuk - Pantau status servis kendaraan Anda dengan mudah cukup menggunakan nomor polisi.">
    <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= $base ?>assets/img/favicon.svg">
    <link rel="alternate icon" href="<?= $base ?>assets/img/favicon.svg">
    <link rel="stylesheet" href="<?= $base ?>assets/css/style.css">
</head>
<body>
<?= icon_sprite() ?>
<?php if ($showNavbar): ?>
<header class="site-header">
    <div class="container navbar">
        <a class="brand" href="<?= $base ?>index.php">
            <span class="brand-mark" aria-hidden="true">
                <span class="brand-mark-orbit"></span>
                <?= icon('bike', 'brand-bike') ?>
                <span class="brand-wrench"><?= icon('wrench') ?></span>
            </span>
            <span>Servis<span class="brand-accent">Yuk</span><small>BENGKEL ONLINE TERPERCAYA</small></span>
        </a>

        <button class="nav-toggle" type="button" aria-label="Buka menu navigasi" aria-expanded="false"><span></span></button>

        <ul class="nav-menu">
            <?php if ($adminView && !empty($_SESSION['admin_id'])): ?>
                <li><a class="nav-link <?= $current === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">Dashboard</a></li>
                <li><a class="nav-link <?= $current === 'servis.php' ? 'active' : '' ?>" href="servis.php">Data Servis</a></li>
                <li><a class="nav-link <?= $current === 'riwayat.php' ? 'active' : '' ?>" href="riwayat.php">Riwayat &amp; PIN</a></li>
                <li><a class="nav-link <?= $current === 'pelanggan.php' ? 'active' : '' ?>" href="pelanggan.php">Pelanggan</a></li>
                <li><a class="nav-link <?= $current === 'stok.php' ? 'active' : '' ?>" href="stok.php">Stok</a></li>
                <li><a class="nav-link <?= $current === 'register.php' ? 'active' : '' ?>" href="register.php">Tambah Admin</a></li>
                <li class="nav-cta">
                    <span class="admin-greeting">Halo, <?= e($_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin') ?></span>
                    <a class="button button-primary button-sm" href="logout.php">Keluar</a>
                </li>
            <?php else: ?>
                <li><a class="nav-link <?= $current === 'index.php' && !$adminView ? 'active' : '' ?>" href="<?= $base ?>index.php">Beranda</a></li>
                <li><a class="nav-link" href="<?= $base ?>index.php#layanan">Layanan</a></li>
                <li><a class="nav-link" href="<?= $base ?>index.php#keunggulan">Keunggulan</a></li>
                <li><a class="nav-link" href="<?= $base ?>index.php#alur">Alur Servis</a></li>
                <li><a class="nav-link" href="<?= $base ?>index.php#kontak">Kontak</a></li>
                <li class="nav-cta">
                    <a class="button button-primary button-sm" href="<?= $base ?>login-pelanggan.php">Login Pelanggan</a>
                    <a class="nav-link <?= $current === 'login.php' && $adminView ? 'active' : '' ?>" href="<?= $adminBase ?>login.php">Login Admin</a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</header>
<?php endif; ?>
<main>
