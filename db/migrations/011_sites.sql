-- ── Sites ─────────────────────────────────────────────────────────────────────
CREATE TABLE sites (
    id          SERIAL       PRIMARY KEY,
    name        VARCHAR(128) NOT NULL,
    slug        VARCHAR(64)  NOT NULL UNIQUE,
    description TEXT         NOT NULL DEFAULT '',
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

INSERT INTO sites (name, slug, description)
VALUES ('Default Site', 'default-site', '');

-- Temporarily nullable to allow back-fill before enforcing NOT NULL.
ALTER TABLE devices
    ADD COLUMN site_id INTEGER REFERENCES sites(id) ON DELETE RESTRICT;

UPDATE devices
SET site_id = (SELECT id FROM sites WHERE slug = 'default-site');

ALTER TABLE devices ALTER COLUMN site_id SET NOT NULL;

CREATE INDEX idx_devices_site_id ON devices (site_id);
