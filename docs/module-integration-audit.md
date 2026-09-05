# Module integration audit

How data crosses the boundaries between the five team members' modules, and by what mechanism.

Written to answer one question directly: **does this system actually implement communication
between modules, and if so, how?** Every claim below cites the file and line it is read from.

---

## 1. Deployment topology: Reading B — modules are packages inside one application

**There are no IP addresses between modules, and there cannot be.** All five modules run inside a
single Laravel process, served by one PHP entry point, backed by one MySQL schema. "Source IP to
destination IP" is the wrong mental model here and must not be forced into the design.

The evidence:

| Fact | Where |
|---|---|
| One dependency manifest, one framework install | `composer.json` — a single `require` block; no HTTP client is pulled in for internal calls |
| One HTTP entry point | `public/index.php` is the only PHP file in `public/` |
| One application container, two providers, for the whole system | `bootstrap/providers.php` — `AppServiceProvider` and `CredentialServiceProvider` |
| One base URL | `.env` — `APP_URL=http://localhost:8000` (single value, not one per module) |
| One database, shared by all five modules | `.env` — `DB_DATABASE=edusystem` at `127.0.0.1:3306`; all 44 tables live in it |
| No service boundary artifacts | No `Dockerfile`, no `docker-compose.yml`, no Kubernetes manifests, no per-module process definitions anywhere in the repository |
| No internal network surface | `routes/` contains only `web.php`, `auth.php` and `console.php`. There is **no `api.php`** — the system exposes no service interface for anything to call |
| Even the queue and session are tables, not brokers | `.env` — `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database` |

> **Correction to carry into the report:** `SYLLABUS_AUDIT.md` §2 states "`routes/api.php` is
> unused". That file does not exist at all any more. The statement should read that the project
> exposes no API routes whatsoever.

Because Reading B applies, section 6 of the task ("document host/port configuration") does not
apply — there is nothing to configure. What follows is the real mechanism table.

---

## 2. The modules and their owners

| # | Module | Owner | Pattern | Primary tables it writes |
|---|---|---|---|---|
| 1 | Identity, Access & Digital Credentialing | Serena Lim Sze Kee | Facade | `certificates`, `badge_student`, `student_progress`, `progress_snapshots`, `notifications`, `permissions`, `activity_logs` |
| 2 | Academic Resources Repository, and the calendar | Foo Chong Xian | Adapter | `courses`, `course_materials`, `announcements`, `course_events`, `course_invitations` |
| 3 | Student Forum & Notifications | Ong Shun Yan | Observer | `posts`, `replies`, `announcement_comments` — and produces rows in `notifications` |
| 4 | Skill Assessment & Quiz | Wong Siew Lam | Strategy | `quizzes`, `questions`, `answers`, `quiz_attempt_answers` |
| 5 | Academic Progress Analytics & Evaluation | Ong Kwong Wei | State | `assignments`, `submissions`, `quiz_attempts`, `grades` |

Ownership boundaries are stated in `docs/EduSystem.md` §2A.

---

## 3. Module-to-module communication table

Four mechanisms are in use. None of them is a network call.

| Source module | Target module | Data passed | Mechanism | File and line |
|---|---|---|---|---|
| **M5** Evaluation | **M1** Credentialing | A `Grade` — score plus its link to a submission or quiz attempt | **Eloquent model event.** `Grade::created` fires; the listener resolves the credential service from the container and hands it the grade | `app/Providers/AppServiceProvider.php:68-70` → `app/Patterns/Facade/CredentialAuthority.php` (`handleGradeRecorded`) |
| **M4** Quiz | **M5** Evaluation | Marked quiz percentage | **Direct model write** into Module 5's `grades` table, inside the attempt transaction | `app/Http/Controllers/QuizAttemptController.php:102-105` |
| **M5** Evaluation | **M5** `grades` | Assignment mark | State object writes the authoritative grade and moves the submission to `graded` | `app/Patterns/State/SubmittedState.php:57-62`, invoked at `app/Http/Controllers/SubmissionController.php:110` |
| **M3** Forum | **M1** Inbox | Notification rows (new post, reply, mention, announcement comment) | **Observer → shared service.** Three models are observed; the observer calls one shared `Notifier`, which applies the recipient's preferences | `app/Providers/AppServiceProvider.php:49-51` → `app/Patterns/Observer/SystemNotificationObserver.php:179` → `app/Support/Notifier.php:31-60` |
| **M2** Calendar | **M1** Inbox (via M3's sender) | Reminder notifications for imminent classes, due assignments, closed assignments | **Scheduled console command.** Reads M2's `course_events` and M5's `assignments`, writes through the same `Notifier`. Deduplicated by a `reference` string | `app/Console/Commands/SendScheduledReminders.php:79`, `:122`, `:162`; scheduled in `routes/console.php` |
| **M2** Courses | **M3** Forum | A `DiscussionForum` row created alongside every new course | **Direct model create** — a course is never left without somewhere to ask | `app/Http/Controllers/CourseController.php:92-95` |
| **M2** Course page | **M4** + **M5** | Quizzes and assignments, rendered as an ordered study plan with completion state | **Shared service.** `StudyPlan` reads M4's `quizzes` and M5's `assignments` / `submissions` | `app/Http/Controllers/CourseController.php:143` → `app/Support/StudyPlan.php:63-92` |
| **M2** Calendar | **M5** Assignments | `assignments.due_date` presented as a calendar entry | **Adapter.** M5's `Assignment` is wrapped in M2's `CalendarEntry` interface, so the grid iterates one list. Nothing is copied — the deadline stays in M5's table | `app/Http/Controllers/CalendarController.php:55`, `:68` → `app/Patterns/Adapter/AssignmentDeadlineAdapter.php:15` |
| **M1** Credentialing | **M2**, **M3**, **M5** | Reads courses, forum posts, grades and submissions to compute progress and badge eligibility | **Shared-database reads via Eloquent**, read-only by design (§2A) | `CredentialAuthority.php:406` (forum posts), `:420` (quiz grades), `:429` (submissions), `:552-562` (course quizzes/assignments/forum), `:649-658`, `:667-684` |
| **M5** Analytics | **M1** Progress | Completion snapshots, aggregated into the XML/XSLT trend chart | Shared-database read | `app/Http/Controllers/AnalyticsController.php:212-223` |
| **M1** Dashboard | **M2**, **M3**, **M5** | Progress, certificates, submissions, unread notification count | Shared-database reads | `app/Http/Controllers/DashboardController.php:43`, `:68`, `:71`, `:99`, `:134` |
| **M1** Access control | **all modules** | Permission decisions — `$user->can('...')` | **Gate registered once at boot**, resolved against `permissions` / `permission_role`. Every module's controllers consult it | `app/Providers/AppServiceProvider.php:129-136` |
| **M1** Account state | **all modules** | Deactivated / must-change-password enforcement on every request | **HTTP middleware appended to the web stack** | `bootstrap/app.php:22-25` |
| **M1** Certificates UI | **M1** credential service | Issue, revoke, verify | **Constructor injection** from the service container | `app/Http/Controllers/CertificateController.php:30` |
| **M4** Quiz sitting | **M4** grading algorithms | Per-question response | **Constructor injection** of the strategy resolver; the algorithm is chosen per question at run time | `app/Http/Controllers/QuizAttemptController.php:28`, `:85-86` |

### The four mechanisms, named for the report

1. **Eloquent model events** — the loosest coupling in the system. M5 writes a `Grade` and knows
   nothing about credentialing; M1 listens. M3 saves a `Post` and knows nothing about the inbox.
2. **Shared application services** — `Notifier`, `StudyPlan`, `GradeScale`, `Mentions` in
   `app/Support/`. Two unrelated producers (an observer and a scheduled command) write inbox rows
   through one sender, so preference handling exists in one place.
3. **Shared database tables via Eloquent** — with written ownership rules (§2A): M5 is the sole
   writer of `grades`, M2 the sole writer of `courses`, and M1 reads both without writing either.
4. **Constructor injection from the service container** — how controllers reach services.

---

## 4. Gaps — pairs that should communicate

These were verified absences, not speculation: at the time of the audit `Notifier::send()` was
called from exactly two places in the codebase, and `CredentialAuthority.php` contained **zero**
references to it. Five of the six have since been closed.

| # | Missing link | Consequence | Status |
|---|---|---|---|
| 1 | **M1 → M3/inbox: a certificate was minted and nobody was told.** Issuance wrote an `ActivityLog` row but never a notification | The single most significant event in the system — earning a verifiable credential — was silent. The student found out only by visiting *My certificates* | **Fixed.** `Certificate` is now an observed subject |
| 2 | **M1 → M3/inbox: a badge was awarded silently** | The trophy cabinet changed with no prompt to go and look at it | **Fixed** in `BadgeRuleEvaluator` — see the note below on why this one cannot be an observer |
| 3 | **M5 → M3/inbox: work was marked and the student was not told** | A student had to poll the assignment page to discover they had been graded | **Fixed.** `Grade` is now an observed subject, for coursework only |
| 4 | **M2 → M3/inbox: posting an announcement notified nobody.** Only `Post`, `Reply` and `AnnouncementComment` were observed; `Announcement` itself was not | A lecturer broadcast a notice and no enrolled student was alerted — yet a *comment* on that notice did notify | **Fixed.** `Announcement` is now an observed subject |
| 5 | **M2 → M3/inbox: a course invitation was silent** | A student was invited to a course and discovered it only by visiting their Courses page | **Fixed.** `CourseInvitation` is now an observed subject |
| 6 | **M2 → M1: `materials_viewed` is permanently 0.** No view-tracking table exists, so the materials component of progress never contributes | Progress is measured on quizzes, assignments and forum activity only. Documented at `implementation-notes.md` | **Open, and accepted** — it needs a schema addition, not a wiring fix |

### How they were closed, and what it demonstrates

The delivery path already existed. `Notifier::send()` applies the recipient's preferences and
deduplicates on a reference; these producers were simply never connected to it.

Four of the five were closed by **registering the model as a subject of the existing Observer** —
`Announcement`, `Grade`, `Certificate` and `CourseInvitation` in `AppServiceProvider`. That took
four lines and **no change at all** to the announcement screen, the grading flow, the credential
authority or the enrolment controller. The Observer now serves **seven subjects**, and this is the
strongest evidence in the project for the pattern's central claim: a new listener was added without
the subjects learning that notifications exist.

**The badge award is the exception, and it is worth explaining in the report.** A badge is granted
by attaching a row to the `badge_student` pivot, and `attach()` fires no Eloquent model event —
there is no model for an observer to watch. So that one notification is sent explicitly, next to
the award it announces, in `BadgeRuleEvaluator`. It still goes through the same `Notifier`, so
preferences and deduplication apply identically.

Two design decisions inside the fix:

- **A marked quiz does not notify.** Quizzes are graded the instant they are submitted and the
  student is already looking at the result. Only coursework — marked later, by a person, out of
  sight — produces a notification. This follows the rule the reminder command already states: being
  told about something you just did is how people learn to ignore notifications.
- **`certificate.issued` and `badge.awarded` were already listed on the preferences screen** before
  anything produced them — switches for notifications that never arrived. Both now have a producer,
  and the string literals in `NotificationController::TYPES` have been replaced by the constants
  their senders declare, so the two can no longer drift apart.

---

## 5. Answering the original question directly

> "Does the system implement communication from my module to the other members' modules?"

**Yes, and it is real.** The integration is not decorative — the workflow in `EduSystem.md` §4
executes end to end across all five modules:

```
Student sits a quiz          (M4)
  → writes a Grade           (M4 → M5, QuizAttemptController.php:102)
  → Grade::created fires     (M5 → M1, AppServiceProvider.php:68)
  → progress recalculated,
    badges evaluated,
    certificate minted       (M1, CredentialAuthority.php:511-531)
  → appears on the dashboard (M1, DashboardController.php:43)
```

What crosses the boundaries is **method calls, model events, shared services and shared tables** —
never network messages. If the report needs a diagram, the arrows should be labelled with those
four mechanisms, not with addresses and ports.
