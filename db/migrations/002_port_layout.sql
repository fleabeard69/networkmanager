-- Add physical panel position to switch_ports (safe to run on existing installs)
ALTER TABLE switch_ports
    ADD COLUMN IF NOT EXISTS port_row INTEGER NOT NULL DEFAULT 1 CHECK (port_row BETWEEN 1 AND 10),
    ADD COLUMN IF NOT EXISTS port_col INTEGER NOT NULL DEFAULT 1 CHECK (port_col BETWEEN 1 AND 50);

CREATE INDEX IF NOT EXISTS idx_switch_ports_layout ON switch_ports(port_row, port_col);
