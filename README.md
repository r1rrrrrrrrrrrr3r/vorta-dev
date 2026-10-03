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

- `/index.php` – sign in
- `/dashboard.php` – staff: **Today** (attendance, today's reports, monthly progress, team ranking); admin: **Dashboard** (KPIs, staff performance, job types)
- `/my_reports.php` – staff: own reports with month/status/search filters and a detail drawer
- `/report_form.php`, `/edit_report.php` – new / edit report
- `/attendance.php` – staff: check in/out, leave, absence, history
- `/admin_reports.php` – admin: tabs `?tab=all` (all reports, filters) and `?tab=today` (today's completion)
- `/admin_attendance.php` – admin: tabs `?tab=daily`, `?tab=monthly`, `?tab=missing` (not checked in) + Excel export
- `/admin_master_data.php` – admin: users, employees, work forces, job types
- `/settings.php` – admin: monthly and daily report targets
- `/account.php` – profile, appearance (theme) and password

Old URLs (`profile.php`, `edit_profile.php`, `change_password.php`, `admin_not_attendance.php`) redirect to the new pages.

## Styles

Tailwind CSS v4 is built with the CLI from `vorta-app/`:

```
npm install
npm run css     # one-off minified build to public/css/output.css
npm run build   # watch mode while developing
```

Design tokens and components live in `src/css/input.css`; page shells in `views/layout/`.

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