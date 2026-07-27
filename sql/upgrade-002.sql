-- =====================================================================
--  Prime Luxury Rides Toronto — Upgrade 002
--  Return trips & stops · Customer accounts · Drivers & SMS · Tracking
--
--  Safe to run once on an existing database:
--     mysql -u root -P 3307 primeluxuryrides < sql/upgrade-002.sql
--
--  Fresh installs get all of this from sql/schema.sql already.
-- =====================================================================
USE `primeluxuryrides`;

-- ---------------------------------------------------------------------
--  CUSTOMERS  (accounts, saved addresses, verified membership)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`           VARCHAR(190) NOT NULL,
  `password_hash`   VARCHAR(255) NOT NULL,
  `full_name`       VARCHAR(150) NOT NULL,
  `phone`           VARCHAR(40)  DEFAULT NULL,
  -- Membership is set by the operator, never self-selected by the customer.
  `membership_tier` ENUM('none','elite','vip') NOT NULL DEFAULT 'none',
  `membership_note` VARCHAR(255) DEFAULT NULL,
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at`   DATETIME DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_email` (`email`),
  KEY `idx_membership` (`membership_tier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_addresses` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `label`       VARCHAR(60)  NOT NULL DEFAULT 'Saved place',
  `address`     VARCHAR(255) NOT NULL,
  `sort_order`  SMALLINT NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customer` (`customer_id`, `sort_order`),
  CONSTRAINT `fk_addr_customer` FOREIGN KEY (`customer_id`)
    REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  DRIVERS  (chauffeur roster)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `drivers` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name`  VARCHAR(150) NOT NULL,
  `phone`      VARCHAR(40)  NOT NULL,
  `email`      VARCHAR(190) DEFAULT NULL,
  `licence_no` VARCHAR(60)  DEFAULT NULL,
  `notes`      TEXT,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  VEHICLES — number plate, shown to the customer on assignment
-- ---------------------------------------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles' AND COLUMN_NAME = 'plate');
SET @s := IF(@c = 0, 'ALTER TABLE `vehicles` ADD COLUMN `plate` VARCHAR(20) DEFAULT NULL AFTER `image`',
                     'SELECT "vehicles.plate exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
--  BOOKINGS — return trips, stops, customer link, driver link, tracking
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `plr_add_col`;
DELIMITER //
CREATE PROCEDURE `plr_add_col`(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col) = 0 THEN
    SET @q = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
    PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
  END IF;
END //
DELIMITER ;

CALL plr_add_col('bookings','is_return',
  '`is_return` TINYINT(1) NOT NULL DEFAULT 0 AFTER `service_type`');
CALL plr_add_col('bookings','return_at_trip',
  '`return_at_trip` DATETIME DEFAULT NULL AFTER `return_at`');
CALL plr_add_col('bookings','stops',
  '`stops` TEXT DEFAULT NULL AFTER `dropoff_address`');
CALL plr_add_col('bookings','customer_id',
  '`customer_id` INT UNSIGNED DEFAULT NULL AFTER `reference`');
CALL plr_add_col('bookings','driver_id',
  '`driver_id` INT UNSIGNED DEFAULT NULL AFTER `assigned_driver`');
CALL plr_add_col('bookings','track_token',
  '`track_token` VARCHAR(32) DEFAULT NULL AFTER `driver_id`');
CALL plr_add_col('bookings','notified_at',
  '`notified_at` DATETIME DEFAULT NULL AFTER `track_token`');

DROP PROCEDURE IF EXISTS `plr_add_col`;

-- Indexes / foreign keys (ignore duplicates on re-run)
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND INDEX_NAME = 'uq_track_token');
SET @s := IF(@i = 0, 'ALTER TABLE `bookings` ADD UNIQUE KEY `uq_track_token` (`track_token`)',
                     'SELECT "uq_track_token exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND INDEX_NAME = 'idx_customer');
SET @s := IF(@i = 0, 'ALTER TABLE `bookings` ADD KEY `idx_customer` (`customer_id`)',
                     'SELECT "idx_customer exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND INDEX_NAME = 'idx_driver');
SET @s := IF(@i = 0, 'ALTER TABLE `bookings` ADD KEY `idx_driver` (`driver_id`)',
                     'SELECT "idx_driver exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
--  NEW SETTINGS
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `settings` (`key_name`,`value`,`label`,`group_name`,`input_type`,`sort_order`) VALUES
 ('return_discount','10','Return-trip discount (%)','pricing','number',6),
 ('stop_fee','15','Fee per additional stop ($)','pricing','number',7),
 ('max_stops','3','Maximum additional stops','pricing','number',8);
