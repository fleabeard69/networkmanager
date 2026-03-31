-- Extend port_row range from 1–10 to 1–20 to support rear panel rows.
-- panel_rows (max 10) + panel_rear_rows (max 10) = max 20 rows total.
ALTER TABLE switch_ports
    DROP CONSTRAINT IF EXISTS switch_ports_port_row_check,
    ADD CONSTRAINT switch_ports_port_row_check CHECK (port_row BETWEEN 1 AND 20);
