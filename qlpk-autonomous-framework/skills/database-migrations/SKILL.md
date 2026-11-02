# Skill: MySQL Schema and Laravel Migrations

## Project context
The user reports an existing MySQL database `DBQLPK` using `utf8mb4_unicode_ci`; Laravel connection was verified with `php artisan db:show`. No application migrations have been run yet for the clinic schema. Existing default Laravel migrations may be present. Verify actual database and migration table state before any operation.

## Rules
1. Do not assume an empty database just because application tables have not been created.
2. Never run `migrate:fresh`, `db:wipe`, table drops, destructive alters or bulk deletes without explicit, informed approval and a verified backup.
3. Before migration, inspect current schema, migration status, target environment and affected rows/tables.
4. Prefer additive, reversible migrations. For large tables, assess lock time, index creation and deployment sequence.
5. Use foreign keys and indexes based on actual access patterns and integrity requirements; avoid duplicate/unnecessary indexes.
6. Use transactions for atomic business operations and understand MySQL engine/DDL transaction limitations.
7. Handle uniqueness and concurrent writes at database level as well as application level.
8. Use appropriate types: integer IDs where consistent, `DECIMAL` for money, explicit date/time semantics, bounded strings and correct nullable constraints.
9. Preserve utf8mb4 compatibility. Do not change database charset/collation globally without reviewing impact.
10. Never use real patient data in development fixtures or migration tests.
11. Run migration tests against a disposable test database, not the user's DBQLPK production-like database.
12. Before applying a migration, show the exact migration plan, expected impact and rollback limitations; request approval for destructive or risky changes.
