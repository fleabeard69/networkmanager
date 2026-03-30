-- Network Manager — initial schema

CREATE TABLE users (
    id         SERIAL       PRIMARY KEY,
    username   VARCHAR(64)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE TABLE devices (
    id          SERIAL       PRIMARY KEY,
    hostname    VARCHAR(128) NOT NULL,
    mac_address VARCHAR(17),
    device_type VARCHAR(32)  NOT NULL DEFAULT 'unknown',
    notes       TEXT         NOT NULL DEFAULT '',
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE TABLE switch_ports (
    id          SERIAL      PRIMARY KEY,
    port_number INTEGER     NOT NULL UNIQUE CHECK (port_number > 0),
    label       VARCHAR(64) NOT NULL DEFAULT '',
    port_type   VARCHAR(8)  NOT NULL DEFAULT 'rj45'
                    CHECK (port_type IN ('rj45', 'sfp', 'sfp+', 'wan', 'mgmt')),
    speed       VARCHAR(8)  NOT NULL DEFAULT '1G',
    poe_enabled BOOLEAN     NOT NULL DEFAULT FALSE,
    vlan_id     INTEGER     CHECK (vlan_id BETWEEN 1 AND 4094),
    status      VARCHAR(16) NOT NULL DEFAULT 'active'
                    CHECK (status IN ('active', 'disabled', 'unknown')),
    device_id   INTEGER     REFERENCES devices(id) ON DELETE SET NULL,
    notes       TEXT        NOT NULL DEFAULT '',
    port_row    INTEGER     NOT NULL DEFAULT 1 CHECK (port_row BETWEEN 1 AND 10),
    port_col    INTEGER     NOT NULL DEFAULT 1 CHECK (port_col BETWEEN 1 AND 50),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE ip_assignments (
    id         SERIAL      PRIMARY KEY,
    device_id  INTEGER     NOT NULL REFERENCES devices(id) ON DELETE CASCADE,
    ip_address INET        NOT NULL,
    subnet     CIDR,
    gateway    INET,
    interface  VARCHAR(32) NOT NULL DEFAULT '',
    is_primary BOOLEAN     NOT NULL DEFAULT FALSE,
    notes      TEXT        NOT NULL DEFAULT ''
);

CREATE TABLE service_ports (
    id          SERIAL      PRIMARY KEY,
    device_id   INTEGER     NOT NULL REFERENCES devices(id) ON DELETE CASCADE,
    protocol    VARCHAR(8)  NOT NULL DEFAULT 'tcp'
                    CHECK (protocol IN ('tcp', 'udp', 'both')),
    port_number INTEGER     NOT NULL CHECK (port_number BETWEEN 1 AND 65535),
    service     VARCHAR(64) NOT NULL DEFAULT '',
    description TEXT        NOT NULL DEFAULT '',
    is_external BOOLEAN     NOT NULL DEFAULT FALSE,
    UNIQUE (device_id, protocol, port_number)
);

-- Indexes
CREATE INDEX idx_switch_ports_layout     ON switch_ports(port_row, port_col);
CREATE INDEX idx_switch_ports_device_id  ON switch_ports(device_id);
CREATE INDEX idx_ip_assignments_device_id ON ip_assignments(device_id);
CREATE INDEX idx_service_ports_device_id  ON service_ports(device_id);

-- Enforce at most one primary IP per device at the database level
CREATE UNIQUE INDEX idx_one_primary_ip ON ip_assignments(device_id) WHERE is_primary = TRUE;
