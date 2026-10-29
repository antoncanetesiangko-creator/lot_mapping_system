
CREATE DATABASE IF NOT EXISTS lot_mapping
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE lot_mapping;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS installment_payments;
DROP TABLE IF EXISTS installments;
DROP TABLE IF EXISTS ownership_history;
DROP TABLE IF EXISTS lot_shapes;
DROP TABLE IF EXISTS lots;
DROP TABLE IF EXISTS clients;
DROP TABLE IF EXISTS map_areas;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. USERS AND ACCESS ROLES
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff', 'viewer') NOT NULL DEFAULT 'viewer',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. MAP AREAS
CREATE TABLE map_areas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    area_name VARCHAR(180) NOT NULL,
    location VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    map_image_path VARCHAR(500) DEFAULT NULL,
    map_image_width INT UNSIGNED DEFAULT NULL,
    map_image_height INT UNSIGNED DEFAULT NULL,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_map_areas_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_map_areas_name (area_name),
    INDEX idx_map_areas_active (is_active)
) ENGINE=InnoDB;

-- 3. CLIENTS
-- Client details are retained even when ownership ends.
CREATE TABLE clients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) DEFAULT NULL,
    last_name VARCHAR(100) NOT NULL,
    suffix VARCHAR(20) DEFAULT NULL,
    contact_number VARCHAR(40) DEFAULT NULL,
    email VARCHAR(190) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_clients_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_clients_name (last_name, first_name),
    INDEX idx_clients_contact (contact_number)
) ENGINE=InnoDB;

-- 4. LOTS
-- Block/lot numbers must be unique only within the same map area.
CREATE TABLE lots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    map_area_id BIGINT UNSIGNED NOT NULL,
    block_number VARCHAR(50) NOT NULL,
    lot_number VARCHAR(50) NOT NULL,
    lot_area_sqm DECIMAL(12,2) DEFAULT NULL,
    price DECIMAL(14,2) DEFAULT NULL,
    status ENUM(
        'available',
        'spot_cash',
        'installment_current',
        'overdue',
        'forfeiture_review'
    ) NOT NULL DEFAULT 'available',
    notes TEXT DEFAULT NULL,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_area_block_lot
        UNIQUE (map_area_id, block_number, lot_number),

    CONSTRAINT fk_lots_map_area
        FOREIGN KEY (map_area_id) REFERENCES map_areas(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_lots_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL,

    INDEX idx_lots_status (status),
    INDEX idx_lots_block (map_area_id, block_number)
) ENGINE=InnoDB;

-- 5. LOT BOUNDARIES
-- Store polygon points as JSON.
-- Example points:
-- [{"x":0.1,"y":0.2},{"x":0.3,"y":0.2},{"x":0.3,"y":0.4}]
-- Coordinates may be normalized from 0 to 1.
CREATE TABLE lot_shapes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lot_id BIGINT UNSIGNED NOT NULL,
    polygon_points JSON NOT NULL,
    verification_status ENUM(
        'pending',
        'verified',
        'rejected'
    ) NOT NULL DEFAULT 'pending',
    verified_by BIGINT UNSIGNED DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_lot_shapes_lot
        FOREIGN KEY (lot_id) REFERENCES lots(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_lot_shapes_verified_by
        FOREIGN KEY (verified_by) REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_lot_shapes_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL,

    INDEX idx_lot_shapes_verification (verification_status)
) ENGINE=InnoDB;

-- 6. OWNERSHIP HISTORY
-- Never delete an old ownership record during a transfer or backout.
CREATE TABLE ownership_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lot_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    ownership_type ENUM('spot_cash', 'installment') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    ownership_status ENUM(
        'active',
        'completed',
        'backed_out',
        'transferred',
        'forfeiture_review'
    ) NOT NULL DEFAULT 'active',
    backout_date DATE DEFAULT NULL,
    backout_reason TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    recorded_by BIGINT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_ownership_lot
        FOREIGN KEY (lot_id) REFERENCES lots(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_ownership_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_ownership_recorded_by
        FOREIGN KEY (recorded_by) REFERENCES users(id)
        ON DELETE SET NULL,

    INDEX idx_ownership_lot_status (lot_id, ownership_status),
    INDEX idx_ownership_client (client_id),
    INDEX idx_ownership_dates (start_date, end_date)
) ENGINE=InnoDB;

-- 7. INSTALLMENT SCHEDULES
-- One schedule belongs to one ownership record.
CREATE TABLE installments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ownership_id BIGINT UNSIGNED NOT NULL,
    first_due_date DATE NOT NULL,
    term_months SMALLINT UNSIGNED NOT NULL,
    notes TEXT DEFAULT NULL,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_installments_ownership
        FOREIGN KEY (ownership_id) REFERENCES ownership_history(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_installments_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT chk_installment_term
        CHECK (term_months > 0),

    INDEX idx_installments_first_due (first_due_date)
) ENGINE=InnoDB;

-- 8. INSTALLMENT PAYMENT DATES
-- Each monthly installment has a separate due date and payment date.
-- No payment amount is required.
CREATE TABLE installment_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    installment_id BIGINT UNSIGNED NOT NULL,
    installment_number SMALLINT UNSIGNED NOT NULL,
    due_date DATE NOT NULL,
    payment_date DATE DEFAULT NULL,
    recorded_by BIGINT UNSIGNED DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_installment_number
        UNIQUE (installment_id, installment_number),

    CONSTRAINT fk_payments_installment
        FOREIGN KEY (installment_id) REFERENCES installments(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_payments_recorded_by
        FOREIGN KEY (recorded_by) REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT chk_installment_number
        CHECK (installment_number > 0),

    INDEX idx_payments_due_date (due_date),
    INDEX idx_payments_payment_date (payment_date)
) ENGINE=InnoDB;

-- 9. ACTIVITY LOG
CREATE TABLE activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) DEFAULT NULL,
    entity_id BIGINT UNSIGNED DEFAULT NULL,
    details JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_activity_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL,

    INDEX idx_activity_action (action),
    INDEX idx_activity_entity (entity_type, entity_id),
    INDEX idx_activity_created (created_at)
) ENGINE=InnoDB;

status ENUM(
    'available',
    'held',
    'spot_cash',
    'installment_current',
    'overdue',
    'forfeiture_review'
) NOT NULL DEFAULT 'available',