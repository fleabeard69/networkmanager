-- Generic key-value store for application metadata (e.g. last backup timestamp).
-- Safe to apply to an existing database: CREATE TABLE IF NOT EXISTS is idempotent.
--
-- For existing deployments apply manually:
--   docker exec -i netmanager_db psql -U $DB_USER -d $DB_NAME < db/migrations/007_app_settings.sql

CREATE TABLE IF NOT EXISTS app_settings (
    key   VARCHAR(64) PRIMARY KEY,
    value TEXT        NOT NULL
);
