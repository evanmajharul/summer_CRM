CREATE DATABASE IF NOT EXISTS summer_fair_crm
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE summer_fair_crm;

CREATE TABLE system_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('primary_admin','admin','viewer') NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE workers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_workers_created_by
        FOREIGN KEY (created_by) REFERENCES system_users(id)
);

CREATE TABLE assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    worker_id INT UNSIGNED NOT NULL,
    category ENUM('toyride','children_car','juice_slush') NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    hourly_rate DECIMAL(10,2) NOT NULL,
    hours_worked DECIMAL(10,2) NOT NULL,
    gross_earning DECIMAL(10,2) NOT NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NOT NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_assignments_worker
        FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE,
    CONSTRAINT fk_assignments_created_by
        FOREIGN KEY (created_by) REFERENCES system_users(id),
    CONSTRAINT fk_assignments_updated_by
        FOREIGN KEY (updated_by) REFERENCES system_users(id)
);

CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    worker_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(50) NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_worker
        FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_created_by
        FOREIGN KEY (created_by) REFERENCES system_users(id)
);

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NULL,
    action VARCHAR(30) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT UNSIGNED NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_admin
        FOREIGN KEY (admin_id) REFERENCES system_users(id) ON DELETE SET NULL
);

CREATE INDEX idx_assignments_worker ON assignments(worker_id);
CREATE INDEX idx_assignments_dates ON assignments(start_datetime, end_datetime);
CREATE INDEX idx_assignments_category ON assignments(category);
CREATE INDEX idx_payments_worker_date ON payments(worker_id, payment_date);
CREATE INDEX idx_audit_created_at ON audit_logs(created_at);
