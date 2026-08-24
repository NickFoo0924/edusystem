# LearnSync — setup and operation

Everything needed to install, run, reset and troubleshoot the system. For what
LearnSync *is* — the modules, the design patterns, the roles — see
[`README.md`](README.md).

---

## Contents

1. [Quick start](#1-quick-start)
2. [What you need before starting](#2-what-you-need-before-starting)
3. [Setting the system up](#3-setting-the-system-up)
4. [Running it](#4-running-it)
5. [Moving it to another device](#5-moving-it-to-another-device)
6. [Running the tests](#6-running-the-tests)
7. [Troubleshooting](#7-troubleshooting)
8. [Building this from scratch](#8-building-this-from-scratch)

---

## 1. Quick start

If PHP, Composer, Node and XAMPP's MySQL are already working on your machine, this is the whole
thing. Start MySQL in the **XAMPP Control Panel** first, then open a terminal in the project folder
and run these in order:

```bash
cd C:/xampp/htdocs/edusystem
```

```bash
composer install
```

```bash
npm install && npm run build
```

```bash
copy .env.example .env
```

```bash
php artisan key:generate
```

Create an empty database named `edusystem` — either in **http://localhost/phpmyadmin** (**New** →
name it `edusystem` → collation `utf8mb4_unicode_ci` → **Create**) or from the terminal:

```bash
C:\xampp\mysql\bin\mysql -u root -e "CREATE DATABASE edusystem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

Then build the tables, load the demo data, and start the server:

```bash
php artisan migrate:fresh --seed
```

```bash
php artisan storage:link
```

```bash
php artisan serve
```

Open **http://localhost:8000** and sign in as `learnsync.admin@gmail.com` with the password
`password`. Every account uses that same password — the full list is in
[the account list](README.md#login-accounts).

That gives you the 12-account demo. For the full 50-student cohort, the populated calendar and the
reminder notifications, run the two extra commands in
[Restoring the full demo data](#restoring-the-full-demo-data).

Two things that are easy to miss, because they are the only real prerequisites beyond Laravel
itself:

- **The GD and XSL extensions must be enabled** in `C:\xampp\php\php.ini`. Without GD,
  `composer install` stops with *"requires ext-gd"*; without XSL the analytics chart is skipped. See [section 2](#2-what-you-need-before-starting).
- **Apache is not needed.** `php artisan serve` is the web server; XAMPP is only there for MySQL.

### Already ran the setup before?

Nothing above is repeated on later days. To use the system again you need exactly two things:

1. Start **MySQL** in the XAMPP Control Panel.
2. Run `php artisan serve` in the project folder.

Then open **http://localhost:8000**. Press `Ctrl + C` in that terminal to stop the server.

Run `npm run build` again only after changing a Blade template or CSS file, and
`php artisan migrate:fresh --seed` only when you want to wipe the data back to a clean demo state.

The rest of this README explains each of these steps in full, plus what to do when one of them
fails — start at [section 2](#2-what-you-need-before-starting) if anything above did not work.

---

## 2. What you need before starting

| Tool | Version | Where to get it |
|---|---|---|
| **XAMPP** (Apache not needed, MySQL/MariaDB is) | 8.2+ | https://www.apachefriends.org |
| **PHP** | 8.2 or newer | ships with XAMPP |
| **Composer** | 2.x | https://getcomposer.org/download/ |
| **Node.js + npm** | 18+ | https://nodejs.org |

Check everything is installed by opening a terminal and running:

```bash
php -v && composer -V && node -v
```

If any command is not recognised, install that tool and reopen the terminal.

### Two PHP extensions must be enabled

The QR code library needs **GD**. Open `C:\xampp\php\php.ini`, find this line:

```
;extension=gd
```

Remove the leading semicolon so it reads `extension=gd`, save, and confirm with:

```bash
php -m | findstr gd
```

It should print `gd`. Without this, step 3.3 fails with *"requires ext-gd"*.

The analytics chart needs **XSL**. In the same file, find:

```
;extension=xsl
```

Remove the leading semicolon so it reads `extension=xsl`, save, and confirm with:

```bash
php -m | findstr xsl
```

Without it the analytics page still works, but the completion-trend chart is skipped and a warning
is written to `storage/logs/laravel.log`. The chart is produced by an XSLT transformation, which
needs this extension.

---

## 3. Setting the system up

### 3.1 Put the project in place

The project belongs in XAMPP's web root. If you were given the folder, copy it to
`C:\xampp\htdocs\edusystem`. Then open a terminal **inside that folder** — every command below
assumes you are there:

```bash
cd C:/xampp/htdocs/edusystem
```

### 3.2 Install the PHP dependencies

```bash
composer install
```

### 3.3 Install and build the frontend

```bash
npm install
```

```bash
npm run build
```

`npm run build` compiles Tailwind CSS and Chart.js once. If the site later looks like unstyled
black text on white, this step did not finish — run it again.

### 3.4 Create the environment file

```bash
copy .env.example .env
```

```bash
php artisan key:generate
```

### 3.5 Start MySQL and create the database

Open the **XAMPP Control Panel** and press **Start** next to **MySQL**. Apache is not required —
Laravel serves the site itself.

Then open **http://localhost/phpmyadmin**, click **New** in the left sidebar, enter the database
name `edusystem`, choose collation `utf8mb4_unicode_ci`, and press **Create**.

### 3.6 Check the database settings

Open `.env` and confirm these lines. They are already correct for a default XAMPP install:

```
APP_NAME=LearnSync
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=edusystem
DB_USERNAME=root
DB_PASSWORD=
```

`DB_PASSWORD` is deliberately empty — that is XAMPP's default root account.

### 3.7 Create the tables and demo data

```bash
php artisan migrate:fresh --seed
```

You should see a list of migrations ending with:

```
Seeded 12 users, 6 courses, 35 permissions, 5 badges.
Log in as learnsync.admin@gmail.com / password
```

### 3.8 Link the storage folder

Uploaded avatars and badge icons are served from here:

```bash
php artisan storage:link
```

---

## 4. Running it

```bash
php artisan serve
```

Leave that terminal open — it is the web server. Then visit:

**http://localhost:8000**

To stop it, press `Ctrl + C` in that terminal. To use the system again later you only need to
(1) start MySQL in XAMPP and (2) run `php artisan serve`. The setup steps are one-time.

> **Resetting.** `php artisan migrate:fresh --seed` wipes everything and rebuilds the 12-account
> demo. Use it whenever you want a clean slate before a presentation — but note it does **not**
> bring back the 50-student cohort. To restore all of the data, see
> [Restoring the full demo data](#restoring-the-full-demo-data) immediately below.

### Restoring the full demo data

**These three commands rebuild everything from empty.** Run them in this order — the whole thing
takes about a minute, most of it spent rendering a real PDF for every certificate earned:

```bash
php artisan migrate:fresh --seed
```

```bash
php artisan db:seed --class=BulkStudentSeeder
```

```bash
php artisan reminders:send
```

> **`migrate:fresh` drops every table first.** That is how it gives you a clean slate, and also how
> a cohort gets lost: running it on its own rebuilds the 12-account demo and *not* the 50 students.
> If you want them back, run all three.

**What each one does:**

| Command | What it puts in |
|---|---|
| `migrate:fresh --seed` | the 12 named accounts, 6 courses, the 35-key permission matrix, badge rules, certificate templates, and a small amount of demo content |
| `db:seed --class=BulkStudentSeeder` | the 50-student cohort and a term of history, plus the calendar, invitations and announcement threads |
| `reminders:send` | the reminder notifications for whatever is currently imminent |

The cohort adds roughly this much -- the figures move by a few percent on each reseed, because
the course-selection draw uses PHP's CSPRNG rather than the seeded generator:

- **50 students**, `student1` … `student50`, emails `student1@gmail.com` … `student50@gmail.com`,
  same password
- a quiz and two assignments for every course
- around **160 enrolments**, **110 quiz attempts**, **210 submissions**, **260 grades**,
  **105 forum posts** with **50 replies**, and **79 certificates** with **99 badges** awarded
- **113 calendar events** — a weekly lecture slot for every course running from the start of term
  to three weeks ahead, online consultations carrying meeting links, and two institution-wide
  events from the administrator
- **8 announcements with 27 comments**, and **15 pending course invitations**

Nothing is fabricated. Quiz answers are marked by the real grading Strategy, submissions move
through the real State pattern — including the demo ones, which are created as drafts and then
submitted through the state object rather than by writing the column — and each grade wakes the
CredentialAuthority exactly as a live one would. So the progress curves, badges and certificates
are genuine consequences of the data. History is generated on a simulated clock spanning a 14-week
term, which is why the dashboard chart has a real shape and the activity log reads like a term's
use.

Each student has a hidden ability score, so marks cluster like a real cohort rather than spreading
evenly, and roughly one submission in eight is left as an unsubmitted draft — the case the
**Due soon** panel exists to catch.

### Seeing the reminders

The cohort deliberately includes three things that are **about to happen**, because otherwise the
reminder feature looks broken when it is merely idle: a revision session starting within the hour,
an assignment due tomorrow that nobody has submitted, and one that closed three hours ago with
submissions waiting. `php artisan reminders:send` turns those into about **56 notifications**.

Sign in as **`serenalim@gmail.com`** to see the result — an unread count on the bell, pending
invitations on her Courses page, and entries across the calendar.

Re-running `reminders:send` is safe and sends nothing the second time: each reminder carries a
reference, so nobody is told the same thing twice. For reminders to appear on their own as time
passes, leave a scheduler running in a spare terminal:

```bash
php artisan schedule:work
```

---

## 5. Moving it to another device

The project folder is not the whole system, which is the one thing that catches people out. Three
parts of a working install live outside the folder or are deliberately excluded from it, so copying
`edusystem` across on its own gives you a site that will not boot.

| Part | Travels with the folder? | What to do on the new device |
|---|---|---|
| Source code (`app/`, `routes/`, `resources/`, `database/`) | ✅ yes | nothing |
| `vendor/` — PHP packages | ⚠️ don't bother | `composer install` rebuilds it |
| `node_modules/` + `public/build` — compiled CSS/JS | ⚠️ don't bother | `npm install && npm run build` |
| `.env` — your config and `APP_KEY` | ❌ **no**, it is gitignored | recreate it: `copy .env.example .env` then `php artisan key:generate` |
| **The database** | ❌ **no** — it lives in MySQL, not in the folder | re-seed it, or export/import — see below |
| Uploaded avatars and badge icons | only if you **copy the folder**; ❌ not via git | re-seeded, or copy `storage/app/public/` by hand |

### Step 1 — Get the code onto the new device

**If you are copying the folder** (USB stick, zip, OneDrive, Google Drive) — delete `vendor` and
`node_modules` before you zip it. They are several hundred megabytes of files that are rebuilt from
`composer.lock` and `package-lock.json` in a minute anyway, and copying them across machines is a
common cause of odd errors. Put the folder at `C:\xampp\htdocs\edusystem` on the new device.

**If you are using git** — this repository has **no remote configured yet**, so there is nothing to
clone from until you add one:

```bash
git remote add origin https://github.com/<your-username>/edusystem.git
```

```bash
git push -u origin master
```

Then on the other device:

```bash
git clone https://github.com/<your-username>/edusystem.git C:/xampp/htdocs/edusystem
```

Remember that git will **not** bring `.env`, the database, or any uploaded avatars — `.gitignore`
excludes all three on purpose, because they are either secret or machine-specific.

### Step 2 — Install the prerequisites on the new device

The new machine still needs **XAMPP (for MySQL)**, **PHP 8.2+**, **Composer** and **Node 18+**, and
still needs **GD enabled** in its own `php.ini`. That checklist is
[section 2](#2-what-you-need-before-starting) — none of it transfers with the project.

### Step 3 — Rebuild and run

Exactly the commands from [section 1](#1-quick-start): `composer
install`, `npm install && npm run build`, create `.env`, `php artisan key:generate`, create the
empty `edusystem` database, `php artisan migrate:fresh --seed`, `php artisan storage:link`, then
`php artisan serve`.

### The database: fresh demo data, or your actual data?

`migrate:fresh --seed` gives the new device the standard demo — 12 accounts, 6 courses, all the
walkthrough content. **For a presentation on a lab machine this is what you want**, and you can
skip the rest of this section.

If instead you want to carry across work you actually did — courses you created, submissions,
issued certificates — move the database itself:

1. On the old device, open **http://localhost/phpmyadmin**, select `edusystem`, go to **Export**,
   leave the format as **SQL**, and press **Export**. You get an `edusystem.sql` file.
2. On the new device, create the empty `edusystem` database as usual, then select it, go to
   **Import**, choose that `.sql` file, and press **Import**.
3. Run `php artisan migrate` (not `migrate:fresh` — that would wipe what you just imported) to
   apply any migrations added since the export.

Copy `storage/app/public/` across too, or the avatars in the imported data will render as broken
images.

> **A new `APP_KEY` does not break your certificates.** The integrity hash is
> `SHA-256(student_id | course_id | score | issued_at | credential_id)` — it never touches the app
> key, so imported certificates still verify as **VALID** on a machine with a freshly generated key.
> Regenerating the key only invalidates existing sessions and cookies, which just means everyone
> signs in again.

### If the new device uses a different address

`.env`'s `APP_URL` matters if you are not on `http://localhost:8000` — for example when you run
`php artisan serve --host=0.0.0.0 --port=8000` so another machine on the same Wi-Fi can reach it.
Set `APP_URL` to the address people actually type.

The certificate QR code is the reason. It encodes the verification URL, built by Laravel's `route()`
helper, which uses **the address of the request that minted the PDF** — so a certificate created by
a lecturer marking work is correct for whatever host they were on. But certificates generated by the
seeder are built from the command line, where there is no request, and `route()` falls back to
`APP_URL`. Leave `APP_URL` at `http://localhost` and every seeded certificate's QR code points at
the lab machine's own localhost, which a phone cannot reach.

---

## 6. Running the tests

```bash
php artisan test
```

Expected: **29 passed**. The tests run against an in-memory SQLite database and never touch your
`edusystem` data.

---

## 7. Troubleshooting

| Symptom | Cause and fix |
|---|---|
| `SQLSTATE[HY000] [2002]` on any page | MySQL is not running. Start it in the XAMPP Control Panel. |
| `could not find driver` | `extension=pdo_mysql` is commented out in `C:\xampp\php\php.ini`. |
| Composer refuses: *requires ext-gd* | GD is not enabled — see [section 2](#2-what-you-need-before-starting). |
| Site loads but is completely unstyled | `npm run build` did not finish. Run it again. |
| `No application encryption key` | Run `php artisan key:generate`. |
| Avatars and badge icons are broken images | Run `php artisan storage:link`. |
| `/register` shows 404 | Correct — registration is invitation-only by design. |
| Locked out after failed logins | Five wrong passwords locks an account. Sign in as Admin → **Accounts** → **Unlock**. Only an administrator can clear it. |
| Certificate says TAMPERED | Its row was edited directly in the database. That is the integrity check doing its job. |
| Page 403s unexpectedly | That role lacks the permission. Check Admin → **Permissions**. |
| Changed a Blade file and nothing happened | Run `php artisan view:clear`. |
| The 50 students / calendar / cohort data have vanished | `migrate:fresh` drops every table, and `--seed` only rebuilds the 12-account demo. Re-run all three commands in [Restoring the full demo data](#restoring-the-full-demo-data). |
| Calendar reminders never arrive | Nothing is scheduling them. Run `php artisan schedule:work` in a spare terminal, or `php artisan reminders:send` once by hand. |
| A reminder arrived only once and not again | Correct. Each carries a reference so nobody is told the same thing twice. |
| A colour or badge renders with no background | Tailwind strips class names it cannot find in the source. If you build one in PHP rather than writing it in a template, make sure the file is covered by `content` in `tailwind.config.js` — `./app/**/*.php` is already listed — then `npm run build`. |
| The left rail is collapsed and will not stay open | The state is kept in `localStorage` under `learnsync.sidebar`. Clearing site data resets it, and it starts collapsed on narrow screens. |

---

## 8. Building this from scratch

Only needed if you are recreating the project rather than running the supplied one. These are the
commands from the BAIT3173 practical (Appendix 3.1), with `ordersystem` replaced by `edusystem`.

```bash
composer create-project laravel/laravel edusystem
```

```bash
cd C:/xampp/htdocs/edusystem
```

```bash
composer require laravel/breeze --dev
```

```bash
php artisan breeze:install blade
```

```bash
npm install && npm run build
```

Then the packages this project adds on top:

```bash
composer require barryvdh/laravel-dompdf simplesoftwareio/simple-qrcode intervention/image
```

```bash
npm install chart.js
```

Create the `edusystem` database in phpMyAdmin, set the `.env` block from
[section 3.6](#36-check-the-database-settings), and run:

```bash
php artisan migrate
```

```bash
php artisan serve
```

### Conventions used throughout

Following the practical's naming rules:

- Tables are **snake_case plural** — `course_materials`, `quiz_attempts`
- Models are **PascalCase singular** — `CourseMaterial`, `QuizAttempt`
- Controllers are **PascalCase singular ending in Controller** — `CourseMaterialController`
- Models are generated with `php artisan make:model X -m`
- Resource controllers with `php artisan make:controller XController --resource --model=X`
- Routes registered in `routes/web.php`, views in `resources/views` extending `layout.blade.php`
- Every model declares a `$fillable` array

---
