-- =====================================================================
--  Prime Luxury Rides Toronto — Database Schema
--  MySQL 5.7+ / MariaDB 10.3+  |  utf8mb4
--  Import:  mysql -u root < sql/schema.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `primeluxuryrides`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `primeluxuryrides`;

-- ---------------------------------------------------------------------
--  VEHICLES  (fleet + per-vehicle pricing parameters)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `vehicles`;
CREATE TABLE `vehicles` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`            VARCHAR(80)  NOT NULL,
  `name`            VARCHAR(120) NOT NULL,
  `class_label`     VARCHAR(60)  NOT NULL DEFAULT 'Sedan',   -- Sedan / SUV / Ultra-Luxury
  `tagline`         VARCHAR(200) DEFAULT NULL,
  `description`     TEXT,
  `passengers`      TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `luggage`         TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `image`           VARCHAR(255) DEFAULT NULL,               -- uploads/vehicles/xxx.jpg
  `plate`           VARCHAR(20)  DEFAULT NULL,               -- shown to the customer on assignment
  `features`        TEXT,                                    -- newline-separated

  -- Dynamic pricing (used when distance < flat_rate_threshold_km)
  `base_fare`       DECIMAL(10,2) NOT NULL DEFAULT 20.00,
  `rate_per_km`     DECIMAL(10,2) NOT NULL DEFAULT 2.25,
  `rate_per_min`    DECIMAL(10,2) NOT NULL DEFAULT 0.95,

  -- Hourly chauffeur
  `hourly_rate`     DECIMAL(10,2) NOT NULL DEFAULT 100.00,
  `min_hours`       TINYINT UNSIGNED NOT NULL DEFAULT 3,

  -- Rentals (self-drive / daily hire)
  `rental_daily`    DECIMAL(10,2) DEFAULT NULL,
  `rental_weekly`   DECIMAL(10,2) DEFAULT NULL,
  `rental_available` TINYINT(1) NOT NULL DEFAULT 0,

  -- Service eligibility  (Maybach = hourly + city-to-city only)
  `allow_airport`     TINYINT(1) NOT NULL DEFAULT 1,
  `allow_city`        TINYINT(1) NOT NULL DEFAULT 1,
  `allow_city_to_city` TINYINT(1) NOT NULL DEFAULT 1,
  `allow_hourly`      TINYINT(1) NOT NULL DEFAULT 1,

  `sort_order`      SMALLINT NOT NULL DEFAULT 0,
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vehicle_slug` (`slug`),
  KEY `idx_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  FLAT RATES  (city-to-city, one-way, >= 40 km — HST not included)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `flat_rates`;
CREATE TABLE `flat_rates` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vehicle_id`   INT UNSIGNED NOT NULL,
  `city`         VARCHAR(120) NOT NULL,
  `city_key`     VARCHAR(120) NOT NULL,     -- normalised for matching
  `distance_km`  SMALLINT UNSIGNED NOT NULL,
  `price`        DECIMAL(10,2) DEFAULT NULL, -- NULL = use dynamic pricing
  `sort_order`   SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vehicle_city` (`vehicle_id`, `city_key`),
  KEY `idx_city_key` (`city_key`),
  CONSTRAINT `fk_flat_vehicle` FOREIGN KEY (`vehicle_id`)
    REFERENCES `vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  BOOKINGS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`       VARCHAR(24) NOT NULL,          -- PLR-2026-0001
  `customer_id`     INT UNSIGNED DEFAULT NULL,     -- set when booked while signed in

  `booking_type`    ENUM('ride','rental') NOT NULL DEFAULT 'ride',
  `service_type`    ENUM('airport','city','city_to_city','hourly','rental') NOT NULL,
  `is_return`       TINYINT(1) NOT NULL DEFAULT 0,

  -- Customer
  `full_name`       VARCHAR(150) NOT NULL,
  `email`           VARCHAR(190) NOT NULL,
  `phone`           VARCHAR(40)  NOT NULL,

  -- Trip
  `pickup_address`  VARCHAR(255) NOT NULL,
  `dropoff_address` VARCHAR(255) DEFAULT NULL,
  `stops`           TEXT DEFAULT NULL,             -- JSON array of intermediate addresses
  `pickup_at`       DATETIME NOT NULL,
  `return_at`       DATETIME DEFAULT NULL,         -- rentals
  `return_at_trip`  DATETIME DEFAULT NULL,         -- return leg of a return trip
  `hours`           TINYINT UNSIGNED DEFAULT NULL, -- hourly bookings
  `flight_number`   VARCHAR(40) DEFAULT NULL,

  `distance_km`     DECIMAL(10,2) DEFAULT NULL,
  `duration_min`    DECIMAL(10,2) DEFAULT NULL,

  `vehicle_id`      INT UNSIGNED DEFAULT NULL,
  `vehicle_name`    VARCHAR(120) DEFAULT NULL,     -- snapshot at booking time
  `passengers`      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `luggage`         TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `notes`           TEXT,

  -- Money (all CAD)
  `pricing_method`  ENUM('flat','dynamic','hourly','rental') NOT NULL DEFAULT 'dynamic',
  `subtotal`        DECIMAL(10,2) NOT NULL DEFAULT 0,
  `membership_tier` ENUM('none','elite','vip') NOT NULL DEFAULT 'none',
  `discount`        DECIMAL(10,2) NOT NULL DEFAULT 0,
  `hst`             DECIMAL(10,2) NOT NULL DEFAULT 0,
  `total`           DECIMAL(10,2) NOT NULL DEFAULT 0,
  `price_breakdown` TEXT,                           -- JSON snapshot

  -- Workflow
  `status`          ENUM('pending','confirmed','assigned','completed','cancelled')
                      NOT NULL DEFAULT 'pending',
  `payment_status`  ENUM('unpaid','deposit_paid','paid','refunded')
                      NOT NULL DEFAULT 'unpaid',
  `stripe_session_id` VARCHAR(255) DEFAULT NULL,
  `stripe_payment_intent` VARCHAR(255) DEFAULT NULL,
  `assigned_driver` VARCHAR(150) DEFAULT NULL,     -- name snapshot, survives roster deletion
  `driver_id`       INT UNSIGNED DEFAULT NULL,
  `track_token`     VARCHAR(32) DEFAULT NULL,      -- powers the public /t/<token> page
  `notified_at`     DATETIME DEFAULT NULL,
  `admin_notes`     TEXT,

  `ip_address`      VARCHAR(45) DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reference` (`reference`),
  KEY `idx_status_created` (`status`, `created_at`),
  KEY `idx_pickup_at` (`pickup_at`),
  KEY `idx_email` (`email`),
  UNIQUE KEY `uq_track_token` (`track_token`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_driver` (`driver_id`),
  CONSTRAINT `fk_booking_vehicle` FOREIGN KEY (`vehicle_id`)
    REFERENCES `vehicles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  CONTACT / QUOTE ENQUIRIES
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `enquiries`;
CREATE TABLE `enquiries` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kind`       ENUM('contact','quote','rental') NOT NULL DEFAULT 'contact',
  `full_name`  VARCHAR(150) NOT NULL,
  `email`      VARCHAR(190) NOT NULL,
  `phone`      VARCHAR(40)  DEFAULT NULL,
  `subject`    VARCHAR(200) DEFAULT NULL,
  `message`    TEXT,
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_read_created` (`is_read`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  CUSTOMERS  (accounts, saved addresses, operator-set membership)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `customer_addresses`;
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`           VARCHAR(190) NOT NULL,
  `password_hash`   VARCHAR(255) NOT NULL,
  `full_name`       VARCHAR(150) NOT NULL,
  `phone`           VARCHAR(40)  DEFAULT NULL,
  -- Set by the operator in the admin panel, never by the customer.
  `membership_tier` ENUM('none','elite','vip') NOT NULL DEFAULT 'none',
  `membership_note` VARCHAR(255) DEFAULT NULL,
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at`   DATETIME DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_email` (`email`),
  KEY `idx_membership` (`membership_tier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customer_addresses` (
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
DROP TABLE IF EXISTS `drivers`;
CREATE TABLE `drivers` (
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
--  ADMIN USERS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE `admin_users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`         VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name`     VARCHAR(150) NOT NULL DEFAULT 'Administrator',
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  SITE SETTINGS  (editable text / contact info / pricing globals)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `key_name`   VARCHAR(100) NOT NULL,
  `value`      TEXT,
  `label`      VARCHAR(200) DEFAULT NULL,
  `group_name` VARCHAR(60)  NOT NULL DEFAULT 'general',
  `input_type` ENUM('text','textarea','number') NOT NULL DEFAULT 'text',
  `sort_order` SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  SEED DATA
-- =====================================================================

-- --- Vehicles ---------------------------------------------------------
INSERT INTO `vehicles`
 (`slug`,`name`,`class_label`,`tagline`,`description`,`passengers`,`luggage`,`features`,
  `base_fare`,`rate_per_km`,`rate_per_min`,`hourly_rate`,`min_hours`,
  `rental_daily`,`rental_weekly`,`rental_available`,
  `allow_airport`,`allow_city`,`allow_city_to_city`,`allow_hourly`,`sort_order`)
VALUES
 ('mercedes-s580',
  'Mercedes-Benz S580',
  'Executive Sedan',
  'The definitive executive sedan.',
  'The S-Class is the benchmark for executive travel. Whisper-quiet, impeccably finished and effortlessly composed — ideal for airport transfers, corporate travel and discreet city journeys.',
  3, 3,
  "Nappa leather seating\nRear climate control\nComplimentary Wi-Fi\nBottled water & reading material\nPanoramic roof\nUSB-C charging",
  20.00, 2.25, 0.95, 100.00, 3,
  399.00, 2390.00, 1,
  1,1,1,1, 1),

 ('cadillac-escalade-esv',
  'Cadillac Escalade ESV',
  'Luxury SUV',
  'Commanding presence, generous space.',
  'Our Escalade ESV pairs a commanding road presence with genuine space for six passengers and their luggage. The default choice for family airport runs, group corporate travel and event transport.',
  6, 6,
  "Seating for 6 passengers\nCaptain's chairs\n38-inch curved OLED display\nComplimentary Wi-Fi\nBottled water & reading material\nTri-zone climate control",
  25.00, 2.75, 1.10, 130.00, 3,
  499.00, 2990.00, 1,
  1,1,1,1, 2),

 ('chevrolet-suburban',
  'Chevrolet Suburban',
  'Luxury SUV',
  'Dependable space for six.',
  'The Suburban delivers the same six-passenger capability as our Escalade with a slightly more understated profile — a discreet, dependable workhorse for airport transfers and group travel.',
  6, 7,
  "Seating for 6 passengers\nGenerous luggage capacity\nLeather seating\nComplimentary Wi-Fi\nBottled water & reading material\nRear climate control",
  25.00, 2.75, 1.10, 120.00, 3,
  429.00, 2590.00, 1,
  1,1,1,1, 3),

 ('mercedes-maybach-gls600',
  'Mercedes-Maybach GLS 600',
  'Ultra-Luxury',
  'Our flagship. Hourly & long-distance only.',
  'The Maybach GLS 600 is the pinnacle of our fleet — hand-finished, rear-lounge seating with executive recline. Reserved exclusively for hourly chauffeur hire (4-hour minimum) and long-distance city-to-city transfers.',
  2, 2,
  "Rear executive lounge seating\nHeated, reclining rear seats\nRefrigerated centre console\nBurmester 3D surround sound\nComplimentary Wi-Fi\nBottled water & reading material",
  35.00, 3.50, 1.40, 150.00, 4,
  NULL, NULL, 0,
  0,0,1,1, 4);

-- --- Flat rates: Mercedes-Benz S580 (Sedan) ---------------------------
INSERT INTO `flat_rates` (`vehicle_id`,`city`,`city_key`,`distance_km`,`price`,`sort_order`)
SELECT id, v.city, v.ckey, v.km, v.price, v.so FROM `vehicles`,
 (SELECT 'Hamilton' city,'hamilton' ckey,70 km,150.00 price,1 so UNION ALL
  SELECT 'Mississauga','mississauga',25,NULL,2 UNION ALL
  SELECT 'Brampton','brampton',40,NULL,3 UNION ALL
  SELECT 'Oshawa','oshawa',60,140.00,4 UNION ALL
  SELECT 'Barrie','barrie',90,180.00,5 UNION ALL
  SELECT 'Kitchener / Waterloo','kitchener',110,200.00,6 UNION ALL
  SELECT 'Niagara Falls','niagara falls',130,250.00,7 UNION ALL
  SELECT 'London, ON','london',190,350.00,8 UNION ALL
  SELECT 'Kingston','kingston',260,450.00,9 UNION ALL
  SELECT 'Ottawa','ottawa',450,750.00,10) v
WHERE `slug` = 'mercedes-s580';

-- --- Flat rates: Cadillac Escalade ESV (SUV) --------------------------
INSERT INTO `flat_rates` (`vehicle_id`,`city`,`city_key`,`distance_km`,`price`,`sort_order`)
SELECT id, v.city, v.ckey, v.km, v.price, v.so FROM `vehicles`,
 (SELECT 'Hamilton' city,'hamilton' ckey,70 km,175.00 price,1 so UNION ALL
  SELECT 'Mississauga','mississauga',25,NULL,2 UNION ALL
  SELECT 'Brampton','brampton',40,NULL,3 UNION ALL
  SELECT 'Oshawa','oshawa',60,165.00,4 UNION ALL
  SELECT 'Barrie','barrie',90,220.00,5 UNION ALL
  SELECT 'Kitchener / Waterloo','kitchener',110,240.00,6 UNION ALL
  SELECT 'Niagara Falls','niagara falls',130,300.00,7 UNION ALL
  SELECT 'London, ON','london',190,400.00,8 UNION ALL
  SELECT 'Kingston','kingston',260,500.00,9 UNION ALL
  SELECT 'Ottawa','ottawa',450,800.00,10) v
WHERE `slug` = 'cadillac-escalade-esv';

-- --- Flat rates: Chevrolet Suburban (same SUV tier as Escalade) -------
INSERT INTO `flat_rates` (`vehicle_id`,`city`,`city_key`,`distance_km`,`price`,`sort_order`)
SELECT id, v.city, v.ckey, v.km, v.price, v.so FROM `vehicles`,
 (SELECT 'Hamilton' city,'hamilton' ckey,70 km,175.00 price,1 so UNION ALL
  SELECT 'Mississauga','mississauga',25,NULL,2 UNION ALL
  SELECT 'Brampton','brampton',40,NULL,3 UNION ALL
  SELECT 'Oshawa','oshawa',60,165.00,4 UNION ALL
  SELECT 'Barrie','barrie',90,220.00,5 UNION ALL
  SELECT 'Kitchener / Waterloo','kitchener',110,240.00,6 UNION ALL
  SELECT 'Niagara Falls','niagara falls',130,300.00,7 UNION ALL
  SELECT 'London, ON','london',190,400.00,8 UNION ALL
  SELECT 'Kingston','kingston',260,500.00,9 UNION ALL
  SELECT 'Ottawa','ottawa',450,800.00,10) v
WHERE `slug` = 'chevrolet-suburban';

-- --- Flat rates: Mercedes-Maybach GLS600 (Ultra-Luxury) ---------------
INSERT INTO `flat_rates` (`vehicle_id`,`city`,`city_key`,`distance_km`,`price`,`sort_order`)
SELECT id, v.city, v.ckey, v.km, v.price, v.so FROM `vehicles`,
 (SELECT 'Hamilton' city,'hamilton' ckey,70 km,220.00 price,1 so UNION ALL
  SELECT 'Mississauga','mississauga',25,NULL,2 UNION ALL
  SELECT 'Brampton','brampton',40,NULL,3 UNION ALL
  SELECT 'Oshawa','oshawa',60,200.00,4 UNION ALL
  SELECT 'Barrie','barrie',90,300.00,5 UNION ALL
  SELECT 'Kitchener / Waterloo','kitchener',110,320.00,6 UNION ALL
  SELECT 'Niagara Falls','niagara falls',130,400.00,7 UNION ALL
  SELECT 'London, ON','london',190,520.00,8 UNION ALL
  SELECT 'Kingston','kingston',260,650.00,9 UNION ALL
  SELECT 'Ottawa','ottawa',450,1000.00,10) v
WHERE `slug` = 'mercedes-maybach-gls600';

-- --- Default admin user ----------------------------------------------
--  Email:    info@primeluxuryridestoronto.ca
--  Password: PrimeAdmin2026!   <-- CHANGE THIS IMMEDIATELY AFTER LOGIN
INSERT INTO `admin_users` (`email`,`password_hash`,`full_name`) VALUES
 ('info@primeluxuryridestoronto.ca',
  '$2y$10$1lezRPnzx1nnKgUO8bqMGORGFyXO.ObYn9yqSkoFAncgR132Vteqe',
  'Prime Luxury Rides Admin');

-- --- Site settings ----------------------------------------------------
INSERT INTO `settings` (`key_name`,`value`,`label`,`group_name`,`input_type`,`sort_order`) VALUES
 ('company_name','Prime Luxury Rides Toronto','Company name','contact','text',1),
 ('phone','+1 (416) 000-0000','Phone number','contact','text',2),
 ('whatsapp','14160000000','WhatsApp number (digits only, incl. country code)','contact','text',3),
 ('email','info@primeluxuryridestoronto.ca','Public email','contact','text',4),
 ('hours','24 hours a day, 7 days a week','Operating hours','contact','text',5),
 ('service_area','Toronto • Mississauga • Brampton • Vaughan • Markham • Scarborough','Service area line','contact','text',6),
 ('airports','YYZ (Pearson) • YTZ (Billy Bishop) • YHM (Hamilton)','Airports served','contact','text',7),
 ('facebook','','Facebook URL','social','text',1),
 ('instagram','','Instagram URL','social','text',2),
 ('x_twitter','','X / Twitter URL','social','text',3),
 ('linkedin','','LinkedIn URL','social','text',4),
 ('hst_rate','13','HST rate (%)','pricing','number',1),
 ('flat_rate_threshold_km','40','Flat-rate threshold (km)','pricing','number',2),
 ('elite_discount','30','Elite member discount (%)','pricing','number',3),
 ('vip_discount','40','VIP member discount (%)','pricing','number',4),
 ('deposit_percent','100','Charge at booking (% of total)','pricing','number',5),
 ('return_discount','10','Return-trip discount (%)','pricing','number',6),
 ('stop_fee','15','Fee per additional stop ($)','pricing','number',7),
 ('max_stops','3','Maximum additional stops','pricing','number',8),
 ('hero_title','Luxury Chauffeur Services in Toronto','Home hero headline','content','text',1),
 ('hero_subtitle','Premium airport transfers, corporate travel, events & more.','Home hero sub-headline','content','textarea',2),
 ('about_mission','To redefine private ground transportation in the Greater Toronto Area by pairing an impeccably maintained luxury fleet with chauffeurs who treat every journey as a matter of personal pride.','Mission statement','content','textarea',3),
 ('meta_description','Prime Luxury Rides Toronto — premium chauffeur service for airport transfers, corporate travel and special events across Toronto & the GTA. Licensed, insured, available 24/7.','Default meta description','seo','textarea',1);
