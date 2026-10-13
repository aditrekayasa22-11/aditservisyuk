CREATE DATABASE IF NOT EXISTS autovance
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE autovance;

CREATE TABLE IF NOT EXISTS admins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(40) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(190) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customers_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vehicles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    plate_number VARCHAR(15) NOT NULL UNIQUE,
    brand VARCHAR(60) NULL,
    model VARCHAR(60) NULL,
    year SMALLINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vehicles_customer (customer_id),
    CONSTRAINT fk_vehicles_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS service_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    base_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS service_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_id BIGINT UNSIGNED NOT NULL,
    service_type_id BIGINT UNSIGNED NULL,
    description TEXT NULL,
    status ENUM('Menunggu','Dikerjakan','Selesai','Dibatalkan') NOT NULL DEFAULT 'Menunggu',
    service_date DATE NOT NULL,
    cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_services_vehicle_date (vehicle_id, service_date),
    INDEX idx_services_status (status),
    CONSTRAINT fk_services_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    CONSTRAINT fk_services_type FOREIGN KEY (service_type_id) REFERENCES service_types(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    sku VARCHAR(60) NULL UNIQUE,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    unit VARCHAR(30) NOT NULL DEFAULT 'pcs',
    purchase_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_record_id BIGINT UNSIGNED NOT NULL UNIQUE,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    method ENUM('Tunai','Transfer','Kartu','QRIS') NOT NULL DEFAULT 'Tunai',
    status ENUM('Belum Dibayar','Lunas') NOT NULL DEFAULT 'Belum Dibayar',
    paid_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_service FOREIGN KEY (service_record_id) REFERENCES service_records(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO service_types (name, description, base_price) VALUES
('Servis berkala', 'Pemeriksaan dan perawatan berkala kendaraan.', 250000),
('Ganti oli', 'Penggantian oli mesin kendaraan.', 150000),
('Pemeriksaan rem', 'Pemeriksaan sistem pengereman.', 100000);
