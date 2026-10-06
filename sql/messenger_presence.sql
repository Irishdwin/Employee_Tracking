-- Server-observed session activity, Unix seconds; unrelated to account activation.
ALTER TABLE users ADD COLUMN IF NOT EXISTS MessengerLastActivity BIGINT UNSIGNED NULL DEFAULT NULL;
