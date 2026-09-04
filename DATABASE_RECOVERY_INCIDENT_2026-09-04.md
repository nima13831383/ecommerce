# Development Database Recovery Incident — 2026-09-04

## Incident status

**DEVELOPMENT DATABASE RECOVERY: FAILED — NO RECOVERABLE SOURCE FOUND**

The development database was not restored. No fabricated or partial dataset was applied.

## Scope and safety posture

The affected database is the configured development database `ecommerce`. During this incident response:

* all active `php.exe` application/test processes were stopped;
* the MariaDB server was left running for read-only inspection;
* no Laravel application, test, migration, seeder, queue, scheduler, or demo command was run after the recovery hold was established;
* no `migrate:fresh`, `db:wipe`, `migrate:reset`, `migrate:refresh`, `DROP`, `TRUNCATE`, destructive `DELETE`, or schema reset command was executed;
* no restore or replacement of `ecommerce` was attempted.

The existing database-safety rule in `AGENTS.md` remains in force.

## Database identity

Evidence from the current Laravel configuration and server inspection:

| Field | Observed value |
|---|---|
| Connection | `mysql` |
| Host | `127.0.0.1` |
| Port | `3306` |
| Database | `ecommerce` |
| Server | MariaDB `10.4.32` |
| Application environment | `local` |
| Session driver | `database` |
| Queue connection | `database` |
| Cache store | `database` |

## Damage evidence

The `ecommerce` schema still exists, but the application data tables are empty. A read-only information-schema inventory showed `TABLE_ROWS = 0` for Products, Users, categories, brands, attributes, carts, orders, payments, shipments, posts, coupons, and the other commerce/application tables. Only bookkeeping tables retained rows (`migrations = 98`, `cache = 2`, `sessions = 1`).

The physical table files under `C:\xampp\mysql\data\ecommerce` were recreated/touched in a narrow window:

* user table file timestamp: approximately `2026-09-04 21:59:36` local time;
* application table files: approximately `2026-09-04 21:59:36` through `21:59:40`;
* InnoDB table files: approximately `2026-09-04 21:59:45` through `21:59:47`;
* `cache.ibd` and `sessions.ibd` were subsequently updated at approximately `22:07`.

This is consistent with a destructive reset/drop-and-recreate or equivalent data-clearing operation around `21:59` local time. The exact issuing command and actor could not be established from the available command/session history. Historical PowerShell/Codex logs contain destructive commands from earlier dates, but they do not provide reliable attribution for this incident.

## Forensic snapshot

A non-destructive snapshot of the damaged state was created before any recovery attempt:

`D:\uni-shop-project\db\ecommerce_damaged_after_accidental_reset_2026-09-04.sql`

Observed size: `130,614` bytes. Observed timestamp: `2026-09-04 22:13:31` local time. The snapshot is outside repository runtime directories and has not been overwritten.

## Recovery-source search

The following sources were inspected:

1. Project and parent directories: no complete pre-incident SQL/data backup was found. The existing `db\ecommerce_3.zip` and `db\ecommerce_4.zip` contain PDF/JPG schema/ER artifacts, not row data.
2. `C:\xampp\mysql\data_bkp`: no `ecommerce` database copy exists.
3. Downloads: `ecommerce.sql` and `ecommerce.sql.zip` are old phpMyAdmin schema exports. The SQL contains only a migrations insert; the split dump contains only schema/bookkeeping rows and one historical `users` row. It does not contain the Product, order, payment, inventory, shipping, blog, or other lost application data.
4. MariaDB binary logs: `log_bin = OFF`; `SHOW BINARY LOGS` returns MariaDB error 1381 (“not using binary logging”). Point-in-time recovery is therefore unavailable.
5. Laravel logs and available Codex/session history: no Sept. 4 destructive Laravel command identifying the reset was found. The full suite entries around the incident time used the isolated PHPUnit SQLite configuration (`APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`), not `ecommerce`.

The available July schema/partial dump was intentionally **not** applied: it cannot reconstruct the missing development data and applying it would risk changing the damaged database without a verified recovery source.

## Recovery attempt and validation

No temporary recovery database was created because no complete recoverable source was found. Consequently, no candidate restore could be validated and no replacement of `ecommerce` was performed.

The current damaged database remains the only live database state. It is structurally present but missing the previously stored application rows. The following data is considered unrecoverable from the sources found: Products and variants, users beyond the partial historical export, taxonomy, attributes/options, carts, coupons/usages, addresses, inventory transactions/reservations, orders/items/history, payments/transactions, shipments/history, settings, posts/tags/categories, and other application records.

## Permanent guard changes

No new production-code guard was added during this failed recovery attempt. Adding a recovery-specific guard after an unsuccessful restore would not recover data and could alter unrelated behavior. The repository’s existing permanent database-safety rule remains the governing safeguard.

## Commands/actions performed during response

* Read the complete root `AGENTS.md` and the recovery instructions.
* Stopped active PHP processes to halt possible application/test writes; MariaDB was not stopped.
* Read `.env`, `phpunit.xml`, database metadata, table counts, file timestamps, logs, and available local history.
* Checked MariaDB binary-log configuration (read-only).
* Created the forensic `mysqldump` snapshot listed above.
* Searched local project, backup, Downloads, and MySQL backup locations for a usable full backup.

No destructive database command was executed.

## Final assessment

The database schema is present, but the valuable pre-incident application data cannot be safely reconstructed from the available evidence. The correct safe outcome is to preserve the damaged snapshot, document the loss, and stop rather than manufacture replacement records or apply an incomplete dump.
