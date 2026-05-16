# Deploying ProSpeaking (nginx on Ubuntu)

## 403 Forbidden — usually **not** PHP

nginx returns **403** before PHP runs. Fix nginx first; then use `APP_DEBUG` for PHP errors.

### Checklist

1. **`root` must be the repo root** (folder containing `index.php`, `Reports/`, `adminTools.php`):

   ```nginx
   root /var/www/prospeaking;   # not Reports/ and not parent with no index
   index index.php;
   ```

2. **Permissions** — nginx user (`www-data`) must read files:

   ```bash
   sudo chown -R www-data:www-data /var/www/prospeaking
   # or
   sudo find /var/www/prospeaking -type d -exec chmod 755 {} \;
   sudo find /var/www/prospeaking -type f -exec chmod 644 {} \;
   ```

3. **Directory listing** — if there is no `index.php` in `root`, nginx may 403. This repo has `index.php` at the root.

4. **SELinux / AppArmor** — rare on Ubuntu; if enabled, allow nginx to read the path.

5. **PHP-FPM** — 403 is not 502; if you get **502**, fix `fastcgi_pass` socket path in nginx.

See `dev/nginx-prospeaking.conf.example` for a working server block.

---

## Show PHP errors (after nginx serves PHP)

Create `dev/.env` on the server:

```bash
cp dev/.env.example dev/.env
echo 'APP_DEBUG=1' >> dev/.env
```

Reload the page. Errors will print in the browser.

**Turn off when stable:** remove `APP_DEBUG` or set `APP_DEBUG=0`.

### Optional: diagnose script

With `APP_DEBUG=1`:

`https://your-domain/dev/diagnose.php`

Delete `dev/diagnose.php` when done.

### PHP-FPM pool (alternative)

In `/etc/php/8.x/fpm/pool.d/www.conf` (only for temporary debugging):

```ini
php_admin_value[display_errors] = on
php_admin_value[error_reporting] = E_ALL
```

Then `sudo systemctl reload php8.2-fpm`.

---

## Production config

- Copy production `php_include.php` to `/srv/www/php_include.php` **or** use `dev/php_include.php` + `dev/.env` for DB credentials.
- Warehouse DBs (`DPH`, `DPH2`, `VFR`, `Sales`) must exist on `pslw` (or your MySQL host).
- Cron: run `dailyDPH_import.php`, etc., on a schedule.

---

## Quick URLs

| URL | Page |
|-----|------|
| `/` | DPH report |
| `/adminTools.php` | Admin menu |
| `/Reports/DPH/index.php` | DPH report |
