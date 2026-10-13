# AutoVance

Website pencarian status servis kendaraan dengan backend PHP native, frontend HTML/CSS, dan database MySQL/MariaDB.

## Menjalankan di XAMPP

1. Salin folder project ke `C:\xampp\htdocs\AutoVance`.
2. Buka XAMPP Control Panel dan jalankan **Apache** serta **MySQL**.
3. Buka `http://localhost/phpmyadmin`, pilih tab **Import**, lalu impor file `database.sql`.
   - File tersebut membuat database `autovance`, tabel-tabel aplikasi, dan beberapa pilihan jenis servis awal.
   - Jika ingin menggunakan database lain, sesuaikan konfigurasi di `config.php` atau atur environment variable `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, dan `DB_PASS`.
4. Kunjungi `http://localhost/AutoVance/admin/setup-admin.php` untuk membuat akun admin pertama. Password minimal 10 karakter.
5. Buka `http://localhost/AutoVance` untuk halaman pencarian status. Admin dapat masuk dari tautan **Admin**.

## Alur aplikasi

- Pengguna memasukkan nomor polisi. Jika terdaftar, halaman menampilkan identitas kendaraan, status, riwayat servis, dan status pembayaran; jika tidak, tampil pesan kendaraan tidak ditemukan.
- Admin dapat mengelola data pelanggan dan kendaraan, membuat jenis servis dan catatan servis, memperbarui status pekerjaan, mencatat stok, serta menyimpan pembayaran.
- Akun admin pertama disimpan dengan password hash dan halaman setup terkunci otomatis setelah akun terbentuk.

## Teknologi

- PHP 8.0+ (menggunakan PDO dan MySQL)
- MySQL/MariaDB
- HTML5 dan CSS responsif
