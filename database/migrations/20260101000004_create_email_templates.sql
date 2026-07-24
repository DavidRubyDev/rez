-- Baseline migration — converted 1:1 from the old database/seeds/schema/003_email_templates.sql.
-- Admin-composed custom email templates (subject + HTML body), reusable for
-- one-off sends to an arbitrary list of recipient addresses. No seed rows —
-- this is a real collection, not a singleton settings table.

CREATE TABLE IF NOT EXISTS email_templates (
    id         CHAR(36)     NOT NULL,
    subject    VARCHAR(255) NOT NULL,
    html       MEDIUMTEXT   NOT NULL,
    created_at DATETIME     NOT NULL,
    PRIMARY KEY (id)
);
