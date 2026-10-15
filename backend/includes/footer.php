<?php
/**
 * Footer bersama: info kontak + tautan cepat.
 * $adminView menentukan prefix aset halaman admin ('../').
 */
$adminView = $adminView ?? false;
$base = $adminView ? '../' : '';
$adminBase = $adminView ? '' : 'backend/admin/';
$root = $adminView ? '../../' : '';
?>
</main>
<footer class="site-footer">
    <div class="container">
        <div class="footer-top">
            <div class="footer-brand">
                <a class="brand" href="<?= $base ?>index.php">
                    <span class="brand-mark" aria-hidden="true">
                        <span class="brand-mark-orbit"></span>
                        <?= icon('bike', 'brand-bike') ?>
                        <span class="brand-wrench"><?= icon('wrench') ?></span>
                    </span>
                    <span>Servis<span class="brand-accent">Yuk</span></span>
                </a>
                <p class="footer-about">Bengkel online yang membantu Anda memantau status servis kendaraan secara transparan cukup dengan nomor polisi.</p>
            </div>

            <div class="footer-col">
                <h4>Navigasi</h4>
                <ul>
                    <li><a href="<?= $base ?>index.php">Beranda</a></li>
                    <li><a href="<?= $base ?>cek-status.php">Cek Status Servis</a></li>
                    <li><a href="<?= $base ?>index.php#layanan">Daftar Layanan</a></li>
                    <li><a href="<?= $base ?>index.php#keunggulan">Keunggulan</a></li>
                    <li><a href="<?= $adminBase ?>login.php">Login Admin</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Hubungi Kami</h4>
                <ul>
                    <li>Jalan Hasyim Asyari No. 22, Madiun</li>
                    <li><a href="tel:+6281234567890">+62 812-3456-7890</a></li>
                    <li><a href="mailto:info@servisyuk.id">info@servisyuk.id</a></li>
                    <li>Senin - Sabtu, 08.00 - 17.00</li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> <?= APP_NAME ?>. Semua hak dilindungi.</span>
            <span>Dibuat dengan PHP Native, HTML &amp; CSS murni</span>
        </div>
    </div>
</footer>
<script src="<?= $base ?>assets/js/app.js"></script>
</body>
</html>
