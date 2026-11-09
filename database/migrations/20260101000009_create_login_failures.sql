-- Failed login attempts, for throttling (feature/login-throttling). One row per failure;
-- throttle_key is LoginThrottleKey's SHA-256 of email + client, so neither is stored. A
-- successful login deletes its key's rows. Rows for keys that never log in successfully are
-- inert once outside the window — like expired refresh tokens, a cleanup job can come later.
-- IF NOT EXISTS: MysqlMigrationRepositoryTest replays migrations against an already
-- migrated database (see docs/CONTEXT.md #101), so this must tolerate a second run.

CREATE TABLE IF NOT EXISTS login_failures (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    throttle_key CHAR(64) NOT NULL,
    failed_at    DATETIME NOT NULL,
    INDEX idx_login_failures_key_time (throttle_key, failed_at)
);
