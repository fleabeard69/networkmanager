-- Add a single-column index on attempted_at so that the lazy-purge DELETE in
-- Auth::recordFailedAttempt() can use an index range scan instead of a sequential
-- table scan. The existing composite index (ip_address, attempted_at) serves the
-- isRateLimited() COUNT query; this index serves the age-based DELETE.
CREATE INDEX IF NOT EXISTS idx_login_attempts_time ON login_attempts (attempted_at);
