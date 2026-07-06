-- Core users table and password reset tokens. No seed rows — a real per-user
-- collection, not a singleton settings table, matching 003_email_templates.sql's
-- convention (CREATE TABLE IF NOT EXISTS only).

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

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    email      VARCHAR(255) NOT NULL,
    token_hash CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    PRIMARY KEY (email)
);
