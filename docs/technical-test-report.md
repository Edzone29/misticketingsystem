# UMIS-Helpdesk Technical Test Report

**Run date:** 6 November 2026  
**Build:** Laravel 12 development build  
**Result:** Automated checks passed; production acceptance checks remain open.

## Executed checks

| Check | Result | Evidence / scope |
|---|---|---|
| Laravel automated suite | **Passed** | `C:\xampp\php\php.exe artisan test` — 60 tests, 351 assertions. Tests ran with the project's SQLite test configuration. |
| PHP formatting | **Passed** | `vendor\bin\pint --test` after applying Pint formatting. |
| Dependency advisory scan | **Passed** | `composer audit --no-interaction` — no security vulnerability advisories reported. |
| Local HTTP smoke check | **Passed** | Requested `http://127.0.0.1:8000/login` from the running local server; it returned HTTP 200. The development server remains running for local access. |
| Mobile / tablet / desktop rendering | **Not completed** | Static review found Bootstrap responsive layouts, table wrappers, and 991px/575px breakpoints. A headless Chrome screenshot attempt reused an existing browser session and produced no screenshot, so visual viewport behavior remains unverified. |
| XAMPP database migration state | **Passed** | `artisan migrate:status` reports all six migrations as ran against the local XAMPP MariaDB 10.4.32 database. |
| Isolated backup and restore rehearsal | **Passed** | `scripts\backup_restore_rehearsal.php` created a temporary dump, restored it to a uniquely named scratch database, compared migration, user, ticket, asset, and maintenance counts, then removed its scratch artifacts. |
| Report route registration | **Passed** | `artisan route:list --path=reports` lists 12 ticket, asset, maintenance, and export routes. |

## Automated coverage

The feature suite exercises:

- Login, inactive-user rejection, login throttling, one-time password setup/reset links, and administrator route restrictions.
- User creation, editing, role reassignment, deactivation/reactivation, department CRUD and search, active department-head validation, and exact role-scoped dashboard totals.
- Role and permission enforcement, ticket visibility, private attachment downloads, internal-note visibility, and department-head constraints.
- Ticket submission and unique references, category activation, assignment, priority reasons, status history, resolution requirements, close/reopen behavior, and private attachment storage.
- Sequential ticket references, reassignment history, public replies, first-response SLA updates, internal notes, and ticket-scoped notification recipients.
- Business-calendar SLA calculations, pause/resume behavior, overdue escalation, reminder idempotency, and notification behavior.
- SLA response timing from public staff replies and progress statuses, paused-target reporting, and restart of the resolution deadline when a requester reopens a ticket.
- Database foreign keys, cross-category constraints, feedback ownership and rating range, model relations, and repeatable seed behavior without fake tickets.
- Live role-specific dashboards and knowledge-base publication visibility.
- Asset and maintenance workflows, feedback, and ticket, asset, and maintenance report filtering and CSV, PDF, and Excel exports.
- Asset edits with inactive legacy relationships, validation that prevents new inactive assignments, administrator-only audit review, and feedback permission enforcement.

## Findings and limits

- The automated suite uses SQLite. The local XAMPP database is MariaDB 10.4.32, where the migrations are present; this run did not execute the automated suite against MySQL 8. MySQL 8 staging remains required before deployment.
- The SLA report excludes paused targets from overdue evaluation. Staff-only notes do not count as requester responses; a visible staff reply or progress status records first response time.
- Browser-based visual checks at desktop, tablet, and phone widths were not completed. Chrome reused an existing browser session instead of producing an isolated headless screenshot, so no visual pass is claimed.
- The backup test is an isolated local rehearsal, not an operational backup schedule. Production still needs approved encrypted backups of both the database and private attachment storage, plus a documented restore drill.
- `composer audit` checks published dependency advisories; it is not a penetration test. No external security assessment, institutional privacy review, or university UAT was performed.
- Email delivery depends on configured mail credentials and was not verified against a production mail service. Scheduler and queue workers must be configured in the deployment environment.

## Acceptance checks still required

1. Run migrations and the complete suite on an approved MySQL 8 staging service.
2. Review authenticated pages at desktop, tablet, Android, and iPhone viewport sizes, including keyboard navigation and contrast.
3. Complete institutional UAT using approved non-production accounts and realistic workflows.
4. Verify configured email, queue, scheduler, production HTTPS, private file permissions, and encrypted backup/restore procedures.
5. Complete the university's security, retention, privacy notice, and RA 10173 review before entering real personal information.
