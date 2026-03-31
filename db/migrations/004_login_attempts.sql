-- IP-based login rate limiting for brute-force protection.
-- New installs: applied automatically via docker-entrypoint-initdb.d
-- Existing installs: psql -U $DB_USER -d $DB_NAME -f 004_login_attempts.sql
CREATE TABLE IF NOT EXISTS login_attempts (
    id           BIGSERIAL    PRIMARY KEY,
    ip_address   TEXT         NOT NULL,
    attempted_at TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_login_attempts_ip_time
    ON login_attempts (ip_address, attempted_at);
