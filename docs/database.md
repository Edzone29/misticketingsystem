# UMIS-Helpdesk database reference

The migrations in `database/migrations` are the authoritative schema. The full Mermaid ERD is in [architecture.md](architecture.md#domain-model). The schema uses InnoDB and `utf8mb4` on MySQL-compatible databases, unsigned bigint primary/foreign keys for domain records, timestamps on mutable records, and soft deletion for users' referenced organizational records where appropriate.

## Table inventory

| Module | Tables | Key rules |
|---|---|---|
| Identity and access | `users`, `roles`, `permissions`, `role_permissions` | Unique email, nullable unique employee number, unique role/permission names, composite role-permission primary key. User roles are restricted from deletion while referenced. |
| Organization | `departments` | Unique code and name; optional department head; personnel, ticket, and asset references become null if a department is removed. Departments are soft deleted. |
| Ticket classification | `ticket_categories`, `ticket_subcategories`, `ticket_priorities`, `sla_policies` | Unique category and priority names; subcategory name unique within category; one SLA policy per priority. A composite ticket foreign key guarantees the selected subcategory belongs to the selected category. Categories are soft deleted and referenced ticket categories are restrict-on-delete. |
| Ticket workflow | `ticket_number_counters`, `tickets`, `ticket_assignments`, `ticket_comments`, `ticket_attachments`, `ticket_status_history` | Ticket number unique; per-year counter primary key; assignment and status history are separate records; tickets are soft deleted. Indexes support status/priority, department/date, requester/date, technician/current-assignment, and ticket history lookups. |
| Service levels | `ticket_sla_records` | One SLA record per ticket; response/resolution deadlines, met times, pause start and cumulative paused business minutes are stored. |
| Inventory and maintenance | `asset_categories`, `assets`, `ticket_assets`, `maintenance_schedules`, `maintenance_records` | Unique asset tag and optional unique serial number; ticket/asset link has a composite primary key; one maintenance result per schedule; asset/category deletion is restricted where history depends on it. |
| Self service | `knowledge_base_articles`, `feedback` | Unique article slug; one feedback row per ticket; composite feedback foreign key guarantees only the ticket requester can rate it; database rating constraint enforces 1-5. |
| Governance and calendar | `holidays`, `system_settings`, `audit_logs`, `notifications` | Unique holiday date; settings use a string key primary key; audit metadata is JSON; framework notifications have deterministic UUID primary keys. |
| Laravel infrastructure | `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens`, `sessions` | Created by Laravel framework migrations and consumed by the configured framework drivers. |

## Referential actions

* Ticket requester, ticket category, ticket priority, assignment users, comment authors, attachment uploaders, status-change actors, article authors, and feedback users use `RESTRICT` where deleting the parent would erase accountability or invalidate retained records.
* Ticket-owned assignments, comments, attachments, status history, SLA rows, feedback, and ticket-asset links cascade only when a ticket is force deleted. Normal ticket operations use soft deletion and preserve those rows.
* Optional associations such as department, assigned asset user, ticket subcategory, attachment comment, maintenance assignee, maintenance schedule link, maintenance technician, audit actor, and department head use `SET NULL` where the historical child can remain meaningful without its former parent.
* Deleting a ticket category is restricted while tickets refer to it. Its subcategories cascade with the category. A subcategory referenced by a ticket cannot be hard deleted because the composite category-consistency constraint restricts deletion; deactivate it instead.
* `notifications.notifiable_type/notifiable_id` and `audit_logs.subject_type/subject_id` are intentionally polymorphic. A single SQL foreign key cannot target multiple tables; access is mediated through Laravel's notification relation or the audit log's `subject()` morph relation and application authorization.

## Model and factory map

Every application domain model has Eloquent relationships for its foreign keys and factory support. Pivot associations use Eloquent `belongsToMany` relations rather than standalone pivot factories. `TicketNumberCounter` represents the yearly sequence table; framework-owned session, queue, cache, password token, and notification models are provided by Laravel.

Factories exist for users, roles, permissions, departments, ticket categories/subcategories/priorities, SLA policies/records, ticket workflow/history records, asset categories/assets, maintenance schedules/records, knowledge articles, feedback, holidays, audit logs, settings, and yearly ticket counters. Factory states include inactive users/departments, technician/head/admin users, open tickets, internal comments, ended assignments, published articles, retired assets, and schedule-linked maintenance results.

Generate local records with:

```powershell
php artisan migrate --seed
```

The idempotent `DatabaseSeeder` seeds the five supported roles and permission grants, standard ticket categories and priority/SLA targets, asset categories, configurable calendar defaults, and five clearly labeled `@demo.university.test` accounts. It does not seed tickets or invented operational statistics. Demo credentials are documented in the root README and are for local evaluation only.

## Constraint verification

The `DatabaseIntegrityTest` suite verifies Eloquent relationships/factory graphs, foreign-key rejection, unique maintenance result per schedule, rating range enforcement, category/subcategory consistency, requester-only feedback, seeder idempotence, and absence of seeded tickets. The hardening migration applies a rating check constraint on MySQL/MariaDB and equivalent SQLite triggers for tests. It also expands ticket-number storage to 32 characters and adds schedule-result uniqueness. A follow-up migration adds composite foreign keys for ticket category/subcategory and feedback/requester pairs. Before applying these migrations to an existing production dataset, verify all historical rows satisfy those pair relationships.

Run checks with `php artisan migrate:status` and `php artisan test`. Do not use `migrate:fresh` or `db:wipe` on a database containing records. Use a disposable test database for destructive migration rehearsals.
