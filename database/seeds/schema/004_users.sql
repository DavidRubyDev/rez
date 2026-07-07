-- Core users table and password reset tokens. Unlike 003_email_templates.sql,
-- this table gets exactly one seed row: a default Admin, because every
-- deployment needs at least one Admin to log into rez-admin and this table
-- (unlike database/seeds/data/) is never expected to be wiped. INSERT IGNORE
-- is safe to re-run and, since email is UNIQUE, also skips if the row was
-- since recreated under a different id — same convention as
-- reservation_settings/mailer_settings. Credentials are a placeholder — every
-- deployment must change this password before going live, same expectation as
-- mailer_settings' noreply@example.com default.

CREATE TABLE IF NOT EXISTS users (
    id                 CHAR(36)     NOT NULL PRIMARY KEY,
    name               VARCHAR(255) NOT NULL,
    email              VARCHAR(255) NOT NULL UNIQUE,
    password_hash      VARCHAR(255) NOT NULL,
    role               VARCHAR(20)  NOT NULL DEFAULT 'customer',
    newsletter_opt_in  TINYINT(1)   NOT NULL DEFAULT 0,
    stripe_customer_id VARCHAR(255) NULL,
    created_at         DATETIME     NOT NULL
);

-- Default password: "ChangeMe123!" — CHANGE THIS BEFORE GOING LIVE.
INSERT IGNORE INTO users
    (id, name, email, password_hash, role, newsletter_opt_in, stripe_customer_id, created_at)
VALUES
    ('00000000-0000-4000-8000-000000000001', 'Admin', 'admin@example.com', '$2y$12$IeX08JtozYjfnDlTz0QwBeMVT8s24glZVygHefdIquOe5IqptLyTu', 'admin', 0, NULL, UTC_TIMESTAMP());

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    email      VARCHAR(255) NOT NULL,
    token_hash CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    PRIMARY KEY (email)
);
