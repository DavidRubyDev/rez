-- Admin-composed custom email templates (subject + HTML body), reusable for
-- one-off sends to an arbitrary list of recipient addresses.
-- Re-running is safe: CREATE TABLE IF NOT EXISTS. No seed rows — this is a
-- real collection, not a singleton settings table.

CREATE TABLE IF NOT EXISTS email_templates (
    id         CHAR(36)     NOT NULL,
    subject    VARCHAR(255) NOT NULL,
    html       MEDIUMTEXT   NOT NULL,
    created_at DATETIME     NOT NULL,
    PRIMARY KEY (id)
);
