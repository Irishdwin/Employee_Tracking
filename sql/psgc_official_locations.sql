CREATE TABLE IF NOT EXISTS psgc_locations (
  PSGCCode CHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  Name VARCHAR(200) NOT NULL,
  GeographicLevel VARCHAR(8) NOT NULL,
  ParentPSGCCode CHAR(10) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  RegionCode CHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  ProvinceCode CHAR(10) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  CityMunicipalityCode CHAR(10) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  CorrespondenceCode CHAR(9) DEFAULT NULL,
  CityClass VARCHAR(8) DEFAULT NULL,
  OldNames TEXT DEFAULT NULL,
  Status VARCHAR(150) DEFAULT NULL,
  SourceSHA256 CHAR(64) NOT NULL,
  SourceVersion VARCHAR(100) NOT NULL,
  INDEX idx_psgc_parent (ParentPSGCCode, GeographicLevel),
  INDEX idx_psgc_region (RegionCode, GeographicLevel, ProvinceCode),
  INDEX idx_psgc_province (ProvinceCode, GeographicLevel),
  INDEX idx_psgc_city (CityMunicipalityCode, GeographicLevel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
