# University MIS Helpdesk and IT Service Management System

UMIS-Helpdesk is a Laravel modular monolith for university IT service requests. It is under active development. Implemented areas include authentication, password reset and server-side role checks; admin user CSV import, departments, ticket categories, and asset maintenance; ticket submission, assignment, status history, conversations, private attachments, in-app notifications and email integration; business-hours SLAs with configurable pause, reminders, and escalation; preventive maintenance; knowledge articles and feedback; live dashboards; filtered ticket reports with database-backed breakdowns and CSV, Excel, and PDF export; and audit review. See the implementation checklist in [docs/architecture.md](docs/architecture.md); unchecked items remain outstanding.

## Architecture and database

See [docs/architecture.md](docs/architecture.md) for the modular architecture, Mermaid ERD, and delivery checklist. [docs/database.md](docs/database.md) documents the table inventory, referential actions, factory map, and constraints. Migrations are the authoritative schema.

## Requirements

* PHP 8.2 or newer with `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `gd`, and `zip`. GD and ZIP are needed for Excel exports; enable them in CLI and web PHP.
* Laravel 12 (selected to support the PHP 8.2 bundled with this XAMPP installation).
* Composer 2 and MySQL 8 for the deployment target. XAMPP or equivalent can provide local PHP and a MySQL-compatible database. XAMPP in this environment bundles MariaDB 10.4.32; migrations were run there, but a MySQL 8 staging run is still required.
* Node.js/npm is not required by the current Blade screens; Bootstrap, Inter, and Chart.js are presently loaded from CDNs.

## Local setup (Windows / XAMPP)

1. Install XAMPP and Composer. Confirm `php -v`, `php --ini`, `composer --version`, and that the CLI PHP selected by Composer is the XAMPP PHP with `pdo_mysql`, `gd`, and `zip` enabled. Enable `extension=gd` and `extension=zip` in the XAMPP `php.ini` used by CLI and Apache, then restart the terminal and Apache.
2. Start MySQL from the XAMPP Control Panel. In phpMyAdmin, create a database named `umis_helpdesk` using `utf8mb4` collation.
3. From this project directory, install PHP packages and copy local settings:

   ```powershell
   composer install
   Copy-Item .env.example .env
   php artisan key:generate
   ```

   Set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env` to match your local MySQL account. For XAMPP's default root account, use `DB_USERNAME=root` and an empty `DB_PASSWORD`; set a password if MySQL has one.
4. Apply the schema and local-only demonstration accounts:

   ```powershell
   php artisan migrate --seed
   ```

5. Start the local server:

   ```powershell
   php artisan serve --host=127.0.0.1 --port=8000
   ```

6. Open `http://127.0.0.1:8000`.

7. To run recurring SLA reminders, configure Windows Task Scheduler to run `php artisan schedule:run` once per minute from this project directory, or run `php artisan schedule:work` in a development terminal. Production hosting must likewise invoke Laravel's scheduler.

### Local password setup emails

The default `MAIL_MAILER=log` is for local development and does not deliver email. When an account is created or a setup link is resent, copy the newest reset URL from `storage/logs/laravel.log` and open it on the same computer as the local server. Links expire after 60 minutes. To deliver email, configure the SMTP values in `.env`, set `APP_URL` to an address reachable by the recipient, then run `php artisan config:clear`.

### Local demonstration accounts

The seeder creates accounts with `@demo.university.test` addresses. Each password is `DemoOnly!2026`. All names, emails, employee numbers, department names, and credentials are conspicuously marked as demo/test data. These accounts exist only after an explicit local `db:seed`; never use or seed these credentials in production.

## Privacy and deployment notes

The application defaults to `Asia/Manila`. Replace demonstration organization names and accounts before university testing. Set a strong production `APP_KEY`, production database credentials, HTTPS, secure cookies, mail transport, queue worker, backups, and restricted private storage before deployment. Ticket attachments use Laravel's non-public `local` disk and an authorized download route. Do not publish `storage/app/private` or commit `.env`.

Collect only the user, contact, request, and equipment information needed for support. Define institutional retention, access, incident response, and privacy notice procedures with the university's Data Protection Officer before collecting real records under RA 10173. The current code provides an audit trail foundation; it does not by itself certify legal compliance or production readiness.

## Development checks

Use the XAMPP PHP CLI so `pdo_mysql` is enabled: `C:\xampp\php\php.exe artisan test`, `C:\xampp\php\php.exe artisan route:list`, and `C:\xampp\php\php.exe artisan migrate:status`. Do not use `migrate:fresh` against a database containing real records. See [docs/technical-test-report.md](docs/technical-test-report.md) for executed test results and remaining acceptance checks, and [docs/operations.md](docs/operations.md) for the isolated local backup/restore rehearsal and production backup requirements.

## Current limitations

This is a working development build, not a production-approved university deployment. Before organizational acceptance, complete a MySQL 8 staging run, visual review at desktop/tablet/mobile breakpoints, institutional UAT and security review, approved backup scheduling/encryption, and university logo configuration. Ticket, asset inventory, and maintenance reports are database-backed and support CSV, Excel, and PDF downloads with filters. Ticket reporting includes daily, weekly, monthly, and annual volume, technician elapsed-time metrics, SLA compliance, and user satisfaction. SLA alerts and pending-user reminders run through Laravel's scheduler; configure `MAIL_*` values for email delivery. The local restore rehearsal does not replace institutional backup operations. Role permissions are editable; a few maintenance and MIS work actions also enforce the built-in MIS roles. Responsive CSS and table wrappers are present, but visual viewport verification was not completed because the installed Chrome command reused an existing browser session instead of producing isolated screenshots. CDN assets need an internet connection in this version.
