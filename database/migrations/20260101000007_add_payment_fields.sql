-- Adds pricing to resources/sessions and payment tracking to reservations.
-- Money is split into two nullable columns (amount + ISO currency code) rather than one —
-- NULL in either means no price/amount was ever set, mirroring Money's own invariant that
-- an amount only ever exists alongside a currency.
--
-- Guarded with information_schema checks (MySQL has no ADD COLUMN IF NOT EXISTS — that's a
-- MariaDB-only extension), unlike every migration before this one. Needed because
-- MysqlMigrationRepositoryTest's own tests DELETE FROM schema_migrations to exercise the
-- runner against an empty tracking table — within the same test process, every migration
-- that runs after it is replayed from scratch against an already-migrated database.
-- CREATE TABLE IF NOT EXISTS tolerates that for free; a bare ALTER TABLE ADD COLUMN does not.

SET @has_resources_price_amount = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'resources' AND COLUMN_NAME = 'price_amount'
);
SET @ddl = IF(@has_resources_price_amount = 0,
    'ALTER TABLE resources ADD COLUMN price_amount INT NULL, ADD COLUMN price_currency VARCHAR(3) NULL',
    'DO 0'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_sessions_price_amount = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sessions' AND COLUMN_NAME = 'price_amount'
);
SET @ddl = IF(@has_sessions_price_amount = 0,
    'ALTER TABLE sessions ADD COLUMN price_amount INT NULL, ADD COLUMN price_currency VARCHAR(3) NULL',
    'DO 0'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- payment_status defaults to 'unpaid' so every existing reservation row (predating this
-- feature) lands in the correct state without a backfill script.
SET @has_reservations_payment_status = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations' AND COLUMN_NAME = 'payment_status'
);
SET @ddl = IF(@has_reservations_payment_status = 0,
    "ALTER TABLE reservations
        ADD COLUMN payment_status      VARCHAR(20) NOT NULL DEFAULT 'unpaid',
        ADD COLUMN payment_method      VARCHAR(30) NULL,
        ADD COLUMN amount_due_amount   INT         NULL,
        ADD COLUMN amount_due_currency VARCHAR(3)  NULL,
        ADD COLUMN paid_at             DATETIME    NULL",
    'DO 0'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
