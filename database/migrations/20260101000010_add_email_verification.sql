-- Email verification (feature/email-verification): a self-registered user can't log in until
-- they've followed the link emailed to them. users.email_verified_at is NULL until then.
--
-- Every user that exists when this runs is marked verified (as of their created_at), so no
-- one is locked out by the deploy. The backfill shares the column's guard — run on its own
-- on a replay (see migration 7's header) it would verify everyone still waiting on a link.
SET @has_users_email_verified_at = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_verified_at'
);
SET @ddl = IF(@has_users_email_verified_at = 0,
    'ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL',
    'DO 0'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @backfill = IF(@has_users_email_verified_at = 0,
    'UPDATE users SET email_verified_at = created_at',
    'DO 0'
);
PREPARE stmt FROM @backfill;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- One outstanding link per user: sending a new one replaces the old. Only the SHA-256 of
-- the raw token is stored, same rule as password_reset_tokens and refresh_tokens.
-- issued_at drives the resend cooldown. ON DELETE CASCADE: a deleted user's link dies too.
CREATE TABLE IF NOT EXISTS email_verification_tokens (
    user_id    CHAR(36) NOT NULL PRIMARY KEY,
    token_hash CHAR(64) NOT NULL UNIQUE,
    issued_at  DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
