# ProSpeaking config

All environment-specific settings live here (not under `/srv/www/`).

## Setup

```bash
cp config/.env.example config/.env
# edit DB_HOST, DB_USER, DB_PASS

# optional: production cluster map from your team
cp config/php_include.example.php config/php_include.local.php
```

## Files

| File | Purpose |
|------|---------|
| `bootstrap.php` | Include from app PHP files — loads env + `php_include.php` |
| `php_include.php` | DB connection, `$amountField`, `$teamArr`, mock fallback |
| `.env` | Local credentials (`APP_DEBUG`, `DEV_MOCK`) — **gitignored** |
| `php_include.local.php` | Production `$clusters` overrides — **gitignored** |
| `php_include.example.php` | Template for `php_include.local.php` |

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
