# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

NetManager — self-hosted home network management dashboard. Tracks devices, switch ports, IP assignments, service ports, and physical cable connections with a visual panel that mirrors hardware layout.

Stack: vanilla PHP 8.3 (no framework, no Composer), PostgreSQL 16, Nginx, Docker Compose. No external dependencies anywhere — no npm, no pip, no Composer.

## Commands

```bash
make up           # start all containers (docker compose up -d)
make down         # stop + remove volumes
make restart      # down + up
make build        # rebuild images from scratch + up
make logs         # follow logs
make psql         # shell into postgres container
make migrate FILE=db/migrations/007_foo.sql  # run a migration
```

Local dev runs on `127.0.0.1:8081` (nginx direct, no TLS). Production uses SWAG on port 443.

No test suite exists in this project.

## Architecture

**Entry point**: `app/public/index.php` — manual router using switch/case on `$_SERVER['REQUEST_URI']`. No framework routing. Instantiates controllers directly.

**Request flow**: nginx → PHP-FPM → `index.php` → Controller → Model → Template (server-rendered PHP)

**Layers**:
- `app/src/Controllers/` — thin controllers: validate input, call models, render or JSON-respond
  - `ApiController.php` — all AJAX/JSON endpoints (ports, devices, connections, reordering)
  - `BackupController.php` — full DB export/import with schema validation
- `app/src/Models/` — SQL queries via PDO wrapper; business logic lives here
  - `DeviceModel.php` — devices, IPs, services, panel dimensions
  - `PortModel.php` — switch port management, positioning, drag-and-drop swap
  - `ConnectionModel.php` — cable connections with color and anchor positioning
- `app/src/Helpers/` — cross-cutting concerns
  - `Database.php` — PDO singleton, `ATTR_EMULATE_PREPARES => false`
  - `Auth.php` — login, session, IP-based rate limiting
  - `Session.php` — HttpOnly/Secure/SameSite=Strict cookies, 1-hour idle timeout
  - `Csrf.php` — HMAC CSRF tokens, checked on every POST
- `app/templates/` — server-rendered PHP templates; output always via `htmlspecialchars()`
- `app/public/js/app.js` — ~3500 lines of vanilla JS; handles AJAX, drag-and-drop, canvas cable drawing, modals, toasts
- `app/public/css/app.css` — ~1800 lines, no preprocessor

**Database**: `db/migrations/` contains 6 SQL files run automatically at container startup. Triggers enforce one-connection-per-port and one-primary-IP-per-device at the DB level.

**Bootstrap**: `app/init/bootstrap.php` runs once on startup to create the admin user if the users table is empty.

## Key Patterns

- All DB queries use PDO prepared statements — never interpolate user input into SQL
- CSRF token required on every state-changing request (POST form or AJAX via `X-CSRF-Token` header)
- Rate limiting: 10 failed logins per 15 min by IP, 5 per 5 min by session
- JSON API responses from `ApiController` follow `{"success": bool, "error": "..."}` shape
- Panel editor uses drag-and-drop with position swap semantics (not insert), stored as `position` integer per device
- Cable connections store two endpoints (`port_id`) plus color, draw positions, and anchor offsets — rendered client-side on `<canvas>`

## Environment

Copy `.env.example` to `.env` before first run. Required vars: `DOMAIN`, `DB_*` credentials, `ADMIN_*` initial user, `APP_SECRET` (used for HMAC CSRF tokens).
