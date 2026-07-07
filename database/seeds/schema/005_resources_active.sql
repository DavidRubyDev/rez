-- Resource deletion is soft (see DeleteResourceUseCase / MysqlResourceRepository::delete()):
-- resource rows are never actually removed, only deactivated. This keeps
-- reservation_resources' ON DELETE CASCADE FK harmless — it would otherwise
-- orphan reservations (zero resource_ids) when a resource was hard-deleted.
-- IF NOT EXISTS makes this safe to re-run (MySQL 8.0.29+).

ALTER TABLE resources
    ADD COLUMN IF NOT EXISTS active TINYINT(1) NOT NULL DEFAULT 1;
