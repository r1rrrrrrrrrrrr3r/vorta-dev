# Vorta Productivity Tracker

A lightweight PHP + MySQL app to record daily production reports (min 2 items/day) and monitor monthly targets (50–88 items).

## Quick install (cPanel / VPS)

1. **Database**
   - Create a MySQL database and a dedicated MySQL user with a strong password.
   - Take a verified database backup before importing the schema or running
     migrations.
   - Import `vorta-app/vorta_prodtracker.sql` only as the legacy base schema.
   - From the `vorta-app` directory, run `php run_migrations.php` immediately.
   - The migration runner detects a fresh database and applies the complete
     ordered sequence; do not edit `migrations/schema_version.php` manually.

2. **Deploy files**
   - Configure the web server document root to `vorta-app/public/`, not the
     repository root and not `vorta-app/`.
   - Keep `lib/`, `migrations/`, `seeders/`, `storage/`, and all CLI runners
     outside the document root. Do not make these directories web-accessible.
   - Remove or migrate any legacy `vorta-app/uploads/` files before production.
     Proof images must remain under private `storage/uploads/<company_id>/`.

3. **Configure DB**
   - Set `APP_ENV=production` and non-empty environment variables `DB_HOST`,
     `DB_NAME`, `DB_USER`, and `DB_PASS`. Production also requires an HTTPS
     `APP_URL`; the application intentionally refuses unsafe fallbacks.
   - For the existing local QC setup only, `vorta-app/config.local.php` may
     contain the local database values and `APP_URL` (for example,
     `http://127.0.0.1:8010`). It is ignored by Git and must never be copied
     to production.
   - For local QC, use `APP_ENV=development` and the ignored
     `vorta-app/config.local.php`. With PowerShell:
     ```powershell
     $env:DB_HOST='127.0.0.1'
     $env:DB_NAME='vorta_prodtracker'
     $env:DB_USER='vorta_app'
     $env:DB_PASS='your-local-password'
     $env:APP_URL='http://127.0.0.1:8000'
     $env:APP_ENV='development'
     Set-Location .\vorta-app
     php -S 127.0.0.1:8000 -t public
     ```
   - `.env.example` documents the required variables; it is a template, not
     an automatically loaded secret file.

4. **Configure the canonical application URL**
   - Set `APP_URL` to the public HTTPS URL, without a trailing slash
     (for example, `https://tracker.example.com`).
   - Invitation and password-reset links are not generated from the request
     host, which prevents Host-header poisoning.

5. **Seed sample data (optional)**
   - Set `SEED_DEFAULT_PASSWORD` to a unique password of at least 12
     characters, then run `php run_seeders.php`.
   - Never commit the seed password or use the sample seeder in production.

6. **Login**
   - Visit `/index.php`.

7. **Cron Reminder**
   - Set a cron at 17:00:
     ```
     0 17 * * * /usr/bin/php /path/to/cron/daily_reminder.php
     ```

## Pages

- `/index.php` – login
- `/dashboard.php` – team dashboard + charts
- `/report_form.php` – input daily reports
- `/my_reports.php` – personal progress + chart
- `/admin_reports.php` – all reports + who has <2 entries today (admin only)

## Database schema changes

Schema changes are tracked as migrations in `migrations/`. See
`MIGRATION_NOTES.md` for the history of the current baseline and any
known follow-up items.

- `php run_migrations.php` – applies any migration newer than the current
  tracked version.
- `php run_rollback.php` – rolls back the most recently applied migration.
- Migration, rollback, and seeder runners are CLI-only and should never be
  exposed through the web server.

## Deployment smoke check

After deployment, verify the following before inviting real users:

1. `public/index.php` loads over HTTPS and redirects unauthenticated users
   without exposing database details.
2. A new company can register, sign in, select its timezone, invite a user,
   accept the invitation, and see the employee record created.
3. Two test companies cannot see each other's users, employees, reports,
   attendance, settings, or proof images.
4. A report with a proof image is stored outside the document root and can
   only be downloaded through an authorized report endpoint.
5. Password reset and invitation emails are delivered through the configured
   mail transport.
6. Take a fresh database backup and confirm the migration command completes
   cleanly before enabling production traffic.

## Upload storage

Report proof images are stored under `storage/uploads/<company_id>/`, outside
the public web root, with restrictive directory permissions. Downloads must go
through the authorization-checked report endpoints. Existing legacy files in
`uploads/` should be migrated and removed from the web root before production.

## Mail delivery

Invitations and password-reset links use the server mail transport. Configure
an SMTP/provider-backed transport for production; if mail is unavailable, the
application reports that clearly and does not claim delivery succeeded.

## Notes
- Chart.js is loaded via CDN.
- After changing Tailwind source classes, run `npm run build` for a one-time
  production CSS build. Use `npm run build:watch` only during development.
- PHP must include PDO MySQL, Fileinfo, GD with JPEG/PNG/WebP support, and
  Mbstring. Verify the supported PHP version and extensions in the target
  environment before deployment.