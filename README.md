# LearnSync

**Integrated Educational Resource and Collaborative Learning Portal**
BAIT3173 Integrative Programming · Laravel 12 · SDG 4 (Quality Education)

A learning management system where instructors publish materials, run quizzes and mark
coursework, and students earn **verifiable digital credentials** — certificates carrying a unique
credential ID and a QR code that anyone can check publicly, without an account, in the style of
Cisco NetAcad.

---

## Contents

0. [Quick start — Laravel is already installed](#0-quick-start--laravel-is-already-installed)
1. [What you need before starting](#1-what-you-need-before-starting)
2. [Setting the system up](#2-setting-the-system-up)
3. [Running it](#3-running-it)
3b. [Moving it to another device](#3b-moving-it-to-another-device)
4. [Login accounts](#4-login-accounts)
5. [Courses and who teaches them](#5-courses-and-who-teaches-them)
5b. [Finding your way around](#5b-finding-your-way-around)
6. [A 10-minute demo walkthrough](#6-a-10-minute-demo-walkthrough)
7. [The five modules and their design patterns](#7-the-five-modules-and-their-design-patterns)
8. [Where everything lives](#8-where-everything-lives)
9. [Roles and permissions](#9-roles-and-permissions)
10. [Grading scale](#10-grading-scale)
11. [Running the tests](#11-running-the-tests)
12. [Troubleshooting](#12-troubleshooting)
13. [Building this from scratch](#13-building-this-from-scratch)

---

## 0. Quick start — Laravel is already installed

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
[section 4](#4-login-accounts).

Two things that are easy to miss, because they are the only real prerequisites beyond Laravel
itself:

- **The GD extension must be enabled** in `C:\xampp\php\php.ini`, or `composer install` stops with
  *"requires ext-gd"*. See [section 1](#1-what-you-need-before-starting).
- **Apache is not needed.** `php artisan serve` is the web server; XAMPP is only there for MySQL.

### Already ran the setup before?

Nothing above is repeated on later days. To use the system again you need exactly two things:

1. Start **MySQL** in the XAMPP Control Panel.
2. Run `php artisan serve` in the project folder.

Then open **http://localhost:8000**. Press `Ctrl + C` in that terminal to stop the server.

Run `npm run build` again only after changing a Blade template or CSS file, and
`php artisan migrate:fresh --seed` only when you want to wipe the data back to a clean demo state.

The rest of this README explains each of these steps in full, plus what to do when one of them
fails — start at [section 1](#1-what-you-need-before-starting) if anything above did not work.

---

## 1. What you need before starting

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

### One PHP extension must be enabled

The QR code library needs **GD**. Open `C:\xampp\php\php.ini`, find this line:

```
;extension=gd
```

Remove the leading semicolon so it reads `extension=gd`, save, and confirm with:

```bash
php -m | findstr gd
```

It should print `gd`. Without this, step 2.3 fails with *"requires ext-gd"*.

---

## 2. Setting the system up

### 2.1 Put the project in place

The project belongs in XAMPP's web root. If you were given the folder, copy it to
`C:\xampp\htdocs\edusystem`. Then open a terminal **inside that folder** — every command below
assumes you are there:

```bash
cd C:/xampp/htdocs/edusystem
```

### 2.2 Install the PHP dependencies

```bash
composer install
```

### 2.3 Install and build the frontend

```bash
npm install
```

```bash
npm run build
```

`npm run build` compiles Tailwind CSS and Chart.js once. If the site later looks like unstyled
black text on white, this step did not finish — run it again.

### 2.4 Create the environment file

```bash
copy .env.example .env
```

```bash
php artisan key:generate
```

### 2.5 Start MySQL and create the database

Open the **XAMPP Control Panel** and press **Start** next to **MySQL**. Apache is not required —
Laravel serves the site itself.

Then open **http://localhost/phpmyadmin**, click **New** in the left sidebar, enter the database
name `edusystem`, choose collation `utf8mb4_unicode_ci`, and press **Create**.

### 2.6 Check the database settings

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

### 2.7 Create the tables and demo data

```bash
php artisan migrate:fresh --seed
```

You should see a list of migrations ending with:

```
Seeded 12 users, 6 courses, 33 permissions, 5 badges.
Log in as learnsync.admin@gmail.com / password
```

### 2.8 Link the storage folder

Uploaded avatars and badge icons are served from here:

```bash
php artisan storage:link
```

---

## 3. Running it

```bash
php artisan serve
```

Leave that terminal open — it is the web server. Then visit:

**http://localhost:8000**

To stop it, press `Ctrl + C` in that terminal. To use the system again later you only need to
(1) start MySQL in XAMPP and (2) run `php artisan serve`. The setup steps are one-time.

> **Resetting.** `php artisan migrate:fresh --seed` wipes everything and rebuilds the demo data.
> Use it whenever you want a clean slate before a presentation.

### Optional: a full cohort of 50 students

The default seed gives 12 accounts — enough to demonstrate every feature. For screens that only
look real with volume (class analytics, grade distributions, the activity log), load a simulated
term instead:

```bash
php artisan db:seed --class=BulkStudentSeeder
```

Run it **after** `migrate:fresh --seed`. It takes about 30 seconds, because it renders a real PDF
for every certificate earned. It adds:

- **50 students**, `student1` … `student50`, emails `student1@gmail.com` … `student50@gmail.com`,
  same password
- a quiz and two assignments for every course
- around **170 enrolments**, **120 quiz attempts**, **210 submissions**, **270 grades**,
  **120 forum posts** and **80 certificates**

Nothing is fabricated. Quiz answers are marked by the real grading Strategy, submissions move
through the real State pattern, and each grade wakes the CredentialAuthority exactly as a live one
would — so the progress curves, badges and certificates are genuine consequences of the data.
History is generated on a simulated clock spanning a 14-week term, which is why the dashboard
chart has a real shape and the activity log reads like a term's use.

Each student has a hidden ability score, so marks cluster like a real cohort rather than spreading
evenly, and roughly one submission in eight is left as an unsubmitted draft — the case the
**Due soon** panel exists to catch.

---

## 3b. Moving it to another device

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
[section 1](#1-what-you-need-before-starting) — none of it transfers with the project.

### Step 3 — Rebuild and run

Exactly the commands from [section 0](#0-quick-start--laravel-is-already-installed): `composer
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

## 4. Login accounts

**Every account uses the same password:**

```
password
```

### Administrator

| Name | Email |
|---|---|
| Admin | `learnsync.admin@gmail.com` |

### Lecturers

| Name | Sign-in email | School email (shown to students) | Teaches |
|---|---|---|---|
| Ting Hie Choon | `tinghiechoon@gmail.com` | `tinghc@tarc.edu.my` | BMCS3404 Project I |
| Fatima Ahmed Mohamed Abdalla | `fatima.abdalla@yahoo.com` | `fatimaama@tarc.edu.my` | BMSE3153 Software Project Management |
| Malarvili A/P Nallayan | `malarvili.nallayan@gmail.com` | `malarvili@tarc.edu.my` | BMIT3173 Integrative Programming |
| Lim Mei Shyan | `limmeishyan@outlook.com` | `limms@tarc.edu.my` | BMIT3123 Vulnerability Assessment and Penetration Testing |
| Wong Jee Fong | `wongjeefong@yahoo.com` | `wongjf@tarc.edu.my` | BMIT3113 Systems Administration |
| Jessie Teoh Poh Lin | `jessieteoh@gmail.com` | `jessietpl@tarc.edu.my` | BMIT3084 Enterprise Networking |

### Students — the project group

| Name | Email | Module owned |
|---|---|---|
| Serena Lim Sze Kee | `serenalim@gmail.com` | Module 1 — Identity, Access & Digital Credentialing |
| Foo Chong Xian | `foochongxian@gmail.com` | Module 2 — Academic Resources Repository |
| Ong Shun Yan | `ongshunyan@yahoo.com` | Module 3 — Student Forum & Notifications |
| Wong Siew Lam | `wongsiewlam@outlook.com` | Module 4 — Skill Assessment & Quiz |
| Ong Kwong Wei | `ongkwongwei@hotmail.com` | Module 5 — Academic Progress Analytics |

> **There is no Register button, and that is deliberate.** Public sign-up is disabled
> (EduSystem.md 1A). Visiting `/register` returns 404. New accounts exist only when an
> administrator issues an invitation — see the walkthrough below.

---

## 5. Courses and who teaches them

| Code | Course | Lecturer |
|---|---|---|
| `BMCS3404` | Project I | Ting Hie Choon |
| `BMSE3153` | Software Project Management | Fatima Ahmed Mohamed Abdalla |
| `BMIT3173` | Integrative Programming | Malarvili A/P Nallayan |
| `BMIT3123` | Vulnerability Assessment and Penetration Testing | Lim Mei Shyan |
| `BMIT3113` | Systems Administration | Wong Jee Fong |
| `BMIT3084` | Enterprise Networking | Jessie Teoh Poh Lin |

**BMIT3173** carries the demo content — materials, a quiz with both question types, an assignment
and a forum thread. All five students are enrolled in it.

There is also a **learning path**, "Network Infrastructure and Security Pathway":
`BMIT3084` → `BMIT3113` → `BMIT3123`. Finishing all three earns a higher-tier pathway certificate
on top of the individual ones.

---

## 5b. Finding your way around

Navigation lives in a **collapsible rail down the left**. The hamburger at the top-left folds it to
icons only and expands it again; the choice is remembered between pages. Items are grouped under
**Learning**, **Teaching** and **Administration**, and each one is gated on a permission — an
administrator sees twelve, a lecturer four, a student five.

The **top bar** holds only the menu toggle, the brand, the notification bell with its unread count,
your avatar and log out. Clicking your avatar or name opens your profile, where you can set a
display picture, a bio, and — if you teach — a school email and an optional phone number.

Anywhere a lecturer is named, their name is a link to a read-only contact card: school email
always, phone only if that lecturer chose to publish one.

---

## 6. A 10-minute demo walkthrough

This is the shortest route through every module. Use two browsers (or one normal and one
incognito window) so you can stay logged in as two people at once.

### Step 1 — Instructor publishes (Module 2, Adapter pattern)

Sign in as **Malarvili A/P Nallayan** → **Courses** → **BMIT3173** → **Add material**.
Choose a category, pick *Link to an external resource*, paste any YouTube URL, and save.

Materials are filed under four fixed headings — **Lecture notes**, **Tutorial question**,
**Practical question** and **Others** — and all four always appear with a count, so a student can
see at a glance that nothing has been posted under one rather than wondering whether it exists.

It appears in the same list as uploaded files, labelled **YouTube**. That is the Adapter at work:
a bare URL and a stored file present themselves through one interface, so the view never asks
which it is dealing with.

### Step 1b — Student finds how to contact their lecturer

On any course page, click the lecturer's name under the title. Their contact card opens: school
email always shown, phone only if that lecturer chose to publish one, plus the courses they teach.

The page is read-only — there is no edit route on it at all, so nothing a student can reach writes
to another user's record. Only lecturers have a card; requesting a student's or the administrator's
id returns 404.

### Step 2 — Student asks a question (Module 3, Observer pattern)

In the other browser, sign in as **Foo Chong Xian** → **Courses** → **BMIT3173** →
**Discussion forum** → post a question.

Switch back to the lecturer. **The bell in the top bar now shows a red unread badge.** Nothing in
the forum code mentions notifications — the Observer wrote that row when the post was saved.

### Step 3 — Student takes the quiz (Module 4, Strategy pattern)

As the student: **BMIT3173** → *Web Services and Integration Basics* → **Start quiz**.

Three question types, each marked by a completely different algorithm:

- **One answer** — exact match against the option marked correct
- **Several answers** — set comparison. The paper says *"Select exactly 2 answers"*, a live counter
  tracks how many you have ticked, and the extra boxes stop accepting clicks once you reach the
  limit. Submitting with the wrong number is refused, naming the questions at fault.
- **Fill in the blank** — similarity, not equality. Misspell **MVC** as `MVCC`, or write
  `Model View Controller`, and it is still accepted.

The controller never asks which type a question is; it asks the resolver for a strategy and calls
`grade()`. Adding the multiple-answer type needed no change to it at all.

### Step 4 — Student submits work (Module 5, State pattern)

**BMIT3173** → the assignment → upload any file → **Save draft**.

Notice the file can still be replaced. Now press **Submit for marking** — the upload control
disappears. The submission's state object, not the controller, decided that.

### Step 5 — Lecturer marks it, and a certificate mints itself (Module 1, Singleton)

Back as the lecturer: open the assignment, enter a score, press **Grade**.

Writing that grade wakes the `CredentialAuthority`, which recalculates progress, writes a chart
snapshot, evaluates every badge rule, and — once the student passes the threshold — mints a
certificate with a unique credential ID, an integrity hash and a QR-coded PDF.

As the student, open **Dashboard** in the left rail to see the progress chart move, **Trophy
cabinet** for any new badge, and **My certificates** to download the PDF.

### Step 6 — Anyone verifies it, with no account at all

Copy the credential ID (format `LS-2026-A7F3D9K2`) and open in a **private window**, logged out:

```
http://localhost:8000/verify/LS-2026-A7F3D9K2
```

It reports **VALID** with the holder, course, score and date. Or scan the QR code printed on the
PDF with a phone.

**The party trick:** open phpMyAdmin, edit that certificate's `final_score` by hand, and reload the
verification page. It now reports **TAMPERED** — the stored SHA-256 hash no longer matches the row.

### Step 7 — Admin controls the rules

Sign in as **Admin**:

- **Permissions** (Administration group in the left rail) — untick `certificate.view_own` for *student* and save. That student's
  Certificates menu vanishes and the page 403s. Tick it back. No code changed.
- **Credentials** — revoke a certificate with a reason. The public page immediately says
  **REVOKED** and the download is refused.
- **Invitations** — invite an email address; the tokenised link appears on screen (and is written to
  `storage/logs/laravel.log`, since mail is in log mode). Open it in a private window to register.
- **Accounts** — every action asks for *your own* password on a confirmation page first.
- **Badge rules** / **Learning paths** / **System settings** — the badge criteria, learning paths and progress weighting
  are all data, not code.

---

## 7. The five modules and their design patterns

Every pattern lives in `app/Patterns/`. **No pattern logic sits inside a controller.**

| # | Module | Owner | Pattern | Where |
|---|---|---|---|---|
| 1 | Identity, Access & Digital Credentialing | Serena Lim Sze Kee | **Singleton** (Creational) | `app/Patterns/Singleton/CredentialAuthority.php` |
| 2 | Academic Resources Repository | Foo Chong Xian | **Adapter** (Structural) | `app/Patterns/Adapter/` |
| 3 | Student Forum & Notifications | Ong Shun Yan | **Observer** (Behavioural) | `app/Patterns/Observer/SystemNotificationObserver.php` |
| 4 | Skill Assessment & Quiz | Wong Siew Lam | **Strategy** (Behavioural) | `app/Patterns/Strategy/` |
| 5 | Academic Progress Analytics | Ong Kwong Wei | **State** (Behavioural) | `app/Patterns/State/` |

**Why a Singleton for the credential authority.** It models a real certificate authority. Only one
may exist, because it is the sole issuer of credential IDs and the sole arbiter of whether a
student has already been credentialed for a course. Two concurrent instances — a grade event and a
manual admin issuance firing together — could mint duplicate IDs or issue two certificates for one
achievement, destroying the uniqueness that public verification depends on. It is enforced twice:
a private constructor with a static accessor, and `$this->app->singleton()` in
`CredentialServiceProvider`.

---

## 8. Where everything lives

```
app/
  Patterns/            all five design patterns, one folder each
  Http/Controllers/    one controller per feature area
  Models/              30 Eloquent models
  Support/GradeScale   the A-F letter grade scale
database/
  migrations/          41 tables
  seeders/             DatabaseSeeder builds the whole demo
docs/                  the specification, the practical PDF and the pattern catalogue
resources/views/       Blade templates, all extending layout.blade.php
routes/web.php         every route, grouped and commented by module
tests/                 29 automated tests
```

See **[`docs/README.md`](docs/README.md)** for how each section of the specification maps onto the
code, the schema deviations to carry into the ERD, and an honest list of known gaps.

All database access uses **Eloquent ORM**. There is no raw SQL anywhere — no `DB::raw()`,
no `DB::select()`.

---

## 9. Roles and permissions

Permissions are **rows in the database**, not hardcoded checks. Code asks
`$user->can('certificate.revoke')` and a Laravel Gate resolves it against the `permissions` and
`permission_role` tables. An administrator retunes the whole matrix from the **Permissions** screen
and it takes effect on the next request.

| | Administrator | Lecturer | Student |
|---|---|---|---|
| Create courses, materials, quizzes, assignments | — | ✅ | — |
| Mark submissions | — | ✅ | — |
| Enrol, take quizzes, submit work | — | — | ✅ |
| Own certificates, badges, progress | — | — | ✅ |
| Forum | — | ✅ | ✅ |
| Class analytics | ✅ | ✅ (own courses) | — |
| Invitations, accounts, permission matrix, activity log | ✅ | — | — |
| Badges, learning paths, templates, settings | ✅ | — | — |
| Issue / revoke certificates | ✅ | — | — |

Administrators deliberately **cannot** take quizzes, submit work or post in forums, and lecturers
cannot enrol as students — that is Section 7 of the specification, not an oversight.

---

## 10. Grading scale

Letter grades are derived from the percentage, never stored, so a mark and its letter can never
disagree.

| Letter | Marks | Point | | Letter | Marks | Point |
|---|---|---|---|---|---|---|
| A | 80 – 100 | 4.00 | | C | 50 – 54 | 2.00 |
| A− | 75 – 79 | 3.67 | | C− | 47 – 49 | 1.67 |
| B+ | 70 – 74 | 3.33 | | D+ | 44 – 46 | 1.33 |
| B | 65 – 69 | 3.00 | | D | 40 – 43 | 1.00 |
| B− | 60 – 64 | 2.67 | | F | below 40 | 0.00 |
| C+ | 55 – 59 | 2.33 | | | | |

The class analytics screen groups results into A / B / C / D / F families.

---

## 11. Running the tests

```bash
php artisan test
```

Expected: **29 passed**. The tests run against an in-memory SQLite database and never touch your
`edusystem` data.

---

## 12. Troubleshooting

| Symptom | Cause and fix |
|---|---|
| `SQLSTATE[HY000] [2002]` on any page | MySQL is not running. Start it in the XAMPP Control Panel. |
| `could not find driver` | `extension=pdo_mysql` is commented out in `C:\xampp\php\php.ini`. |
| Composer refuses: *requires ext-gd* | GD is not enabled — see [section 1](#1-what-you-need-before-starting). |
| Site loads but is completely unstyled | `npm run build` did not finish. Run it again. |
| `No application encryption key` | Run `php artisan key:generate`. |
| Avatars and badge icons are broken images | Run `php artisan storage:link`. |
| `/register` shows 404 | Correct — registration is invitation-only by design. |
| Locked out after failed logins | Five wrong passwords locks an account. Sign in as Admin → **Accounts** → **Unlock**. Only an administrator can clear it. |
| Certificate says TAMPERED | Its row was edited directly in the database. That is the integrity check doing its job. |
| Page 403s unexpectedly | That role lacks the permission. Check Admin → **Permissions**. |
| Changed a Blade file and nothing happened | Run `php artisan view:clear`. |
| A colour or badge renders with no background | Tailwind strips class names it cannot find in the source. If you build one in PHP rather than writing it in a template, make sure the file is covered by `content` in `tailwind.config.js` — `./app/**/*.php` is already listed — then `npm run build`. |
| The left rail is collapsed and will not stay open | The state is kept in `localStorage` under `learnsync.sidebar`. Clearing site data resets it, and it starts collapsed on narrow screens. |

---

## 13. Building this from scratch

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
[section 2.6](#26-check-the-database-settings), and run:

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

## Packages used

| Package | Purpose |
|---|---|
| `laravel/breeze` | authentication scaffolding |
| `barryvdh/laravel-dompdf` | renders certificate PDFs |
| `simplesoftwareio/simple-qrcode` | QR code embedded in every certificate |
| `intervention/image` | crops avatars and badge icons |
| `chart.js` | the student progress-over-time chart |
| Tailwind CSS | styling (installed by Breeze) |
