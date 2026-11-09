# UMIS-Helpdesk — User Acceptance Testing worksheet

**Environment:** Local test database only  
**Build/version:** __________________  
**Tester / role:** __________________  
**Date:** __________________

Use only seeded `@demo.university.test` accounts and invented request details. Do not use actual student, employee, health, account credential, or other sensitive information in local acceptance testing.

| ID | Scenario | Steps | Expected result | Pass / fail | Notes |
|---|---|---|---|---|---|
| UAT-01 | Sign in | Sign in as each demo role; sign out | Active account reaches dashboard; session ends on sign out | | |
| UAT-02 | Invalid sign in | Submit wrong password repeatedly | Generic credential error; attempts are rate limited; failed sign-in event is recorded | | |
| UAT-03 | Department access | Open Departments as admin, head, technician, employee | Admin can manage departments; other roles receive 403 | | |
| UAT-04 | Submit request | As employee, submit a complete ticket with an allowed attachment | Unique `MIS-YYYY-00001` format, Open status, SLA dates, and history are recorded | | |
| UAT-05 | Required fields | Submit without category, subject, or description; use a disallowed file | Validation prevents submission and explains the fields to correct | | |
| UAT-06 | Ticket visibility | Try opening another user's ticket as employee; open as assigned technician | Requester is denied; assigned technician can read and update the ticket | | |
| UAT-07 | Assignment | As MIS head assign/reassign a ticket | Current assignment changes; prior assignment is retained with end time | | |
| UAT-08 | Internal note | Add an internal note as MIS; view as requester | MIS can see the note; requester cannot see it or download its attachment | | |
| UAT-09 | Resolution guard | Attempt to resolve without details, then with details | Empty/short resolution is rejected; valid resolution records timestamp and history | | |
| UAT-10 | Close and reopen | Requester confirms a resolved ticket, then reopens within policy | Closed and reopened events are recorded; reopen window is enforced | | |
| UAT-11 | Dashboard | Compare ticket counts and recent items to database records per role | Only visible records are counted; empty states are shown for no data | | |
| UAT-12 | Small screen | Check login, dashboard, ticket form, ticket detail at a 375 px viewport | Navigation works; forms and tables remain usable without page-level horizontal scroll | | |
| UAT-13 | Attachment protection | Request an attachment URL while signed out and as an unrelated user | Authentication and ticket-level authorization prevent access | | |
| UAT-14 | MySQL restore rehearsal | Back up the test database, restore to a fresh local test DB, run `migrate:status` | Records and schema restore without altering the source database | | |
| UAT-15 | Password reset | Request a link, reset to a strong password, then reuse the link | Mail link is delivered, password changes, and used/expired links are rejected | | |
| UAT-16 | CSV account import | Import valid and invalid files using the supplied template | Valid accounts receive setup links; invalid or duplicate rows prevent import and identify row errors | | |
| UAT-17 | SLA pause and reminders | Place a ticket in configured Pending User state, wait for reminder, resume it | Deadline shifts by paused working minutes; user and supervisor alerts do not duplicate | | |
| UAT-18 | Report downloads | Apply filters and download CSV, Excel, and PDF | Each file contains the filtered records; CSV formula-like values are treated as text | | |
| UAT-19 | Asset reporting | Filter by department, category, condition, and status; download CSV, Excel, and PDF | The table and each download contain only matching inventory records | | |
| UAT-20 | Maintenance reporting | Filter by schedule dates, asset, technician, department, category, and status; download CSV, Excel, and PDF | The table and each download contain only matching schedules and inspection records | | |

## Sign-off

Known defects / blockers:  
____________________________________________________________________________

MIS representative: __________________  Date: __________  
Data Protection Officer / delegate: __________________  Date: __________  
Disposition: ☐ Accept for next test phase  ☐ Reject pending fixes
