# Vorta Productivity Tracker

A lightweight PHP + MySQL app to record daily production reports (min 2 items/day) and monitor monthly targets (50–88 items).

## Quick install (cPanel / VPS)

1. **Database**
   - Create a MySQL database & user.
   - Import `database/schema.sql` (creates all tables, no sample data).

2. **Deploy files**
   - Upload the `public` folder contents to your web root (e.g., `public_html/`).
   - Upload `lib/` and `cron/` outside web root if possible (or keep as-is for a quick start).

3. **Configure DB**
   - Edit `lib/db.php` with your DB credentials (or set env variables `DB_HOST, DB_NAME, DB_USER, DB_PASS`).

4. **Seed sample data (optional)**
   - Run `php run_seeders.php` to populate an admin account, sample staff,
     work forces, and job types.
   - Default admin: `admin@vorta.local` / `password123`. Change this after
     your first login.

5. **Login**
   - Visit `/index.php`.

6. **Cron Reminder**
   - Set a cron at 17:00:
     ```
     0 17 * * * /usr/bin/php /path/to/cron/daily_reminder.php
     ```

## Pages

- `/index.php` – login
- `/dashboard.php` – team dashboard + charts
- `/report_form.php` – input daily reports
- `/reports_my.php` – personal progress + chart
- `/admin_reports.php` – all reports + who has <2 entries today (admin only)

## Database schema changes

Schema changes are tracked as migrations in `migrations/`. See
`MIGRATION_NOTES.md` for the history of the current baseline and any
known follow-up items.

- `php run_migrations.php` – applies any migration newer than the current
  tracked version.
- `php run_rollback.php` – rolls back the most recently applied migration.

## Notes
- Chart.js is loaded via CDN.
- This is a minimal baseline; you can add file uploads, edit/delete entries, and SSO.