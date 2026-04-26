BEGIN;

-- ── Sites ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sites (
    id          SERIAL       PRIMARY KEY,
    name        VARCHAR(128) NOT NULL,
    slug        VARCHAR(64)  NOT NULL UNIQUE,
    description TEXT         NOT NULL DEFAULT '',
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

INSERT INTO sites (name, slug, description)
VALUES ('Default Site', 'default-site', '')
ON CONFLICT (slug) DO NOTHING;

-- Temporarily nullable to allow back-fill before enforcing NOT NULL.
ALTER TABLE devices
    ADD COLUMN IF NOT EXISTS site_id INTEGER REFERENCES sites(id) ON DELETE RESTRICT;

UPDATE devices
SET site_id = (SELECT id FROM sites WHERE slug = 'default-site')
WHERE site_id IS NULL;

ALTER TABLE devices ALTER COLUMN site_id SET NOT NULL;

CREATE INDEX IF NOT EXISTS idx_devices_site_id ON devices (site_id);

COMMIT;
