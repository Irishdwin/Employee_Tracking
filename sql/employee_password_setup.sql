-- Preserve all existing passwords. NULL means historical origin is unknown.
ALTER TABLE users ADD COLUMN IF NOT EXISTS LocalPasswordConfigured TINYINT(1) NULL DEFAULT NULL AFTER Password;
