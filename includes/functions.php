<?php
require_once __DIR__ . '/../backend/includes/icons.php';
function e($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'); }
function is_admin(): bool { return !empty($_SESSION['admin']['id']); }
function require_admin(): void { if (!is_admin()) { header('Location: index.php?page=login'); exit; } }
function csrf_token(): string { if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void { if($_SERVER['REQUEST_METHOD']==='POST' && !hash_equals($_SESSION['csrf_token']??'', $_POST['csrf_token']??'')){http_response_code(419);exit('Sesi formulir berakhir. Muat ulang halaman lalu coba lagi.');} }
function flash(string $type,string $message): void { $_SESSION['flash']=['type'=>$type,'message'=>$message]; }
function render_flash(): void { if(!empty($_SESSION['flash'])){$f=$_SESSION['flash'];unset($_SESSION['flash']);echo '<div class="alert alert-'.e($f['type']).'">'.e($f['message']).'</div>';} }
function rupiah($amount): string { return 'Rp '.number_format((float)$amount,0,',','.'); }
function format_datetime($value): string { return $value ? date('d M Y, H:i',strtotime($value)) : 'Belum ditentukan'; }
function status_badge(string $status): string {
    $class='badge-blue'; if(in_array($status,['Selesai','Siap diambil','Lunas'],true))$class='badge-green';elseif(in_array($status,['Menunggu suku cadang','Menunggu verifikasi'],true))$class='badge-amber';elseif(in_array($status,['Ditolak'],true))$class='badge-rose';
    return '<span class="badge '.$class.'">'.e($status).'</span>';
}
function render_header(string $title,bool $admin=true): void {
    ?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($title) ?> — ServisYuk</title><link rel="stylesheet" href="assets/css/style.css"></head><body class="<?= $admin?'admin-body':'public-body' ?>"><?php
}
function render_footer(): void { ?><script src="assets/js/app.js"></script></body></html><?php }
function admin_layout_start(string $active): void { ?>
<div class="admin-shell"><aside class="sidebar"><a class="brand" href="index.php?page=dashboard"><span class="brand-mark">S</span><span>Servis<span class="brand-accent">Yuk</span><small>ADMIN WORKSPACE</small></span></a><div class="side-label">MENU UTAMA</div>
<a class="side-link <?= $active==='dashboard'?'active':'' ?>" href="index.php?page=dashboard"><span><?= icon('layout-dashboard') ?></span>Dashboard</a>
<a class="side-link <?= $active==='services'?'active':'' ?>" href="index.php?page=services"><span><?= icon('car') ?></span>Data Servis Kendaraan</a>
<div class="side-note"><span class="side-note-icon"><?= icon('sparkles') ?></span><b>Informasi jelas,<br>pelanggan lebih tenang.</b><p>Perbarui status servis secara berkala agar pelanggan tidak perlu bertanya berulang kali.</p></div>
<div class="sidebar-bottom"><div class="avatar"><?= e(strtoupper(substr($_SESSION['admin']['name']??'A',0,1))) ?></div><div class="user-info"><b><?= e($_SESSION['admin']['name']??'Admin') ?></b><small>Administrator</small></div><a class="logout-icon" title="Logout" href="index.php?page=logout"><?= icon('external-link') ?></a></div></aside>
<div class="admin-main"><header class="topbar"><div><span class="mobile-brand">Servis<span class="brand-accent">Yuk</span></span><span class="topbar-caption">Workspace / <?= e(ucwords(str_replace('_',' ',$active))) ?></span></div><div class="topbar-right"><span class="online-dot"></span><span>Admin aktif</span><span class="top-avatar"><?= e(strtoupper(substr($_SESSION['admin']['name']??'A',0,1))) ?></span></div></header><main class="content">
<?php }
function admin_layout_end(): void { ?></main><footer class="admin-footer">ServisYuk · Sistem Informasi Servis Kendaraan</footer></div></div><?php }
