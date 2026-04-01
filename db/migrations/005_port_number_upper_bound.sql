-- Add upper bound to switch port_number (max 9999) to match PHP validation.
-- All other bounded integer columns already carry DB-level CHECK constraints;
-- port_number previously only enforced > 0 with no ceiling.
ALTER TABLE switch_ports
    DROP CONSTRAINT IF EXISTS switch_ports_port_number_check,
    ADD CONSTRAINT switch_ports_port_number_check CHECK (port_number BETWEEN 1 AND 9999);
