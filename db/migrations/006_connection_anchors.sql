-- Per-endpoint anchor side for connection lines (null = legacy auto-routing).
-- Existing connections keep null and continue to use the original auto-routing behavior.
ALTER TABLE port_connections
    ADD COLUMN IF NOT EXISTS anchor_a VARCHAR(6)
        CHECK (anchor_a IN ('top', 'bottom', 'left', 'right')),
    ADD COLUMN IF NOT EXISTS anchor_b VARCHAR(6)
        CHECK (anchor_b IN ('top', 'bottom', 'left', 'right'));
