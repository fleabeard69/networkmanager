-- Add physical panel position to switch ports
ALTER TABLE switch_ports
    ADD COLUMN port_row INTEGER NOT NULL DEFAULT 1 CHECK (port_row BETWEEN 1 AND 10),
    ADD COLUMN port_col INTEGER NOT NULL DEFAULT 1 CHECK (port_col BETWEEN 1 AND 50);

CREATE INDEX idx_switch_ports_layout ON switch_ports(port_row, port_col);
