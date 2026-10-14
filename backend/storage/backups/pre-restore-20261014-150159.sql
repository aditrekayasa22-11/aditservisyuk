-- =========================================================
-- Backup database ServisYuk
-- Dibuat: 2026-10-14 15:01:59
-- =========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

--
-- Struktur tabel `admins`
--
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Data tabel `admins`
--
INSERT INTO `admins` (`id`, `username`, `password_hash`, `name`, `created_at`) VALUES
  (1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', '2026-10-14 19:32:57'),
  (2, 'Shanum', '$2y$10$7VM66F96qFUpgDPH17TG7.2BOIdmCsbcDI9wg9Y0IHLq9.9okx4eO', 'manager', '2026-10-14 19:41:42'),
  (3, 'budi', '$2y$10$udeD7Fa9mQEcRGOAJoEWwe8vzWEGSO9G7o7KZr8vX3T0Qdh72GjM.', 'manager', '2026-10-14 19:42:22');

--
-- Struktur tabel `customers`
--
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Data tabel `customers`
--
INSERT INTO `customers` (`id`, `name`, `phone`, `email`, `created_at`) VALUES
  (1, 'Budi Santoso', '081234567890', 'budi@example.com', '2026-10-14 19:36:26'),
  (2, 'Siti Aminah', '081298765432', 'siti@example.com', '2026-10-14 19:36:26'),
  (3, 'Andi Pratama', '085611122233', 'andi@example.com', '2026-10-14 19:36:26'),
  (4, 'Dewi Lestari', '087755544433', 'dewi@example.com', '2026-10-14 19:36:26');

--
-- Struktur tabel `inventory`
--
DROP TABLE IF EXISTS `inventory`;
CREATE TABLE `inventory` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `sku` varchar(40) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `unit` varchar(20) NOT NULL DEFAULT 'pcs',
  `purchase_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_inventory_sku` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Struktur tabel `payments`
--
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `service_record_id` int(10) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `method` enum('Tunai','Transfer','Kartu','QRIS') NOT NULL DEFAULT 'Tunai',
  `status` enum('Belum Dibayar','Lunas') NOT NULL DEFAULT 'Belum Dibayar',
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_payment_service_record` (`service_record_id`),
  CONSTRAINT `fk_payments_service_record` FOREIGN KEY (`service_record_id`) REFERENCES `service_records` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Data tabel `payments`
--
INSERT INTO `payments` (`id`, `service_record_id`, `amount`, `method`, `status`, `paid_at`, `created_at`) VALUES
  (1, 1, '150000.00', 'Tunai', 'Lunas', '2026-10-14 19:36:26', '2026-10-14 19:36:26'),
  (2, 4, '250000.00', 'Transfer', 'Lunas', '2026-10-14 19:36:26', '2026-10-14 19:36:26'),
  (3, 5, '120000.00', 'QRIS', 'Lunas', '2026-10-14 19:36:26', '2026-10-14 19:36:26');

--
-- Struktur tabel `service_pins`
--
DROP TABLE IF EXISTS `service_pins`;
CREATE TABLE `service_pins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(10) unsigned NOT NULL,
  `pin_hash` varchar(255) NOT NULL,
  `expires_at` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_service_pins_vehicle` (`vehicle_id`),
  CONSTRAINT `fk_service_pins_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Struktur tabel `service_records`
--
DROP TABLE IF EXISTS `service_records`;
CREATE TABLE `service_records` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(10) unsigned NOT NULL,
  `service_type_id` int(10) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Menunggu','Dikerjakan','Selesai','Dibatalkan') NOT NULL DEFAULT 'Menunggu',
  `service_date` date NOT NULL,
  `cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_service_records_vehicle` (`vehicle_id`),
  KEY `idx_service_records_status` (`status`),
  KEY `fk_service_records_type` (`service_type_id`),
  CONSTRAINT `fk_service_records_type` FOREIGN KEY (`service_type_id`) REFERENCES `service_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_service_records_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Data tabel `service_records`
--
INSERT INTO `service_records` (`id`, `vehicle_id`, `service_type_id`, `description`, `status`, `service_date`, `cost`, `created_at`) VALUES
  (1, 1, 1, 'Ganti oli mesin + filter oli', 'Selesai', '2026-10-09', '150000.00', '2026-10-14 19:36:26'),
  (2, 2, 2, 'Ganti kampas rem depan', 'Dikerjakan', '2026-10-13', '250000.00', '2026-10-14 19:36:26'),
  (3, 3, 3, 'Tune up mesin + ganti busi', 'Menunggu', '2026-10-14', '350000.00', '2026-10-14 19:36:26'),
  (4, 4, 5, 'Isi freon + bersihkan evaporator', 'Selesai', '2026-10-04', '250000.00', '2026-10-14 19:36:26'),
  (5, 5, 6, 'Cuci bodi + poles kilap', 'Selesai', '2026-10-12', '120000.00', '2026-10-14 19:36:26');

--
-- Struktur tabel `service_types`
--
DROP TABLE IF EXISTS `service_types`;
CREATE TABLE `service_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `base_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_service_type_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Data tabel `service_types`
--
INSERT INTO `service_types` (`id`, `name`, `description`, `base_price`, `created_at`) VALUES
  (1, 'Ganti oli', 'Ganti oli mesin dan filter oli', '150000.00', '2026-10-14 19:32:57'),
  (2, 'Servis rem', 'Pemeriksaan dan penggantian kampas rem', '250000.00', '2026-10-14 19:32:57'),
  (3, 'Tune up', 'Tuning mesin, busi, dan filter udara', '350000.00', '2026-10-14 19:32:57'),
  (4, 'Ganti ban', 'Pemasangan ban baru sekaligus spooring ringan', '40000.00', '2026-10-14 19:32:57'),
  (5, 'Servis AC', 'Pemeriksaan dan isi freon sistem pendingin', '250000.00', '2026-10-14 19:32:57'),
  (6, 'Cuci dan poles', 'Pencucian bodi eksterior dan poles kilap', '120000.00', '2026-10-14 19:32:57');

--
-- Struktur tabel `vehicles`
--
DROP TABLE IF EXISTS `vehicles`;
CREATE TABLE `vehicles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` int(10) unsigned NOT NULL,
  `plate_number` varchar(20) NOT NULL,
  `brand` varchar(80) DEFAULT NULL,
  `model` varchar(80) DEFAULT NULL,
  `year` smallint(5) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_plate_number` (`plate_number`),
  KEY `fk_vehicles_customer` (`customer_id`),
  CONSTRAINT `fk_vehicles_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Data tabel `vehicles`
--
INSERT INTO `vehicles` (`id`, `customer_id`, `plate_number`, `brand`, `model`, `year`, `created_at`) VALUES
  (1, 1, 'B 1234 SYK', 'Toyota', 'Avanza', 2019, '2026-10-14 19:36:26'),
  (2, 1, 'B 5678 XYZ', 'Honda', 'Mobilio', 2021, '2026-10-14 19:36:26'),
  (3, 2, 'D 9012 ABC', 'Daihatsu', 'Xenia', 2018, '2026-10-14 19:36:26'),
  (4, 3, 'F 3456 DEF', 'Mitsubishi', 'Xpander', 2022, '2026-10-14 19:36:26'),
  (5, 4, 'B 7890 GHI', 'Suzuki', 'Ertiga', 2020, '2026-10-14 19:36:26');

SET FOREIGN_KEY_CHECKS = 1;
