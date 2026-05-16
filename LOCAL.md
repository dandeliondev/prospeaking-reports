# Local development (dummy data)

Run the DPH report UI with sample rows—no production database required.

## Prerequisites

**Option A — Docker (recommended)**

- [Docker](https://docs.docker.com/get-docker/) with Compose v2  
- Enable **WSL integration** in Docker Desktop if you use WSL2

**Option B — No Docker**

- PHP 8+ with `mysqli`
- MySQL 8+ (root password `prospeaking`, or set `DB_PASS`)

## Start

**With Docker:**

```bash
./dev/start.sh
```

Or: `docker compose up --build -d`

**Without Docker:**

```bash
./dev/start-local.sh
```

This imports `dev/seed.sql` (if MySQL is available) and runs `php -S localhost:8080` from the repo root.

You can also run PHP yourself from the repo (no symlinks needed):

```bash
mysql -u root -p < dev/seed.sql   # once
php -S localhost:8080 -t .
```

## View the UI

1. Open **http://localhost:8080/** (redirects to the DPH report).
2. Leave **Daily** selected.
3. Click **Submit** — the table shows five dummy agents.

**No MySQL?** If you have no database (or wrong root password), the app automatically uses **in-memory dummy data** and shows a yellow “Demo mode” banner. To force that: `echo 'DEV_MOCK=1' >> dev/.env`

**Use your MySQL instead:** copy `dev/.env.example` to `dev/.env` and set `DB_USER` / `DB_PASS`, then import `dev/seed.sql`.

Direct link: http://localhost:8080/Reports/DPH/index.php

## What runs

| Service | Role |
|---------|------|
| `db` | MySQL 8 with `DPH.DAILY` seed data + empty `vicidial` tables |
| `web` | PHP built-in server; mounts `dev/php_include.php` at `/srv/www/php_include.php` |

## Stop / reset

```bash
docker compose down          # stop
docker compose down -v       # stop and wipe DB (re-seed on next up)
```

## Files

- `dev/php_include.php` — local DB connection (used only in Docker)
- `dev/seed.sql` — dummy DPH rows
- `docker-compose.yml` — stack definition
