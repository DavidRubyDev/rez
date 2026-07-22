-- Discrete, admin-created fixed-length class occurrences (Pilates, cycling, massage).
-- No ON DELETE CASCADE: resources are soft-deleted (invariant 13), so this never fires,
-- but be explicit rather than silent about that being the reason.
CREATE TABLE IF NOT EXISTS sessions (
    id CHAR(36) PRIMARY KEY,
    resource_id CHAR(36) NOT NULL,
    start_time DATETIME NOT NULL,
    duration_minutes INT NOT NULL,
    capacity INT NOT NULL,
    status VARCHAR(20) NOT NULL,
    FOREIGN KEY (resource_id) REFERENCES resources(id)
);
