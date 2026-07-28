-- =====================================================================
--  Prime Luxury Rides Toronto — Upgrade 003
--  Membership requests · site imagery settings
--
--  Safe to run once on an existing database:
--     mysql -u root -P 3307 primeluxuryrides < sql/upgrade-003.sql
-- =====================================================================
USE `primeluxuryrides`;

-- ---------------------------------------------------------------------
--  ENQUIRIES — allow membership applications alongside contact/quote
-- ---------------------------------------------------------------------
ALTER TABLE `enquiries`
  MODIFY COLUMN `kind`
  ENUM('contact','quote','rental','membership') NOT NULL DEFAULT 'contact';

-- Which tier the applicant asked for (NULL for ordinary enquiries).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'enquiries' AND COLUMN_NAME = 'requested_tier');
SET @s := IF(@c = 0,
  "ALTER TABLE `enquiries` ADD COLUMN `requested_tier` ENUM('elite','vip') DEFAULT NULL AFTER `kind`",
  'SELECT "enquiries.requested_tier exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Link an application back to the account that made it.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'enquiries' AND COLUMN_NAME = 'customer_id');
SET @s := IF(@c = 0,
  'ALTER TABLE `enquiries` ADD COLUMN `customer_id` INT UNSIGNED DEFAULT NULL AFTER `requested_tier`',
  'SELECT "enquiries.customer_id exists"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
--  SETTINGS — membership copy, pricing and site imagery
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `settings` (`key_name`,`value`,`label`,`group_name`,`input_type`,`sort_order`) VALUES
 ('membership_enabled','1','Show the membership page (1 = yes, 0 = no)','pricing','number',9),
 ('elite_price','0','Elite membership fee — annual ($, 0 = by application)','pricing','number',10),
 ('vip_price','0','VIP membership fee — annual ($, 0 = by application)','pricing','number',11),
 ('elite_blurb','For clients who travel with us most weeks. A standing discount on every fare, priority allocation at peak times, and a direct line to dispatch.','Elite tier description','content','textarea',10),
 ('vip_blurb','Our highest tier, by invitation or application. The deepest discount we offer, first call on the Maybach, guaranteed availability at any hour, and a named account manager.','VIP tier description','content','textarea',11),
 ('hero_image','','Home hero image filename (uploads/site/…)','content','text',20),
 ('about_image','','About page image filename (uploads/site/…)','content','text',21);
