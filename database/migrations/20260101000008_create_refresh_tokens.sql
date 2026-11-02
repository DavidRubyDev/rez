-- Rotating refresh tokens (feature/refresh-tokens). One row per issued token; every
-- refresh marks the presented row used and inserts its successor under the same
-- family_id, so a family is one login on one device and revoking it ends that login.
-- Only the SHA-256 hash of the raw token is stored, same rule as password_reset_tokens.
-- ON DELETE CASCADE: deleting a user must end every session they had.
-- IF NOT EXISTS: MysqlMigrationRepositoryTest replays migrations against an already
-- migrated database (see docs/CONTEXT.md #101), so this must tolerate a second run.

CREATE TABLE IF NOT EXISTS refresh_tokens (
    id         CHAR(36) NOT NULL PRIMARY KEY,
    family_id  CHAR(36) NOT NULL,
    user_id    CHAR(36) NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    used_at    DATETIME NULL,
    revoked_at DATETIME NULL,
    INDEX idx_refresh_tokens_family (family_id),
    INDEX idx_refresh_tokens_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
