-- Rez reservation engine — MySQL schema
-- Run once against an empty database to create all tables.
-- Re-running is safe: all statements use CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS resources (
    id         CHAR(36)     NOT NULL,
    type       VARCHAR(100) NOT NULL,
    name       VARCHAR(255) NOT NULL,
    capacity   INT          NOT NULL,
    attributes JSON         NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS reservations (
    id          CHAR(36)     NOT NULL,
    status      VARCHAR(20)  NOT NULL,
    start_at    DATETIME     NOT NULL,
    end_at      DATETIME     NOT NULL,
    party_name  VARCHAR(255) NOT NULL,
    party_email VARCHAR(255) NOT NULL,
    party_size  INT          NOT NULL,
    party_phone VARCHAR(50)  NULL,
    created_at  DATETIME     NOT NULL,
    PRIMARY KEY (id)
);

-- Links a reservation to one or more resources.
CREATE TABLE IF NOT EXISTS reservation_resources (
    reservation_id CHAR(36) NOT NULL,
    resource_id    CHAR(36) NOT NULL,
    PRIMARY KEY (reservation_id, resource_id),
    FOREIGN KEY (reservation_id) REFERENCES reservations (id) ON DELETE CASCADE,
    FOREIGN KEY (resource_id)    REFERENCES resources (id)    ON DELETE CASCADE
);

-- Weekly recurring availability rules per resource.
-- day_of_week: 'monday' … 'sunday' (see DayOfWeekMapper)
-- open_time / close_time: 'HH:MM' (24-hour)
CREATE TABLE IF NOT EXISTS availability_rules (
    resource_id CHAR(36)    NOT NULL,
    day_of_week VARCHAR(10) NOT NULL,
    open_time   CHAR(5)     NOT NULL,
    close_time  CHAR(5)     NOT NULL,
    PRIMARY KEY (resource_id, day_of_week),
    FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE
);

-- One-off availability overrides for a specific date.
-- available: 1 = open, 0 = closed (overrides the weekly rule)
CREATE TABLE IF NOT EXISTS availability_overrides (
    resource_id CHAR(36)   NOT NULL,
    date        DATE       NOT NULL,
    available   TINYINT(1) NOT NULL,
    PRIMARY KEY (resource_id, date),
    FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE
);
