# NetManager

A self-hosted home network manager for tracking devices, switch ports, IP assignments, service ports, and physical cable connections. Runs as a Docker Compose stack with HTTPS via SWAG/Let's Encrypt.

---

## Features

### Dashboard
- Visual panel view of every device and its switch ports, mirroring the physical layout of the hardware
- Front and rear panel faces for devices with rear-facing ports
- Color-coded port status (active, WAN, management, disabled)
- Port type and PoE badges on each port card
- VLAN ID display per port
- Drag-to-reorder device sections to match your rack layout
- Orthogonal cable connection lines between ports with bridge arcs at crossings
- 16-color cable picker for creating connections
- One-cable-per-port enforcement

### Devices
- Add, edit, and delete network devices (server, workstation, router, switch, access point, NAS, IoT, printer, camera, phone, TV, game console, and more)
- Hostname, MAC address, device type, and free-text notes
- Per-device front and rear panel row/column configuration

### IP Addresses
- Track multiple IP addresses per device
- Subnet (CIDR), gateway, and interface fields
- Primary IP flag per device (enforced at the database level)

### Service Ports
- Track open service ports per device (TCP, UDP, or both)
- Port number, service name, description, and external-access flag
- Upsert on device + protocol + port — editing is non-destructive

### Switch Ports
- Add and manage switch ports independently or via the panel editor
- Port types: RJ45, SFP, SFP+, WAN, Management
- Link speed: 10M, 100M, 1G, 2.5G, 5G, 10G
- PoE enabled flag, VLAN ID, status (active/disabled/unknown), and notes
- Assign ports to devices; unassign without deleting

### Panel Editor
- Per-device drag-and-drop visual port layout editor
- Separate front and rear panel grids
- Resize panel dimensions (rows and columns) with live preview
- Port row/column positioning validated against panel bounds (1–20 rows, 1–50 cols)

### Port Connections
- Draw cable connections between any two ports across any devices
- Connections rendered as orthogonal (right-angle) SVG lines on the dashboard
- Bridge arcs automatically drawn where lines cross
- Connected port cards tinted to match their cable color
- One connection per port enforced in both application logic and database trigger

### Backup & Restore
- Export the full configuration (devices, ports, connections, IPs, service ports) as a JSON file
- Re-import from a backup to restore everything in a single transaction
- Strict validation of all imported fields with allowlist coercion for enums

---

## Stack

| Component | Image |
|-----------|-------|
| App (PHP) | `php:8.3-fpm-alpine` |
| Database | `postgres:16-alpine` |
| Web server | `nginx:1.27-alpine` |
| HTTPS / reverse proxy | `lscr.io/linuxserver/swag` (Let's Encrypt, Cloudflare DNS) |

No external PHP dependencies — no Composer, no framework. Vanilla PHP 8.3 with PDO/pgsql.

---

## Setup

### Prerequisites
- Docker and Docker Compose
- A domain name pointed at your server
- Cloudflare API credentials (for DNS-01 Let's Encrypt validation)

### Installation

**1. Clone the repository**
```bash
git clone https://github.com/fleabeard69/networkmanager.git
cd networkmanager
```

**2. Create your `.env` file**
```bash
cp .env.example .env
```

Edit `.env` and fill in all values:

| Variable | Description |
|----------|-------------|
| `DOMAIN` | Your domain (e.g. `example.com`) |
| `SUBDOMAINS` | Optional subdomain prefix (e.g. `lan` → `lan.example.com`) |
| `EMAIL` | Email for Let's Encrypt registration |
| `DB_NAME` | PostgreSQL database name |
| `DB_USER` | PostgreSQL username |
| `DB_PASS` | PostgreSQL password — use a strong random value |
| `ADMIN_USER` | Admin account username (default: `admin`) |
| `ADMIN_PASS` | Admin account password — **required on first boot** |
| `APP_SECRET` | Long random string for CSRF token signing |
| `TZ` | Timezone (e.g. `America/New_York`) |

**3. Generate a strong `APP_SECRET`**
```bash
openssl rand -hex 32
```
Paste the output as your `APP_SECRET` value.

**4. Configure SWAG**

Place your Cloudflare API credentials in `swag/config/dns-conf/cloudflare.ini` before starting. See the [SWAG documentation](https://docs.linuxserver.io/general/swag) for full proxy configuration.

**5. Start the stack**
```bash
docker compose up -d
```

On first boot, the database schema is created automatically and the admin user is created from `ADMIN_USER` / `ADMIN_PASS`. The app is available at `https://yourdomain.com` once SWAG obtains its certificate.

---

## Upgrading an Existing Deployment

When updating from an earlier version, apply any new database migrations manually:

```bash
# Migration 002 — adds rear panel row support
docker compose exec db psql -U $DB_USER -d $DB_NAME \
  -f /docker-entrypoint-initdb.d/002_add_rear_panel.sql

# Migration 003 — extends port_row range to 1–20
docker compose exec db psql -U $DB_USER -d $DB_NAME \
  -f /docker-entrypoint-initdb.d/003_extend_port_row.sql

# Migration 004 — adds login_attempts table for brute-force protection
docker compose exec db psql -U $DB_USER -d $DB_NAME \
  -f /docker-entrypoint-initdb.d/004_login_attempts.sql
```

Only run the migrations that haven't been applied to your database yet.

After applying migrations, restart the app container:
```bash
docker compose restart app
```

---

## Security

- **Authentication** — Session-based login with `HttpOnly`, `Secure`, and `SameSite=Lax` cookie flags. Session ID regenerated on login.
- **CSRF protection** — Per-session token (HMAC-keyed with `APP_SECRET`) required on all state-changing requests. API endpoints use `X-CSRF-Token` header.
- **Brute-force protection** — IP-based rate limiting (10 failed attempts per 15 minutes, stored in the database) plus session-based limiting (5 per 5 minutes). Timing-safe dummy hash prevents username enumeration.
- **Defense-in-depth auth** — Every controller constructor independently enforces authentication, independent of the global gate in `index.php`.
- **Scoped deletes** — IP address and service port deletions are scoped by device ID in a single atomic SQL statement, preventing cross-resource authorization bypass.
- **Parameterized queries** — All database access uses PDO with `ATTR_EMULATE_PREPARES => false` (true prepared statements, no SQL injection via model layer).
- **Input validation** — Allowlist validation for all enum fields (device type, port type, speed, protocol, color). MAC address, IP, VLAN ID, and port range validated server-side.
- **Backup import** — Full field-level validation and enum coercion on import. All changes executed in a single transaction with rollback on error. File size capped at 5 MB.
- **HTTPS** — All traffic goes through SWAG on port 443. Port 8081 (nginx direct) is bound to `127.0.0.1` only — LAN and WAN cannot reach it.
- **Output escaping** — All user-supplied values passed through `htmlspecialchars()` before rendering.

---

## Local Development

Port 8081 is bound to `127.0.0.1` and proxies directly to the nginx/PHP container without TLS. Useful for development or if SWAG is not configured:

```
http://localhost:8081
```

---

## License

MIT
