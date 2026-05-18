# ProSpeaking config

Environment-specific settings live here (not under `/srv/www/`).

## Setup

```bash
cp config/.env.example config/.env
# edit DB_HOST, DB_PORT, DB_USER, DB_PASS

# optional: production cluster map from your team
cp config/php_include.example.php config/php_include.local.php
```

## Database connection

Connection details are read from **`config/.env`** (gitignored):

| Variable | Default | Purpose |
|----------|---------|---------|
| `DB_HOST` | `127.0.0.1` | MySQL server for `connectToCluster()` |
| `DB_PORT` | `3306` | Port |
| `DB_USER` | `root` | Username |
| `DB_PASS` | *(empty)* | Password |
| `DEV_MOCK` | *(auto)* | `1` = mock data; `0` = real DB; omit `.env` to auto-mock |
| `APP_DEBUG` | off | `1` enables display errors and strict mysqli |

`prospeaking_db_config()` in `php_include.php` supplies these values. All cluster names (`pslw`, `psl1`, `psl2`, `pslv`) share this single connection in the current code; the app then selects a schema with `prospeaking_select_db($conn, 'DPH')` (or `DPH2`, `VFR`, `Sales`).

**Local reporting schemas** — create them from the repo root:

```bash
mysql -u root -p < database/00_create_databases.sql
mysql -u root -p < database/01_dph.sql
mysql -u root -p < database/02_dph2.sql
mysql -u root -p < database/03_vfr.sql
mysql -u root -p < database/04_sales.sql
mysql -u root -p < database/05_seed_dev.sql   # optional
```

Set `DEV_MOCK=0` after the schema exists and credentials match.

**Production** — copy `php_include.local.php` for per-cluster Vicidial hosts, `$clusters` API URLs, `$Scon`, `$campArr`, and other overrides from your live environment. Reporting still uses `DB_*` from `.env` unless you extend `connectToCluster()` locally.

## Files

| File | Purpose |
|------|---------|
| `bootstrap.php` | Include from app PHP files — loads env + `php_include.php` |
| `env.php` | Loads `.env`, configures error display |
| `php_include.php` | DB connection, Vicidial field names, `$teamArr`, mock fallback |
| `.env` | Local credentials — **gitignored** |
| `.env.example` | Template for `.env` |
| `php_include.local.php` | Production `$clusters` and overrides — **gitignored** |
| `php_include.example.php` | Template for `php_include.local.php` |
| `mock_data.php` / `mock_mysqli.php` | In-memory data when `DEV_MOCK` or no `.env` |

## Load order

1. `config/env.php` — `.env` and error display
2. `config/php_include.local.php` — if present
3. `config/php_include.php`
4. Legacy `/srv/www/php_include.php` — if still used on old servers

## Deploy on `/var/www/html`

No symlink to `/srv/www` required. Ensure every PHP entry uses:

```php
require_once __DIR__ . '/../../config/bootstrap.php';
```

(depth varies by folder)

For verifier date ranges (`$PSD`, `$PED`):

```bash
cp VICI/vfr_include.example.php VICI/vfr_include.php
```

See the root [README.md](../README.md) for database schemas and project layout.
