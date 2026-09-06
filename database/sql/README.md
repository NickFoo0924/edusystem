# SQL scripts

Two scripts that build the LearnSync database from nothing, for the assignment
submission requirement "the complete SQL scripts for creating the database and
tables, as well as populating the database tables with initial data".

| File | What it does |
|---|---|
| `01_create_database.sql` | Creates the `edusystem` database and all 44 tables with their primary keys, foreign keys, unique constraints and indexes |
| `02_seed_data.sql` | Inserts the full demonstration data set: 65 users, 35 permissions, 6 courses, 6 quizzes, 76 certificates, 279 grades and the supporting records |

## Running them

From a terminal with MySQL on the path, or using the XAMPP copy at
`C:\xampp\mysql\bin\mysql.exe`:

    mysql -u root -p < 01_create_database.sql
    mysql -u root -p edusystem < 02_seed_data.sql

Or import both through phpMyAdmin in that order.

## Notes

`01_create_database.sql` drops each table before creating it, so running it
against an existing `edusystem` database replaces the schema and its contents.

The scripts are the SQL equivalent of the Laravel migrations and seeders in
`database/migrations/` and `database/seeders/`. Either route produces the same
schema. A developer working on the code would normally run:

    php artisan migrate:fresh --seed

The runtime tables `cache`, `cache_locks`, `sessions`, `jobs`, `job_batches`,
`failed_jobs` and `password_reset_tokens` are created by the first script but
left empty by the second. They hold no initial data and the application
repopulates them as it runs.

Passwords are stored as bcrypt hashes. The demonstration account credentials
are listed in `docs/README.md`.
