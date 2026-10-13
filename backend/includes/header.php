<?php
$pageTitle = $pageTitle ?? APP_NAME;
$adminView = $adminView ?? false;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#101c2c">
    <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= $adminView ? '../assets/css/style.css' : 'assets/css/style.css' ?>">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= $adminView ? '../index.php' : 'index.php' ?>">
        <span class="brand-mark">A</span>
        <span>Auto<span class="brand-accent">Vance</span><small>CARE THAT MOVES YOU</small></span>
    </a>
    <nav class="header-nav">
        <?php if ($adminView && !empty($_SESSION['admin_id'])): ?>
            <span class="admin-greeting">Halo, <?= e($_SESSION['admin_username'] ?? 'Admin') ?></span>
            <a class="nav-link" href="logout.php">Keluar</a>
        <?php else: ?>
            <a class="nav-link" href="<?= $adminView ? '../index.php' : 'index.php' ?>">Beranda</a>
            <a class="nav-link nav-admin" href="<?= $adminView ? 'login.php' : 'admin/login.php' ?>">Admin <span aria-hidden="true">↗</span></a>
        <?php endif; ?>
    </nav>
</header>
<main>
