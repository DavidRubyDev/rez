-- Baseline migration — converted 1:1 from the old database/seeds/schema/002_mailer_settings.sql.
-- Single-row settings table for outgoing mail branding (From address/name).
-- id is always 1 — enforced by convention, not a DB constraint, matching
-- reservation_settings' style.
--
-- The seeded defaults below are placeholders — every deployment must update
-- them via the mailer settings endpoint before going live.

CREATE TABLE IF NOT EXISTS mailer_settings (
    id           TINYINT UNSIGNED NOT NULL,
    from_address VARCHAR(255)     NOT NULL,
    from_name    VARCHAR(255)     NOT NULL,
    updated_at   DATETIME         NOT NULL,
    PRIMARY KEY (id)
);

INSERT IGNORE INTO mailer_settings
    (id, from_address, from_name, updated_at)
VALUES
    (1, 'noreply@example.com', 'Rez', UTC_TIMESTAMP());
