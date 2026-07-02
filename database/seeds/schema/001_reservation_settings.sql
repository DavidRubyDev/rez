-- Single-row settings table for the reservation lifecycle (autoConfirm + which
-- lifecycle emails fire automatically). id is always 1 — enforced by convention,
-- not a DB constraint, matching this repo's existing seed style.
-- Re-running is safe: CREATE TABLE IF NOT EXISTS + INSERT IGNORE.

CREATE TABLE IF NOT EXISTS reservation_settings (
    id                               TINYINT UNSIGNED NOT NULL,
    auto_confirm                     TINYINT(1)       NOT NULL DEFAULT 0,
    auto_send_reservation_created    TINYINT(1)       NOT NULL DEFAULT 1,
    auto_send_reservation_confirmed  TINYINT(1)       NOT NULL DEFAULT 1,
    auto_send_reservation_cancelled  TINYINT(1)       NOT NULL DEFAULT 1,
    updated_at                       DATETIME         NOT NULL,
    PRIMARY KEY (id)
);

INSERT IGNORE INTO reservation_settings
    (id, auto_confirm, auto_send_reservation_created, auto_send_reservation_confirmed, auto_send_reservation_cancelled, updated_at)
VALUES
    (1, 0, 1, 1, 1, UTC_TIMESTAMP());
