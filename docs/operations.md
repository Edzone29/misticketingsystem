# Local backup and restore operations

## Development rehearsal

Run from the project root with the XAMPP PHP CLI:

```powershell
C:\xampp\php\php.exe scripts\backup_restore_rehearsal.php
```

The rehearsal reads the configured MySQL-compatible connection, writes a short-lived SQL dump in the operating system's temporary directory, restores it into a uniquely named `umis_restore_test_*` schema, and compares migration, user, ticket, asset, and maintenance-record counts. It then drops only that generated scratch schema and deletes the temporary dump. It does not alter the configured source schema. It requires the XAMPP `mysql` and `mysqldump` clients at `C:\xampp\mysql\bin`.

The rehearsal was executed against the local XAMPP MariaDB database during development. It is a restore check, not a scheduled backup service, encryption system, or production disaster-recovery plan.

## Production backup requirements

Before real university records are collected, the university's MIS and Data Protection Officer should approve the backup owner, schedule, encryption, storage region, access list, retention period, recovery time objective, and incident procedure under institutional policy and RA 10173.

Production operations must:

1. Use the hosting provider's encrypted database backup or an approved encrypted `mysqldump` workflow. Do not place plaintext dumps in the project, web root, source control, or a broadly shared folder.
2. Store database backups in restricted storage separate from the application host. Keep credentials in the hosting secret store and request a password interactively when using a local client; do not put passwords in shell history or command arguments.
3. Back up private attachments from `storage/app/private` or the configured private object-storage bucket. A database dump does not contain those files.
4. Keep retention and deletion intervals aligned with approved university policy. Limit restore access to designated MIS administrators.
5. Restore recent backups into an isolated staging database and private file-storage location. Verify migrations, record counts, attachment reads, and application behavior before declaring recovery successful. Never restore over a live database during a rehearsal.
6. Record rehearsal date, backup identifier, source and restore versions, verification results, operator, and follow-up actions without copying personal ticket content into the log.

An example interactive MySQL-compatible dump command is shown below. Run it only after choosing a protected backup directory and an institutional encryption workflow; `-p` prompts for the database password without placing it in the command line.

```powershell
mysqldump --host=<approved-host> --port=3306 --user=<backup-user> --password --single-transaction --routines --triggers --default-character-set=utf8mb4 <database-name> --result-file=<protected-backup-path>
```

The tested local rehearsal uses MariaDB 10.4.32. A MySQL 8 staging restore is still required before organizational acceptance.
