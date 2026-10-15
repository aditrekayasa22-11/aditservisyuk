-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 15, 2026 at 08:45 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `servisyuk`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password_hash`, `name`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', '2026-10-15 06:43:45');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `sku` varchar(40) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `unit` varchar(20) NOT NULL DEFAULT 'pcs',
  `purchase_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `service_record_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `method` enum('Tunai','Transfer','Kartu','QRIS') NOT NULL DEFAULT 'Tunai',
  `status` enum('Belum Dibayar','Menunggu Verifikasi','Lunas','Ditolak') NOT NULL DEFAULT 'Belum Dibayar',
  `paid_at` datetime DEFAULT NULL,
  `proof_path` varchar(255) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `verified_by` int(10) UNSIGNED DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_pins`
--

CREATE TABLE `service_pins` (
  `id` int(10) UNSIGNED NOT NULL,
  `vehicle_id` int(10) UNSIGNED NOT NULL,
  `pin_hash` varchar(255) NOT NULL,
  `expires_at` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_progress`
--

CREATE TABLE `service_progress` (
  `id` int(10) UNSIGNED NOT NULL,
  `service_record_id` int(10) UNSIGNED NOT NULL,
  `note_type` enum('Pemeriksaan','Perbaikan','Kendala','Lainnya') NOT NULL DEFAULT 'Pemeriksaan',
  `note` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_records`
--

CREATE TABLE `service_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `vehicle_id` int(10) UNSIGNED NOT NULL,
  `service_type_id` int(10) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Menunggu Pemeriksaan','Sedang Diperbaiki','Menunggu Suku Cadang','Selesai','Siap Diambil','Dibatalkan') NOT NULL DEFAULT 'Menunggu Pemeriksaan',
  `service_date` date NOT NULL,
  `estimated_done` datetime DEFAULT NULL,
  `cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_record_types`
--

CREATE TABLE `service_record_types` (
  `service_record_id` int(10) UNSIGNED NOT NULL,
  `service_type_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_types`
--

CREATE TABLE `service_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `base_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_types`
--

INSERT INTO `service_types` (`id`, `name`, `description`, `base_price`, `created_at`) VALUES
(1, 'Ganti oli', 'Ganti oli mesin dan filter oli', 150000.00, '2026-10-15 06:43:45'),
(2, 'Servis rem', 'Pemeriksaan dan penggantian kampas rem', 250000.00, '2026-10-15 06:43:45'),
(3, 'Tune up', 'Tuning mesin, busi, dan filter udara', 350000.00, '2026-10-15 06:43:45'),
(4, 'Ganti ban', 'Pemasangan ban baru sekaligus spooring ringan', 40000.00, '2026-10-15 06:43:45'),
(5, 'Servis AC', 'Pemeriksaan dan isi freon sistem pendingin', 250000.00, '2026-10-15 06:43:45'),
(6, 'Cuci dan poles', 'Pencucian bodi eksterior dan poles kilap', 120000.00, '2026-10-15 06:43:45');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(10) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `plate_number` varchar(20) NOT NULL,
  `brand` varchar(80) DEFAULT NULL,
  `model` varchar(80) DEFAULT NULL,
  `year` smallint(5) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_inventory_sku` (`sku`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_payment_service_record` (`service_record_id`);

--
-- Indexes for table `service_pins`
--
ALTER TABLE `service_pins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_service_pins_vehicle` (`vehicle_id`);

--
-- Indexes for table `service_progress`
--
ALTER TABLE `service_progress`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service_progress_record` (`service_record_id`);

--
-- Indexes for table `service_records`
--
ALTER TABLE `service_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service_records_vehicle` (`vehicle_id`),
  ADD KEY `idx_service_records_status` (`status`),
  ADD KEY `fk_service_records_type` (`service_type_id`);

--
-- Indexes for table `service_record_types`
--
ALTER TABLE `service_record_types`
  ADD PRIMARY KEY (`service_record_id`,`service_type_id`),
  ADD KEY `idx_service_record_types_type` (`service_type_id`);

--
-- Indexes for table `service_types`
--
ALTER TABLE `service_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_service_type_name` (`name`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_plate_number` (`plate_number`),
  ADD KEY `fk_vehicles_customer` (`customer_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_pins`
--
ALTER TABLE `service_pins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_progress`
--
ALTER TABLE `service_progress`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_records`
--
ALTER TABLE `service_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_types`
--
ALTER TABLE `service_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_service_record` FOREIGN KEY (`service_record_id`) REFERENCES `service_records` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_pins`
--
ALTER TABLE `service_pins`
  ADD CONSTRAINT `fk_service_pins_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_progress`
--
ALTER TABLE `service_progress`
  ADD CONSTRAINT `fk_service_progress_record` FOREIGN KEY (`service_record_id`) REFERENCES `service_records` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_records`
--
ALTER TABLE `service_records`
  ADD CONSTRAINT `fk_service_records_type` FOREIGN KEY (`service_type_id`) REFERENCES `service_types` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_service_records_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_record_types`
--
ALTER TABLE `service_record_types`
  ADD CONSTRAINT `fk_service_record_types_record` FOREIGN KEY (`service_record_id`) REFERENCES `service_records` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_service_record_types_type` FOREIGN KEY (`service_type_id`) REFERENCES `service_types` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `fk_vehicles_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
