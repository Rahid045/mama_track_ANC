# MamaTrack ANC
Web-based maternal health record, trend-based risk assessment and missed-appointment system for antenatal care in Uganda
(PHP + HTML/CSS/JS, SQLite via PDO — no database server needed).

## Run it (2 minutes)
Requirements: PHP 8.1+ with `pdo_sqlite`, `curl`, `mbstring` (all included in XAMPP).

**Quick start (any OS):**
```
cd mamatrack
php seed_demo.php        # optional: sample doctors, mothers, visits
php -S localhost:8000
```
Open http://localhost:8000
**XAMPP:** copy the folder into `htdocs/`, start Apache, open http://localhost/mamatrack

First login: `admin@mamatrack.local` / `Admin@123` (you must change it immediately).
Demo staff (after seed): `sarah@mamatrack.local`, `peter@mamatrack.local` (doctors), `grace@mamatrack.local` (nurse) — password `Demo@1234`.

## Deploy on Vercel

This repository includes a `Dockerfile` and `Caddyfile` for Vercel's Container runtime. SQLite is retained for local XAMPP use only: Vercel instances have non-durable local storage, so production requires hosted PostgreSQL.

1. Create a PostgreSQL database (the Vercel Marketplace Postgres integration is suitable) and copy its connection URL.
2. Import the repository into Vercel. Set the project framework to **Container** if it is not detected automatically.
3. In **Settings → Environment Variables**, add `DATABASE_URL`, `BASE_URL` (your production URL), and a long random `CRON_SECRET`; use `.env.example` as the template. Do not commit these values.
4. Deploy. The first request creates the tables and the initial administrator. Change the initial administrator password immediately.

`vercel.json` runs the protected missed-appointment/reminder task hourly in production. The task validates Vercel's `CRON_SECRET` bearer token. Backups must be managed through the hosted PostgreSQL provider; downloading a local SQLite backup is intentionally disabled in production.

## How it maps to the proposal
| Objective | Where |
|---|---|
| Secure recording of data across sequential visits | `patient_form.php`, `visit_form.php` (validated vitals, CSRF, prepared statements, audit trail) |
| Automatic alerts to physician on missed visit | `includes/notify.php → process_missed_appointments()`, run by `cron/run.php` and on normal page use |
| Rescheduling by doctors and mothers | `reschedule.php` (staff via login; mothers via a secure one-time SMS link) |
| Trend-based risk assessment | `includes/risk.php` (latest values + trends + attendance, every point explained) |
| Role-based access | admin / doctor / nurse (`users.php`) |
| Backup against record loss | `backup.php` (download) + daily auto-backup in `cron/run.php` |

## Turn on real SMS / email
Edit `includes/config.php` → `notify`: set `mode` to `live`, add your Africa's Talking `at_username` and `at_api_key`
(use `sandbox` to test first). Email uses PHP `mail()`; for production configure SMTP/sendmail.
In `log` mode (default) every message is only recorded in **Alerts log** — safe for demos.

## Schedule the checks
Linux: `0 * * * * php /path/to/mamatrack/cron/run.php`  ·  Windows: Task Scheduler → `php.exe ...\cron\run.php`

## Risk engine (edit `includes/risk.php`)
Points are added for: BP level and rising BP trend, persistent hypertension, hypertension + proteinuria (pre-eclampsia),
rapid weight gain, anaemia and falling Hb, fetal heart rate, fundal-height mismatch, fever, tachycardia, age, HIV-positive,
grand multiparity, late first ANC visit, missed appointments. **High** = any critical finding or score ≥ 7; **Moderate** ≥ 3.
These are decision-support thresholds — have clinicians validate them against Uganda MoH ANC guidelines before real use.

## Before going live
Serve over HTTPS; keep `data/` outside the web root (or keep the supplied `.htaccess`); set `base_url`; obtain ethical
approval / consent and comply with the Uganda Data Protection and Privacy Act, 2019. To use MySQL, change the PDO
connection in `includes/db.php` and adapt the `CREATE TABLE` syntax.
