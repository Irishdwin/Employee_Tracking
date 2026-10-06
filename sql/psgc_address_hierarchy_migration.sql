-- PSGC Region -> Province -> City/Municipality -> Barangay hierarchy (phpMyAdmin / onazul_hris_db)
-- ADDITIVE ONLY. Does not drop or rename any existing table/column.
--
-- The project already had address_province_lookup / address_city_lookup /
-- address_barangay_lookup (Province -> City -> Barangay, no Region level,
-- seeded with only 3 sample provinces). This migration:
--   1. Adds a new address_region_lookup table (the missing top level).
--   2. Adds RegionID (FK) + PSGCCode to address_province_lookup.
--   3. Adds PSGCCode to address_city_lookup and address_barangay_lookup,
--      so every level is traceable back to its official PSGC code.
-- It does NOT touch existing rows/IDs, so nothing already saved on an
-- applicant record breaks. Run seed_psgc_address_data.php afterwards to
-- replace the 3-province sample data with the full authoritative dataset.

CREATE TABLE IF NOT EXISTS `address_region_lookup` (
  `RegionID` INT(11) NOT NULL AUTO_INCREMENT,
  `PSGCCode` VARCHAR(20) DEFAULT NULL,
  `Name` VARCHAR(150) NOT NULL,
  PRIMARY KEY (`RegionID`),
  UNIQUE KEY `uq_region_psgc_code` (`PSGCCode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `address_province_lookup`
  ADD COLUMN IF NOT EXISTS `RegionID` INT(11) DEFAULT NULL AFTER `ProvinceID`,
  ADD COLUMN IF NOT EXISTS `PSGCCode` VARCHAR(20) DEFAULT NULL,
  ADD UNIQUE KEY IF NOT EXISTS `uq_province_psgc_code` (`PSGCCode`);

ALTER TABLE `address_city_lookup`
  ADD COLUMN IF NOT EXISTS `PSGCCode` VARCHAR(20) DEFAULT NULL,
  ADD UNIQUE KEY IF NOT EXISTS `uq_city_psgc_code` (`PSGCCode`);

ALTER TABLE `address_barangay_lookup`
  ADD COLUMN IF NOT EXISTS `PSGCCode` VARCHAR(20) DEFAULT NULL,
  ADD UNIQUE KEY IF NOT EXISTS `uq_barangay_psgc_code` (`PSGCCode`);

-- Helpful indexes for the cascading dropdown queries (id lookups are already
-- fast on the primary keys; these speed up the "children of X" queries).
ALTER TABLE `address_province_lookup`
  ADD INDEX IF NOT EXISTS `idx_province_region` (`RegionID`);
ALTER TABLE `address_city_lookup`
  ADD INDEX IF NOT EXISTS `idx_city_province` (`ProvinceID`);
ALTER TABLE `address_barangay_lookup`
  ADD INDEX IF NOT EXISTS `idx_barangay_city` (`CityID`);
