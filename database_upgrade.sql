-- ============================================================
-- Upgrade database ServisYuk
-- Skema kanonik yang dipakai backend/admin dan backend/api.
-- Jalankan sekali saja lewat phpMyAdmin atau baris perintah:
--   mysql -u root < database_upgrade.sql
-- Skrip ini idempoten: aman dijalankan berulang kali.
-- ============================================================
CREATE DATABASE IF NOT EXISTS servisyuk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE servisyuk;

-- 1. Pelanggan (ditambah kolom email yang dipakai backend/admin/pelanggan.php)
CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    email VARCHAR(120) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tambah kolom email bila database lama belum memilikinya.
SET @email_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'email'
);
SET @sql_email = IF(@email_exists = 0,
    'ALTER TABLE customers ADD COLUMN email VARCHAR(120) DEFAULT NULL AFTER phone',
    'SELECT 1');
PREPARE stmt_email FROM @sql_email;
EXECUTE stmt_email;
DEALLOCATE PREPARE stmt_email;

-- 2. Kendaraan (satu pelanggan bisa memiliki banyak kendaraan)
CREATE TABLE IF NOT EXISTS vehicles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    plate_number VARCHAR(20) NOT NULL,
    brand VARCHAR(80) DEFAULT NULL,
    model VARCHAR(80) DEFAULT NULL,
    year SMALLINT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_plate_number (plate_number),
    CONSTRAINT fk_vehicles_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Jenis layanan bengkel (judul & harga dasar tampil di halaman publik)
CREATE TABLE IF NOT EXISTS service_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    base_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_service_type_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Catatan pekerjaan servis (antrean, keluhan, biaya, status)
CREATE TABLE IF NOT EXISTS service_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT UNSIGNED NOT NULL,
    service_type_id INT UNSIGNED DEFAULT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('Menunggu','Dikerjakan','Selesai','Dibatalkan') NOT NULL DEFAULT 'Menunggu',
    service_date DATE NOT NULL,
    cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_service_records_vehicle (vehicle_id),
    KEY idx_service_records_status (status),
    CONSTRAINT fk_service_records_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    CONSTRAINT fk_service_records_type FOREIGN KEY (service_type_id) REFERENCES service_types(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Pembayaran (satu catatan servis satu baris pembayaran)
--    Tabel payments lama (kolom service_id/proof_path) diganti definisi ini.
DROP TABLE IF EXISTS payments;
CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_record_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    method ENUM('Tunai','Transfer','Kartu','QRIS') NOT NULL DEFAULT 'Tunai',
    status ENUM('Belum Dibayar','Lunas') NOT NULL DEFAULT 'Belum Dibayar',
    paid_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_payment_service_record (service_record_id),
    CONSTRAINT fk_payments_service_record FOREIGN KEY (service_record_id) REFERENCES service_records(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. PIN akses pelanggan (riwayat servis hanya dibuka dengan PIN)
CREATE TABLE IF NOT EXISTS service_pins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT UNSIGNED NOT NULL,
    pin_hash VARCHAR(255) NOT NULL,
    expires_at DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_service_pins_vehicle (vehicle_id),
    CONSTRAINT fk_service_pins_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Stok barang bengkel
CREATE TABLE IF NOT EXISTS inventory (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    sku VARCHAR(40) DEFAULT NULL,
    quantity INT NOT NULL DEFAULT 0,
    unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
    purchase_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_inventory_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Data awal: jenis layanan (id 1 dipakai skrip uji di folder tools/)
INSERT INTO service_types (name, description, base_price)
SELECT 'Ganti oli', 'Ganti oli mesin dan filter oli', 150000
WHERE NOT EXISTS (SELECT 1 FROM service_types WHERE name = 'Ganti oli');
INSERT INTO service_types (name, description, base_price)
SELECT 'Servis rem', 'Pemeriksaan dan penggantian kampas rem', 250000
WHERE NOT EXISTS (SELECT 1 FROM service_types WHERE name = 'Servis rem');
INSERT INTO service_types (name, description, base_price)
SELECT 'Tune up', 'Tuning mesin, busi, dan filter udara', 350000
WHERE NOT EXISTS (SELECT 1 FROM service_types WHERE name = 'Tune up');
INSERT INTO service_types (name, description, base_price)
SELECT 'Ganti ban', 'Pemasangan ban baru sekaligus spooring ringan', 40000
WHERE NOT EXISTS (SELECT 1 FROM service_types WHERE name = 'Ganti ban');
INSERT INTO service_types (name, description, base_price)
SELECT 'Servis AC', 'Pemeriksaan dan isi freon sistem pendingin', 250000
WHERE NOT EXISTS (SELECT 1 FROM service_types WHERE name = 'Servis AC');
INSERT INTO service_types (name, description, base_price)
SELECT 'Cuci dan poles', 'Pencucian bodi eksterior dan poles kilap', 120000
WHERE NOT EXISTS (SELECT 1 FROM service_types WHERE name = 'Cuci dan poles');

-- Catatan: tabel lama `services` dan `progress_notes` sudah tidak dipakai
-- backend baru. Tabel tersebut dibiarkan apa adanya agar tidak ada data hilang.
