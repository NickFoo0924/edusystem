# LearnSync

**Integrated Educational Resource and Collaborative Learning Portal**
BAIT3173 Integrative Programming · Laravel 12 · SDG 4 (Quality Education)

A learning management system where instructors publish materials, run quizzes and mark
coursework, and students earn **verifiable digital credentials** — certificates carrying a unique
credential ID and a QR code that anyone can check publicly, without an account, in the style of
Cisco NetAcad.

---

## Contents

1. [What you need before starting](#1-what-you-need-before-starting)
2. [Setting the system up](#2-setting-the-system-up)
3. [Running it](#3-running-it)
4. [Login accounts](#4-login-accounts)
5. [Courses and who teaches them](#5-courses-and-who-teaches-them)
6. [A 10-minute demo walkthrough](#6-a-10-minute-demo-walkthrough)
7. [The five modules and their design patterns](#7-the-five-modules-and-their-design-patterns)
8. [Where everything lives](#8-where-everything-lives)
9. [Roles and permissions](#9-roles-and-permissions)
10. [Grading scale](#10-grading-scale)
11. [Running the tests](#11-running-the-tests)
12. [Troubleshooting](#12-troubleshooting)
13. [Building this from scratch](#13-building-this-from-scratch)

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

## 6. A 10-minute demo walkthrough

This is the shortest route through every module. Use two browsers (or one normal and one
incognito window) so you can stay logged in as two people at once.

### Step 1 — Instructor publishes (Module 2, Adapter pattern)

Sign in as **Malarvili A/P Nallayan** → **Courses** → **BMIT3173** → **Add material**.
Choose *Link to an external resource*, paste any YouTube URL, and save.

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

Switch back to the lecturer. **The bell in the navbar now shows a red unread badge.** Nothing in
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

As the student, open **Dashboard** to see the progress chart move, **Trophies** for any new badge,
and **Certificates** to download the PDF.

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

- **Permissions** — untick `certificate.view_own` for *student* and save. That student's
  Certificates menu vanishes and the page 403s. Tick it back. No code changed.
- **Credentials** — revoke a certificate with a reason. The public page immediately says
  **REVOKED** and the download is refused.
- **Invites** — invite an email address; the tokenised link appears on screen (and is written to
  `storage/logs/laravel.log`, since mail is in log mode). Open it in a private window to register.
- **Accounts** — every action asks for *your own* password on a confirmation page first.
- **Badges** / **Paths** / **Settings** — the badge criteria, learning paths and progress weighting
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
