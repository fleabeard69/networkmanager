-- Add rear panel row count to devices
ALTER TABLE devices
    ADD COLUMN panel_rear_rows INTEGER NOT NULL DEFAULT 0
        CHECK (panel_rear_rows BETWEEN 0 AND 10);
