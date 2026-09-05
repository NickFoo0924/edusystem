# LearnSync

**Integrated Educational Resource and Collaborative Learning Portal**
BAIT3173 Integrative Programming · Laravel 12 · SDG 4 (Quality Education)

A learning management system where instructors publish materials, run quizzes and mark
coursework, and students earn **verifiable digital credentials** — certificates carrying a unique
credential ID and a QR code that anyone can check publicly, without an account, in the style of
Cisco NetAcad.

> **Installing or running it?** See [`tutorial.md`](tutorial.md) for setup, the demo data, moving
> the project between machines, the test suite and troubleshooting.

---

## Objectives

The project addresses **SDG 4, Quality Education**, by making course delivery and the credentials
that come out of it verifiable by anyone, at no cost and without an account.

Four requirements shape every part of it:

- **Laravel's MVC architecture**, with the conventions from the module practical: resource
  controllers, resource routes in `routes/web.php`, Blade views extending one layout, `$fillable`
  on every model, snake_case-plural tables and PascalCase-singular models.
- **Eloquent ORM for every query.** There is no raw SQL anywhere in `app/`.
- **Five modules, one owner each, and exactly one GoF design pattern per module.**
- **Role-based access control stored as data**, not as hardcoded role checks, so the permission
  matrix can be retuned at runtime.

---

## Contents

1. [Login accounts](#login-accounts)
2. [Courses and who teaches them](#courses-and-who-teaches-them)
3. [Finding your way around](#finding-your-way-around)
4. [A 10-minute demo walkthrough](#a-10-minute-demo-walkthrough)
5. [The five modules and their design patterns](#the-five-modules-and-their-design-patterns)
6. [Where everything lives](#where-everything-lives)
7. [Roles and permissions](#roles-and-permissions)
8. [Grading scale](#grading-scale)
9. [Packages used](#packages-used)

---

## Login accounts

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

## Courses and who teaches them

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

### How a student gets into a course

There is no browsable catalogue and no self-service enrolment. A student sees a course only if one
of two things has happened, and if neither has, the course does not appear on their side at all:

1. **The lecturer invited them.** It shows up under **Invitations** on their Courses page with an
   **Enrol** button. Until they press it they are invited, not enrolled.
2. **They typed the class code.** The **+** in the top bar opens the join page.

The **class code** is not the course code. `BMIT3173` is public, printed on every timetable, and
identifies the course; the class code is six random characters like `mFiJQT` that the lecturer
hands out, and holding it is itself the evidence they meant you to join. It appears on the course
page for the lecturer who owns it, alongside the roster and the invite box, and **Issue a new code**
revokes the old one immediately.

Forging the enrol request for a course you were not invited to returns **403** — the invitation is
the authorisation, not the button.

---

## Finding your way around

Navigation lives in a **collapsible rail down the left**. The hamburger at the top-left folds it to
icons only and expands it again; the choice is remembered between pages. Items are grouped under
**Learning**, **Teaching** and **Administration**, and each one is gated on a permission — an
administrator sees thirteen, a lecturer five, a student six.

The **top bar** holds only the menu toggle, the brand, the notification bell with its unread count,
your avatar and log out. Clicking your avatar or name opens your profile, where you can set a
display picture, a bio, and — if you teach — a school email and an optional phone number.

Anywhere a lecturer is named, their name is a link to a read-only contact card: school email
always, phone only if that lecturer chose to publish one.

---

## A 10-minute demo walkthrough

This is the shortest route through every module.

### Demonstrating three roles at once

A browser holds **one login at a time**. Signing in as somebody else in a second tab replaces the
session in every tab of that browser, and reloading the first tab shows the new user. That is how
HTTP cookies work -- one cookie jar per browser profile, shared by every tab -- and Google
Classroom, Moodle and every other cookie-based application behave identically. Nothing in LearnSync
is caching a user.

To show admin, lecturer and student side by side, give each one **its own cookie jar**:

| Role | Where to open it |
|---|---|
| Administrator | a normal window |
| Lecturer | a private / incognito window |
| Student | a second browser (Edge, Firefox) or a second browser profile |

All three logins then coexist, and each window keeps its own. The signed-in name and **role** are
shown in the top bar of every page, so there is never any doubt which window is which.

If a page is left open while the session changes underneath it, submitting from it does **not**
act as the other user: the CSRF token belongs to the old session, so the write is refused. You are
redirected with an explanation rather than being shown a bare "Page Expired".

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

### Step 2b — The conversation under an announcement

Open **Announcements**. Each announcement carries its own comment thread, **collapsed** behind
*View 3 comments* so a busy notice board stays readable — expand only the one you care about.

Students and lecturers both take part; the lecturer's own replies are tagged **author**, so an
answer is distinguishable from a classmate's guess. Commenting fires the same Observer as the
forum, so the notice's author gets a bell notification — and commenting on your own announcement
notifies nobody.

Administrators can read and delete any thread but have **no comment box**, which is Section 7's
rule about forums applied here: they run the class, they are not in it.

### Step 2c — The calendar (Module 2, Adapter again)

Open **Calendar** in the rail. A lecturer or administrator gets **Schedule an event** for a class,
an online meeting or a briefing; give it a meeting link and the entry on the grid clicks straight
through to it. An administrator can also schedule institution-wide, the same privilege they hold
over global announcements. Students schedule nothing — they read the calendar for the courses they
are enrolled in.

**Assignment deadlines are not scheduled, and that is the point.** They have no row in
`course_events`. The calendar reads `assignments.due_date` and adapts it into the same
`CalendarEntry` interface a scheduled event arrives through, so the grid iterates one list without
knowing which is which. Change an assignment's due date and the calendar moves with it, because
there is only ever one date — nothing to copy and nothing to fall out of step.

That is the Adapter doing the same job it does for materials, on a second mismatched pair: an event
has a duration and a room, a deadline has neither and means "by" rather than "at".

### Step 2d — The calendar reminds people (Module 3)

A calendar nobody looks at reminds nobody, so the entries produce notifications:

- **A class or meeting is about to start** — everyone it concerns, students and the lecturer both,
  an hour ahead by default.
- **An assignment is due soon** — but only the students who have **not** submitted. Reminding
  someone to do a thing they already did is how people learn to ignore notifications.
- **An assignment has closed** — the lecturer, told how many submissions are waiting to be marked.

These are produced by a command, not by the Observer, and the difference is worth stating in the
report: the Observer fires when a model is *saved*, and nothing is saved when a deadline
approaches. Time passing is not an Eloquent event. So this is a scheduled producer feeding the same
inbox through the same sender, honouring the same per-user preferences — each of the three can be
switched off individually under notification preferences.

**Reminders only fire while a scheduler is running.** In a spare terminal:

```bash
php artisan schedule:work
```

For a demo you can skip that and produce them on demand:

```bash
php artisan reminders:send
```

Either way it is safe to run repeatedly. Every reminder carries a reference — `event:12`,
`assignment_due:3` — and nobody is told the same thing twice.

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

### Step 5 — Lecturer marks it, and a certificate mints itself (Module 1, Facade)

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

## The five modules and their design patterns

Every pattern lives in `app/Patterns/`. **No pattern logic sits inside a controller.**

| # | Module | Owner | Pattern | Where |
|---|---|---|---|---|
| 1 | Identity, Access & Digital Credentialing | Serena Lim Sze Kee | **Facade** (Structural) | `app/Patterns/Facade/CredentialAuthority.php` |
| 2 | Academic Resources Repository, and the calendar | Foo Chong Xian | **Adapter** (Structural) | `app/Patterns/Adapter/` |
| 3 | Student Forum & Notifications | Ong Shun Yan | **Observer** (Behavioural) | `app/Patterns/Observer/SystemNotificationObserver.php` |
| 4 | Skill Assessment & Quiz | Wong Siew Lam | **Strategy** (Behavioural) | `app/Patterns/Strategy/` |
| 5 | Academic Progress Analytics | Ong Kwong Wei | **State** (Behavioural) | `app/Patterns/State/` |

---

## Where everything lives

```
app/
  Patterns/            all five design patterns, one folder each
  Http/Controllers/    23 controllers, one per resource, actions grouped on it
  Models/              33 Eloquent models
  Support/             GradeScale, Notifier, Mentions, StudyPlan
  Console/Commands/    the scheduled reminder command
database/
  migrations/          43 migrations, 44 tables
  seeders/             DatabaseSeeder plus BulkStudentSeeder for the full cohort
docs/                  this file, the setup guide, the specification and the audits
resources/views/       Blade templates, all extending layout.blade.php
resources/xml/         the analytics schema and stylesheet
routes/web.php         every route, grouped and commented by module
tests/                 29 automated tests
```

All database access uses **Eloquent ORM**. There is no raw SQL anywhere — no `DB::raw()`,
no `DB::select()`.

---

## Roles and permissions

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
| Forum, and comments under announcements | — | ✅ | ✅ |
| Schedule classes and meetings on the calendar | ✅ (institution-wide) | ✅ (own courses) | — |
| Read the calendar | ✅ | ✅ | ✅ |
| Class analytics | ✅ | ✅ (own courses) | — |
| Invitations, accounts, permission matrix, activity log | ✅ | — | — |
| Badges, learning paths, templates, settings | ✅ | — | — |
| Issue / revoke certificates | ✅ | — | — |

Administrators deliberately **cannot** take quizzes, submit work or post in forums, and lecturers
cannot enrol as students — that is Section 7 of the specification, not an oversight.

---

## Grading scale

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

## Packages used

| Package | Purpose |
|---|---|
| `laravel/breeze` | authentication scaffolding |
| `barryvdh/laravel-dompdf` | renders certificate PDFs |
| `simplesoftwareio/simple-qrcode` | QR code embedded in every certificate |
| `intervention/image` | crops avatars and badge icons |
| `chart.js` | the student progress-over-time chart |
| Tailwind CSS | styling (installed by Breeze) |
