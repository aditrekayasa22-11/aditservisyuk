# ServisYuk — PHP Native + CSS

Sistem informasi bengkel online: admin mengelola pelanggan, kendaraan, antrean servis, keluhan, biaya, status, pembayaran, PIN, dan stok. Pelanggan memantau servis cukup dengan nomor polisi (tanpa akun), dilindungi PIN bila diaktifkan.

## Kebutuhan
- XAMPP (Apache, PHP 8+, MySQL/MariaDB)
- Ekstensi PHP: PDO MySQL, cURL (hanya untuk skrip uji di `tools/`)
- Browser

## Menjalankan di XAMPP
1. Pastikan folder project ada di `C:\xampp\htdocs\ServisYuk`.
2. Nyalakan **Apache** (dan MySQL pada port 3306).
3. Import database: buka `http://localhost/phpmyadmin` → menu **Import** → pilih `database.sql` → **Go**.
   - Database lama yang strukturnya masih versi sebelumnya bisa diselaraskan dengan `database_upgrade.sql` (idempoten, aman dijalankan berulang).
   - Lewat baris perintah: `mysql -u root < database.sql`
4. Buka situs:
   - Halaman publik: `http://localhost:8080/ServisYuk/` (port **8080** pada instalasi XAMPP `C:\xampp` di mesin ini; ganti sesuai konfigurasi `Listen` di `httpd.conf`)
   - Login admin: `http://localhost:8080/ServisYuk/backend/admin/login.php`
     - Username: `admin` — Password: `password`
5. **Ganti password default** sebelum dipakai sungguhan.

## Dua role

### Admin (`backend/admin/`)
- Login username + password (hash `password_verify`, sesi di-regenerate, CSRF pada semua form).
- `dashboard.php` — statistik pelanggan, kendaraan, servis aktif, pendapatan hari ini, tunggakan, stok; ubah status antrean.
- `servis.php` — tambah/ubah/hapus catatan servis + jenis layanan, pencarian, filter status & plat.
- `riwayat.php` — riwayat servis per kendaraan + atur/hapus PIN akses (4–6 digit, berlaku terbatas).
- `pelanggan.php` — pelanggan, kendaraan, pembayaran.
- `stok.php` — barang masuk/keluar, stok minimum.
- `logout.php` — hapus sesi dengan aman.

### Pelanggan (`cek-status.php`)
- Tanpa registrasi. Cukup nomor polisi; spasi dan huruf besar/kecil diabaikan.
- Bila kendaraan punya PIN, riwayat baru terbuka setelah PIN diverifikasi di server (percobaan dibatasi).
- Menampilkan kendaraan, keluhan, tanggal masuk, status pengerjaan, estimasi biaya, pembayaran, dan riwayat servis. Data pribadi pemilik lain (telepon/email) tidak pernah ditampilkan.

## API (`backend/api/`)
Router tunggal `backend/api/index.php?path=/endpoint`, respons JSON `{success, data|message, code}`. Contoh: `http://localhost:8080/ServisYuk/backend/api/index.php?path=/dashboard` (jalur cantik `/backend/api/...` belum aktif karena tidak ada `.htaccess` rewrite).

| Metode | Endpoint | Keterangan |
|---|---|---|
| GET | `/auth/csrf`, `/auth/setup-needed`, `/auth/me` | token, status setup, sesi admin |
| POST | `/auth/login`, `/auth/logout`, `/auth/setup-first-admin` | autentikasi admin |
| GET | `/dashboard` | ringkasan statistik |
| GET/POST/PUT/DELETE | `/customers`, `/vehicles`, `/service-types`, `/services`, `/payments`, `/inventory` | CRUD terproteksi sesi + CSRF |
| GET | `/lookup?plate=...&pin=...` | publik; minta `pin` bila kendaraan dilindungi PIN |

Aturan keamanan: semua tulis butuh sesi admin + header `X-CSRF-Token` (GET publik kecuali `/lookup` tidak butuh), password disimpan sebagai hash, PIN disimpan sebagai hash dengan tanggal kedaluwarsa.

## Struktur folder
- `index.php` — halaman publik (hero, layanan, keunggulan, alur, kontak)
- `cek-status.php` — pencarian status servis pelanggan tanpa login
- `login-pelanggan.php` — login portal pelanggan **tanpa username/password**: nomor polisi + verifikasi PIN (OTP disiapkan di `backend/config.php` lewat `CUSTOMER_OTP_ENABLED`)
- `akun-pelanggan.php` — halaman portal: data kendaraan, keluhan, tanggal masuk, status, estimasi biaya, riwayat (hanya milik sesi yang login)
- `logout-pelanggan.php` — keluar dari portal pelanggan (sesi dihapus total)
- `assets/css/style.css`, `assets/js/app.js` — design system & interaksi (salinan identik ada di `backend/assets/` untuk halaman admin)
- `assets/img/hero-garage.jpg` — foto latar hero beranda (kelas `.hero-photo` di `index.php`; kredit & cara mengganti ada di `assets/img/CREDIT.txt`)
- `backend/includes/functions.php` — helper bersama: PDO, CSRF, flash, format rupiah/status, PIN, sesi & verifikasi portal pelanggan
- `backend/includes/icons.php` — sprite ikon SVG inline + helper `icon('nama')` (set ikon Lucide, lisensi ISC; dirender sekali per halaman oleh `header.php`)
- `backend/includes/header.php`, `footer.php` — layout navbar/footer (publik & admin)
- `backend/admin/` — panel admin; `backend/admin/includes/sidebar.php`
- `backend/api/` — API REST (`index.php`, `routes.php`, `controllers/`)
- `database.sql` — skema instalasi baru; `database_upgrade.sql` — sinkronisasi skema
- `tools/` — skrip uji lokal & utilitas (`build-icons.php` menyusun ulang sprite, `scan-emoji.php` memastikan tidak ada emoji/entity sisa)

## Skema database
`admins`, `customers`, `vehicles`, `service_types`, `service_records`, `payments`, `service_pins`, `inventory`.

Catatan: tabel legacy `services` dan `progress_notes` tidak lagi dipakai dan dibiarkan apa adanya agar tidak ada data yang hilang. Unggahan bukti transfer (`uploads/proofs/`) juga tidak dipakai oleh alur baru.

## Pengujian
Semua uji berjalan terhadap server yang sudah hidup:

```
php tools/test-flow.php    http://localhost:8080/ServisYuk admin password
php tools/test-pin.php
php tools/test-customer.php http://localhost:8080/ServisYuk
php tools/test-api.php     http://localhost:8080/ServisYuk admin password
php tools/test-admin.php   http://localhost:8080/ServisYuk admin password
php tools/test-setup.php   http://localhost:8080/ServisYuk
php tools/test-icons.php   http://localhost:8080/ServisYuk admin password
php tools/test-pelanggan.php http://localhost:8080/ServisYuk
php tools/scan-emoji.php   (tanpa server; scan source)
```

Cakupan: beranda & daftar layanan, proteksi halaman admin, login benar/salah, seluruh endpoint API (CRUD, validasi, CSRF, lookup + PIN), alur pelanggan (belum terdaftar → PIN → riwayat), seluruh form admin (tambah/ubah/hapus, filter, validasi, flash, logout), setup admin pertama (termasuk pemulihan baris admin semula), tampilan ikon (sprite ter-render sekali per halaman, semua `<use>` menunjuk simbol yang ada, panel ber-PIN/tombol aksi memakai ikon yang benar, tanpa emoji/entity), serta portal pelanggan (menu Login Pelanggan berdampingan dengan Login Admin, login tanpa username/password, pencocokan nomor polisi tanpa memedulikan huruf besar/kecil dan spasi, verifikasi PIN benar/salah/kuota, privasi antar-pelanggan, logout aman, struktur OTP). Data uji dibersihkan otomatis; data pelanggan asli tidak disentuh.

## Catatan keamanan
- PIN dan password tidak pernah disimpan plaintext; telepon/email pelanggan tidak dikirim ke halaman pelanggan.
- Portal pelanggan tidak menyimpan username/password: sesi hanya berisi `customer_id`, seluruh query terikat sesi itu sehingga data pelanggan lain mustahil ikut terbaca; sesi kedaluwarsa setelah 60 menit tanpa aktivitas (`CUSTOMER_SESSION_MINUTES`).
- OTP disiapkan sebagai lapisan kedua: aktifkan `CUSTOMER_OTP_ENABLED` lalu isi `customer_otp_send()`/`customer_otp_verify()` di `backend/includes/functions.php` — titik sambungnya sudah dipanggil `login-pelanggan.php`.
- Percobaan PIN dibatasi per sesi, tetapi untuk produksi tambahkan rate limiting berbasis IP dan HTTPS.
- Untuk produksi: HTTPS, `session` hardening, backup database berkala, dan ganti kredensial default.
