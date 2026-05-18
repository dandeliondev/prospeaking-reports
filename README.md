# ProSpeaking

Legacy PHP reporting and operations tooling for Vicidial dialer data. Imports aggregate stats into local MySQL schemas (`DPH`, `DPH2`, `VFR`, `Sales`) on the `pslw` connection and serves HTML reports.

## Requirements

- PHP with mysqli
- MySQL 5.7+ or MariaDB 10.x (for local reporting DB)
- Vicidial MySQL hosts (`psl1`, `psl2`, `pslv`) for imports in production — not required for UI dev with mock or seed data

## Quick start

### 1. Configuration

```bash
cp config/.env.example config/.env
```

Edit `config/.env` with your MySQL credentials:

| Variable | Purpose |
|----------|---------|
| `DB_HOST` | MySQL host (default `127.0.0.1`) |
| `DB_PORT` | Port (default `3306`) |
| `DB_USER` | Username |
| `DB_PASS` | Password |
| `DEV_MOCK` | `1` = in-memory dummy data; `0` = use real MySQL |
| `APP_DEBUG` | `1` = show PHP/mysqli errors in the browser |

See [config/README.md](config/README.md) for production overrides (`php_include.local.php`) and load order.

### 2. Database schema (local)

Create the reporting databases and tables:

```bash
mysql -u root -p < database/00_create_databases.sql
mysql -u root -p < database/01_dph.sql
mysql -u root -p < database/02_dph2.sql
mysql -u root -p < database/03_vfr.sql
mysql -u root -p < database/04_sales.sql
```

Optional demo rows (matches `config/mock_data.php`):

```bash
mysql -u root -p < database/05_seed_dev.sql
```

Then set `DEV_MOCK=0` in `config/.env` and open a report (e.g. `Reports/DPH/`).

### 3. Connection diagnostics

Open **`/dbStatus.php`** in the browser for a live report: `.env` / mock mode, MySQL reachability, `DPH` / `DPH2` / `VFR` / `Sales` tables, Vicidial schemas, cluster URLs, and API auth flags.

Optional: set `DIAG_KEY` in `config/.env` and visit `dbStatus.php?key=…` to restrict access.

### 4. PHP entry points

Each script loads config via:

```php
require_once __DIR__ . '/../../config/bootstrap.php';
```

Path depth varies by folder (`Reports/DPH/`, `Sales/`, `Admin/`, etc.).

## Database layout

| Schema | Purpose |
|--------|---------|
| `DPH` | Daily agent stats (`DEPTKEY`) — `DAILY`, `DAILY_INSERT`, `ARCHIVE` |
| `DPH2` | Hourly agent stats (`HOUR`) — same table names |
| `VFR` | Verifier/closer stats |
| `Sales` | `Sales`, `Roust`, `DNCs`, `CAMPAIGNS` |

Vicidial tables (`vicidial_list`, `vicidial_agent_log`, …) live on external `asterisk` / `vicidial` databases and are **not** defined in `database/`.

SQL files are applied in numeric order under [`database/`](database/).

## Development modes

| Mode | Setup |
|------|--------|
| **Mock** | No `.env`, or `DEV_MOCK=1` — uses `config/mock_mysqli.php` |
| **Local DB** | `.env` + run `database/*.sql` + `DEV_MOCK=0` |
| **Production** | `.env` + `config/php_include.local.php` with `$clusters`, Vicidial hosts, API creds |

## Project structure

```
config/          Environment, DB connection, mock layer
database/        MySQL DDL and optional dev seed
Reports/         DPH, DPH2, VFR, Sales reports and imports
Sales/           Sales/Roust imports and exports
Admin/           Ops tools (DNC, duplicates, AMD, …)
Lists/           Vicidial list upload/delete
VICI/            Vicidial-related reports
```

## Further reading

- [config/README.md](config/README.md) — config files, credentials, deploy notes
