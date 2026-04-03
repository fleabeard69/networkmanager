-- Enforce that no two ports on the same device share the same grid cell.
--
-- DEFERRABLE INITIALLY IMMEDIATE means the constraint fires on every normal
-- statement (immediate by default) but can be deferred to transaction-end via
--   SET CONSTRAINTS uq_switch_ports_position DEFERRED
-- The swap endpoint uses this to update both ports within one transaction
-- without triggering a transient uniqueness violation mid-way through.
--
-- NULL device_id (unassigned ports) are exempt: PostgreSQL's standard unique
-- index semantics treat NULL as distinct from every other value, so two
-- unassigned ports at the same (port_row, port_col) do not conflict.
--
-- If this migration fails, your database contains two or more ports on the
-- same device at the same grid cell (a pre-existing data inconsistency).
-- Identify and fix them via:
--   SELECT device_id, port_row, port_col, array_agg(id) AS ids
--   FROM switch_ports
--   WHERE device_id IS NOT NULL
--   GROUP BY device_id, port_row, port_col
--   HAVING COUNT(*) > 1;
ALTER TABLE switch_ports
    ADD CONSTRAINT uq_switch_ports_position
    UNIQUE (device_id, port_row, port_col)
    DEFERRABLE INITIALLY IMMEDIATE;
