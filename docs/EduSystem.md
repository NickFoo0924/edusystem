# Project Context & AI Coding Guidelines
**Project Name:** LearnSync (Integrated Educational Resource and Collaborative Learning Portal)
**Folder Name:** `EduSystem`
**Target SDG:** SDG 4 (Quality Education)
**Framework:** Laravel (PHP 8+)
**Tools Used:** Composer, MySQL, Chart.js, Tailwind/Bootstrap
**Required Packages:** `barryvdh/laravel-dompdf` (PDF certificates), `simplesoftwareio/simple-qrcode` (verification QR codes), `intervention/image` (avatar / badge image handling)

> ### Revision Note (v3) — written after implementation
> The system described below has been built in full: all five modules, all five design patterns, and every item in the Section 8 build priority. This note records where the **implementation** differs from Section 3 as originally written, so the **ERD can be corrected before submission**. Nothing in Sections 1, 2, 2A, 4, 5 or 7 changed — the module boundaries, the pattern assignments and the RBAC matrix were all implementable as specified.
>
> **One new table.** `quiz_attempt_answers` (`quiz_attempt_id`, `question_id`, `response`, `is_correct`, `awarded_score`). Section 3 gives `quiz_attempts` only a duration, leaving nowhere to record what the student actually answered — grading and reviewing an attempt are both impossible without it.
>
> **New columns.** `courses.code` (e.g. `BMIT3173`, unique); `course_materials.title`; `assignments.description`; `assignments.allow_late_submission` (per-assignment late policy, default accept-and-label); `submissions.submitted_at` (needed to tell on-time from late, which the `on_time_submissions` badge depends on) plus a composite unique key on (`assignment_id`, `student_id`); `users.school_email` (the address published on a lecturer's public contact card, kept apart from the login `email`); `users.phone` and `users.show_phone` (optional, withheld by default).
>
> **Widened enum.** `course_materials.type` gains `other`, giving the four fixed sections the course page renders: Lecture notes, Tutorial question, Practical question, Others.
>
> **New question type.** `questions.type` accepts `multi` alongside `mcq` and `text` — several correct answers, all of which must be selected. The required count is derived from the number of options flagged correct rather than stored.
>
> **Relaxed constraint.** `badges.icon_path` is nullable, so a badge rule can be defined before its artwork exists.
>
> **Extra model, no schema change.** `permission_role` is given an Eloquent model (`PermissionRole`) because it pivots a permission against a *role enum* rather than a roles table, leaving `belongsToMany` nothing to point at. The alternative was `DB::table()`, which Section 5 forbids.
>
> **Seeder.** Two certificate templates rather than one: a pathway certificate has to read "the learning path", not "the course".

> ### Revision Note (v2)
> Module 1 was previously scoped as "auth + one progress number + a PDF", most of which Laravel's built-in scaffolding provides for free. It has been expanded into a full **Identity, Access & Digital Credentialing** module inspired by Cisco NetAcad's verifiable certificate model.
> **ERD changes introduced in this revision:** the old `achievements` table is split into `certificates` (formal, verifiable PDF credentials) and `badges` + `badge_student` (gamified micro-achievements). New tables have been added for permissions, invitations, activity logs, certificate templates, learning paths and notification preferences. `announcements.admin_id` is renamed `author_id`. Update the ERD diagram before submission.

## 1. Project Overview & Objectives
LearnSync is a professional-grade Learning Management System (LMS). It provides a platform where **Instructors** upload study materials, deploy dynamic quizzes, and manage assignments. **Students** consume these materials, take assessments, track their progress through a visual dashboard, earn **verifiable digital credentials**, and engage in collaborative Q&A forums.

The system's differentiating concept is **credentialing**: like Cisco Networking Academy, completing a course or a learning path issues the student a certificate carrying a unique credential ID and a QR code, which any third party can verify publicly without logging in.

**Strict Technical Requirements:**
- Must use Laravel's MVC architecture.
- Must use Laravel's built-in Auth for Login/Logout (extended — see Module 1).
- Strictly utilize Eloquent ORM for all database queries (No raw SQL).
- Each of the 5 modules MUST implement exactly ONE classic GoF Software Design Pattern.

---

## 2. The 5 Modules & Design Patterns Breakdown

Five modules, one owner each, and exactly one GoF design pattern per module. Every pattern lives in
`app/Patterns/`; no pattern logic sits inside a controller.

| # | Module | Owner | Pattern | Category | Where the pattern lives |
|---|---|---|---|---|---|
| 1 | Identity, Access & Digital Credentialing | Serena Lim Sze Kee | **Facade** | Structural | `app/Patterns/Facade/CredentialAuthority.php` |
| 2 | Academic Resources Repository | Foo Chong Xian | **Adapter** | Structural | `app/Patterns/Adapter/` |
| 3 | Student Forum & Notifications | Ong Shun Yan | **Observer** | Behavioural | `app/Patterns/Observer/SystemNotificationObserver.php` |
| 4 | Skill Assessment & Quiz | Wong Siew Lam | **Strategy** | Behavioural | `app/Patterns/Strategy/` |
| 5 | Academic Progress Analytics | Ong Kwong Wei | **State** | Behavioural | `app/Patterns/State/` |

Each module below follows the same shape: who owns it, which entities it writes to, what it does
broken into numbered areas, and the pattern with the justification to use in the report.

### Module 1: Identity, Access & Digital Credentialing
*   **Owner:** Serena Lim Sze Kee
*   **Entities Managed:** User, Instructor, Student, Administrator, Permission, Invitation, ActivityLog, StudentProgress, Badge, Certificate, CertificateTemplate, LearningPath, NotificationPreference.
*   **Assigned Pattern:** **Facade Pattern (Structural)** — *revised; see the note at the head of the Module 1 pattern section below*

#### 1A. Extended Authentication & Access Control
Laravel Breeze supplies the login/logout/reset scaffolding. This module extends it into a managed identity system:
*   **Invitation-based registration** — public self-signup is disabled. An Administrator issues an `Invitation` (email + intended role + expiring token); the recipient registers only through that tokenised link. This replaces Breeze's open registration route entirely.
*   **Database-driven permission matrix** — the RBAC rules in Section 7 are stored in a `permissions` table and a `permission_role` pivot, not hardcoded. Admin toggles them through a checkbox grid UI. Laravel Gates resolve against the database, so permissions are configurable at runtime.
*   **Account lifecycle** — activate / deactivate / soft-delete users, forced password reset on first login, and password history (a new password may not match the previous 3).
*   **Login throttling & lockout** — 5 consecutive failed attempts lock the account; only an Administrator can unlock it.
*   **Audit trail** — every security-relevant action (login, logout, role change, permission change, certificate issue, certificate revoke, user deactivation) writes an `ActivityLog` row with actor, target, IP address and user agent. Admin can filter and export to CSV.
*   **Profile management** — avatar upload, display name, bio, self-service password change.
*   *(Stretch)* **Email OTP two-factor authentication** — 6-digit code, 5-minute expiry, stored hashed.
*   *(Stretch)* **Bulk user import** — Administrator uploads a CSV class list; the system creates the accounts and dispatches invitations in one operation.

#### 1B. Progress Tracking
*   `StudentProgress` is tracked **per student per course**, not as a single global percentage.
*   Completion is a weighted composite of materials viewed, quizzes passed and assignments submitted. The weighting (e.g. quiz 50% / assignment 40% / participation 10%) is an admin-configurable setting, not a magic number in code.
*   Each recalculation writes a `progress_snapshot` row so the student's own dashboard can render a progress-over-time line chart (Chart.js).

#### 1C. Digital Credentialing (the module's centrepiece)
*   **Certificate templates** — an Administrator creates reusable templates: background image, signature image, and body text containing placeholders `{{student_name}}`, `{{course_title}}`, `{{score}}`, `{{issued_date}}`, `{{credential_id}}`.
*   **Issuance** — when a student satisfies a course's completion criteria, the authority mints a `Certificate` bearing a globally unique human-readable **credential ID** (format: `LS-{YEAR}-{8 CHAR BASE32}`, e.g. `LS-2026-A7F3D9K2`), renders the template to PDF via DomPDF, and stores the file path.
*   **Public verification** — `GET /verify/{credential_id}` is an unauthenticated route. It displays the holder's name, course, score, issue date and status (**Valid / Expired / Revoked**). A QR code encoding this URL is embedded in every generated PDF, so the certificate can be verified by scanning it with a phone.
*   **Integrity hash** — on issuance the system stores `SHA-256(student_id | course_id | score | issued_at | credential_id)`. The verification page recomputes and compares this hash, proving the record has not been tampered with in the database.
*   **Revocation** — an Administrator may revoke a certificate with a stated reason; the verification page immediately reports `REVOKED` and the PDF download is disabled.
*   **Learning Paths** — a `LearningPath` is an ordered collection of courses (e.g. "Web Development Pathway" = HTML → PHP → Laravel). Completing every course in the path issues a higher-tier **pathway certificate** in addition to the individual course certificates.

#### 1D. Badge Rules Engine
*   `Badge` records are **rules configured by an Administrator**, never hardcoded: `criteria_type` (`quiz_score`, `course_completion`, `path_completion`, `on_time_submissions`, `first_forum_post`, `login_streak`), `criteria_value`, `tier` (bronze / silver / gold) and an icon.
*   After any grade event, the authority evaluates all active badge rules for that student and awards any newly satisfied badges.
*   The student profile renders a **trophy cabinet**: earned badges in colour, unearned badges greyed out with their unlock condition displayed ("Score 90% or higher on any quiz").

#### 1E. Notification Inbox
*   Module 3 **produces** notification events. Module 1 **owns the inbox**: the navbar bell with unread count, the notification history page, mark-as-read / mark-all-read, and per-user `NotificationPreference` rows controlling which notification types the user receives.

#### Design Pattern Implementation — Facade

> ### Revision Note (v4) — pattern changed from Singleton to Facade
> Module 1 was originally assigned the **Singleton**. The tutor has since ruled that Singleton
> may not be used, so it has been replaced by the **Facade** (Structural). The module's
> behaviour, public method signatures and feature set are unchanged — only the way the object
> is constructed and what it delegates to. Facade is one of the nine patterns Chapter 3 of the
> syllabus teaches in depth, and it duplicates no other member's pattern (Adapter, Observer,
> Strategy, State).
>
> **The honest finding behind the change:** the original Singleton justification — "two
> concurrent instances could mint duplicate credential IDs" — does not survive scrutiny in
> PHP. Each HTTP request is a separate process with its own memory, so two simultaneous
> issuances were *always* two separate objects; the Singleton never provided any cross-request
> guarantee. What actually prevents duplicate credential IDs is the unique index on
> `certificates.credential_id` together with the collision-retry loop in
> `CredentialIdGenerator`. Both are untouched, so nothing was lost by removing the Singleton.

Create `app/Patterns/Facade/CredentialAuthority.php`, fronting five subsystem collaborators in
`app/Patterns/Facade/Subsystem/`. It is registered in `CredentialServiceProvider` with
`$this->app->scoped(...)`, which is request-scoped **dependency injection** — container lifetime
management, not a Singleton: the constructor is public, there is no static state, and no static
accessor exists.

**Justification (use this wording in the report):** Issuing a verifiable credential is not one
operation but nine. It requires minting a collision-free human-readable credential ID, sealing
the record with a SHA-256 integrity hash, substituting placeholders into an administrator's
template, rendering that template to PDF through DomPDF, generating and embedding a QR code that
encodes the public verification URL, writing the document to a private disk, recalculating the
student's weighted progress against admin-configurable settings, snapshotting that progress for
their chart, evaluating every active badge rule, checking whether the course just completed a
learning path, and writing the audit trail. That is five distinct collaborators and four
third-party libraries. The **Facade** pattern gives the rest of the system a single object with a
small, stable vocabulary — `issueCertificate`, `revoke`, `verify` — and hides all of it behind
that vocabulary. `CertificateController` issues a credential in one line and imports neither
DomPDF, nor the QR encoder, nor the settings table, nor the badge rules. Critically, the Facade
does not seal the subsystem off: each collaborator remains independently usable and independently
testable, which is precisely the pattern's stated intent — *provide a unified interface to a set
of interfaces in a subsystem, making the subsystem easier to use* — rather than a restriction on
how many may exist.

The subsystem it fronts, all in `App\Patterns\Facade\Subsystem`:

| Collaborator | Responsibility |
|---|---|
| `CredentialIdGenerator` | Mints `LS-{YEAR}-{8 CHAR BASE32}`, retrying on collision |
| `IntegrityHasher` | Computes the tamper seal and re-verifies it at verification time |
| `CertificateRenderer` | Placeholder substitution, DomPDF rendering, QR encoding, storage |
| `ProgressCalculator` | The weighted completion arithmetic, thresholds and course marks |
| `BadgeRuleEvaluator` | The badge rule registry and its six criteria types |

The Facade exposes, with signatures unchanged from the previous implementation:
`issueCertificate(Student, Course, ?float)`, `issuePathwayCertificate(Student, LearningPath)`,
`revoke(Certificate, string $reason)`, `verify(string $credentialId)`, `evaluateBadges(Student)`,
`recalculateProgress(Student, Course)`, `handleGradeRecorded(Grade)`, `verificationUrl(string)`
and `verificationQrCode(string)`.

> **Do NOT** justify this Facade as "it keeps the class tidy" — a marker will read that as
> cosmetic. The defensible argument is the one above: it is a genuine subsystem of five
> collaborators and four libraries, and the Facade is what stops that complexity leaking into
> every controller that needs a credential.

---

### Module 2: Academic Resources Repository
*   **Owner:** Foo Chong Xian
*   **Entities Managed:** Course, CourseMaterial, Announcement, AnnouncementComment, CourseInvitation, CourseEvent.
*   **Assigned Pattern:** **Adapter Pattern (Structural)**

#### 2.1 The course hub
*   A `Course` is identified by its code as much as its title, e.g. `BMIT3173`, and belongs to one instructor.
*   The course page gathers everything in one place: materials, quizzes, assignments, announcements and a link to the forum.
*   Students see a **suggested plan** — the same content ordered as a sequence to work through: read the notes, work the tutorial, complete the practical, attempt the quiz, submit the assignment. Only the assessed steps report completion, because the system cannot observe whether notes were read.

#### 2.2 Enrolment is the instructor's decision
*   There is no browsable catalogue and no self-service enrolment. A course reaches a student in exactly two ways, and if neither has happened the course does not appear to them at all.
*   **Invitation** — the instructor names a student, who accepts it from their Courses page.
*   **Class code** — six random characters the instructor hands out, distinct from the public course code. Holding it is itself the evidence the instructor meant you to join. The instructor can issue a new one, which revokes the old.

#### 2.3 Materials
*   Filed under four fixed headings: Lecture notes, Tutorial question, Practical question, Others. Every heading always appears with a count, so an empty section is visibly empty rather than absent.
*   A material is either an uploaded file or a link to something outside the system.

#### 2.4 Announcements and their discussion
*   An instructor addresses a course; an administrator may broadcast to everyone.
*   Each announcement carries a comment thread so a question about a notice stays attached to it. Collapsed by default.

#### 2.5 The calendar
*   Scheduled classes and online meetings, alongside assignment deadlines.
*   Deadlines are **not** copied into the calendar. They already exist as `assignments.due_date`, and a copy would disagree with it the moment an instructor moved one.

#### Design Pattern Implementation — Adapter
Create `app/Patterns/Adapter/`.

**Justification (use this wording in the report):** An instructor may attach an uploaded PDF or a
link to something outside the system. The two have nothing in common — one is a file on disk with a
size and a MIME type, the other is a URL on somebody else's server. Each is wrapped in an adapter
exposing one `DisplayableMaterial` interface, so the Blade view iterates a single list and calls the
same methods on every item, with no `is_external` branching in the template.

The same pattern is applied a second time by the calendar. A scheduled event and an assignment
deadline are equally mismatched — one has a duration and a room, the other is a bare date meaning
"by" rather than "at" — and both are presented through one `CalendarEntry` interface.

### Module 3: Student Forum & Notifications
*   **Owner:** Ong Shun Yan
*   **Entities Managed:** DiscussionForum, Post, Reply. Produces rows in `notifications`, which Module 1 owns.
*   **Assigned Pattern:** **Observer Pattern (Behavioural)**

#### 3.1 The course forum
*   Every course has exactly one Q&A forum, created with the course so it is never without somewhere to ask.
*   Laid out as a conversation: questions, replies indented beneath them, instructor messages marked.
*   Administrators are deliberately excluded from posting (Section 7). They run the class; they are not in it.

#### 3.2 Tagging
*   Typing `@name` in a post or reply notifies that person, student or instructor.
*   Candidates come from the course itself, so a mention cannot reach somebody outside the conversation.
*   A handle claimed by more than one person resolves to nobody, which is quieter than guessing and notifying the wrong one.

#### 3.3 What produces a notification
*   A new question — the course instructor is told.
*   A reply — whoever asked is told.
*   A mention — the person named is told, and only once, so tagging the instructor in a new question does not produce two notifications.
*   A comment under an announcement — its author is told.

#### 3.4 Reminders
*   A class or meeting about to start, a deadline approaching, a deadline that has closed with work waiting to be marked.
*   These are **not** the Observer, and the report should not claim they are: the Observer fires when a model is saved, and nothing is saved when a deadline approaches. They are a scheduled producer feeding the same inbox through the same sender.

#### Design Pattern Implementation — Observer
Create `app/Patterns/Observer/SystemNotificationObserver.php`.

**Justification (use this wording in the report):** The `Post` is the Subject and the observer is the
Observer; Eloquent's model-observer mechanism is the `notify()` call, since saving a Post broadcasts
a `created` event and every registered observer runs automatically. The point is that `Post` knows
nothing about notifications. Module 3 produces the events, Module 1 owns the inbox that displays
them, and neither has to import the other — a second observer, an email digest say, could be added
without touching the forum code at all.

Three subjects now share the one observer: Post, Reply and AnnouncementComment.

### Module 4: Skill Assessment & Quiz
*   **Owner:** Wong Siew Lam
*   **Entities Managed:** Quiz, Question, Answer, QuizAttemptAnswer.
*   **Assigned Pattern:** **Strategy Pattern (Behavioural)**

#### 4.1 Building a quiz
*   An instructor creates a quiz on their own course and defines its questions and answer options.
*   A quiz carries a time limit.

#### 4.2 The three question types
*   **One answer** — a single correct option.
*   **Several answers** — more than one option must be selected, and all of them. How many is derived from the number of options flagged correct rather than stored, since a question claiming "choose 3" while holding two correct answers would be unanswerable.
*   **Fill in the blank** — a typed response, matched on similarity rather than equality, so a near-miss spelling is still accepted.

#### 4.3 Marking
*   An attempt records what the student actually answered, question by question, so it can be graded and later reviewed.
*   Marking happens the moment the attempt is submitted; the resulting mark becomes a `Grade`, which is Module 5's to write.

#### Design Pattern Implementation — Strategy
Create `app/Patterns/Strategy/`.

**Justification (use this wording in the report):** Each question type is marked by a completely
different algorithm — exact match against the option flagged correct, set comparison across several
options, or string similarity for a typed answer. Each is a `GradingStrategy`, and the controller
never asks what type a question is: it asks a resolver for the right strategy and calls `grade()`.
Adding the multiple-answer type required no change to the controller at all, which is the claim the
pattern makes.

### Module 5: Academic Progress Analytics & Dashboard
*   **Owner:** Ong Kwong Wei
*   **Entities Managed:** Assignment, Submission, QuizAttempt, Grade. Sole writer of `grades`.
*   **Assigned Pattern:** **State Pattern (Behavioural)**

#### 5.1 Assignments and submissions
*   An instructor sets an assignment with a brief and a due date, and decides whether late work is accepted.
*   A student uploads a file, may replace it while it is still a draft, and submits it when ready.
*   One submission per student per assignment, enforced by the schema.

#### 5.2 Marking
*   The instructor's dashboard carries a review queue of everything handed in and not yet marked.
*   Assigning a mark writes a `Grade`, which is the event the whole credentialing chain hangs from (Section 4, Step 5).

#### 5.3 Cohort analytics
*   Per course: class average, highest, lowest, how many passed, the grade distribution, and submission turnaround.
*   A completion-trend chart drawn through an XML pipeline — the figures are serialised with `DOMDocument`, validated against an XSD, and transformed into SVG by an XSLT stylesheet. The same document is downloadable as a data export.
*   This is the **instructor and administrator** view. A student's own progress towards their next certificate belongs to Module 1 (Section 2A).

#### Design Pattern Implementation — State
Create `app/Patterns/State/`.

**Justification (use this wording in the report):** A submission behaves differently depending on
where it is in its life. In `DraftState` the file can be replaced; in `SubmittedState` it is locked
and waiting; in `GradedState` a mark exists. Rather than the controller testing a status column
before every action, the submission holds a state object and asks it — the state decides whether
`updateFile()` or `assignGrade()` is legal, and an illegal transition is refused by the object
itself rather than by a conditional somewhere in a controller.

---

## 2A. Module Scope Boundaries
**AI INSTRUCTION: These boundaries prevent two owners implementing the same feature. Respect them when generating code.**

| Overlap risk | Module 1 owns | Other module owns |
|---|---|---|
| **Notifications** | The inbox: bell dropdown, unread count, history page, per-type user preferences, `notification_preferences` table | **Module 3** owns event *production* — the Observer that writes rows into `notifications` |
| **Progress / analytics** | The **student's own** credential-oriented view: "my progress toward my next certificate", badge cabinet, personal progress trend | **Module 5** owns the **instructor/admin** grading analytics: class averages, grade distributions, submission turnaround |
| **Grades** | Reads `grades` as input to progress and credential decisions. Never writes to `grades` | **Module 5** is the sole writer of `grades` |
| **Courses** | Reads `courses` to build learning paths and name certificates. Never writes to `courses` | **Module 2** is the sole writer of `courses` and `course_materials` |

---

## 3. Database Entities & Exact Schema (Data Types)
**AI INSTRUCTION: Use Laravel Migrations to create these tables matching the exact relationships and multiplicities specified below.**
Tables marked **[CORE]** must be built. Tables marked **[STRETCH]** are implemented only if time permits.

### Shared / Module 1 — Identity & Access
1.  **users** **[CORE]**: `id` (PK), `name` (string), `email` (string, unique), `school_email` (string, nullable — published contact address), `password` (string), `role` (enum: 'admin', 'instructor', 'student'), `avatar_path` (string, nullable), `bio` (text, nullable), `phone` (string, nullable), `show_phone` (boolean, default false), `is_active` (boolean, default true), `failed_login_attempts` (integer, default 0), `locked_until` (datetime, nullable), `must_change_password` (boolean, default false), `last_login_at` (datetime, nullable), `deleted_at` (softDeletes).
2.  **permissions** **[CORE]**: `id` (PK), `key` (string, unique — e.g. `course.create`), `label` (string), `group` (string — e.g. 'Course Management').
3.  **permission_role** **[CORE]** (Many to Many): `permission_id` (FK), `role` (enum: 'admin', 'instructor', 'student').
4.  **invitations** **[CORE]**: `id` (PK), `email` (string), `role` (enum), `token` (string, unique), `invited_by` (FK to users), `expires_at` (datetime), `accepted_at` (datetime, nullable).
5.  **activity_logs** **[CORE]**: `id` (PK), `user_id` (FK to users, nullable — the actor), `action` (string — e.g. `certificate.revoked`), `target_type` (string, nullable), `target_id` (integer, nullable), `ip_address` (string), `user_agent` (string), `created_at` (datetime).
6.  **password_histories** **[STRETCH]**: `id` (PK), `user_id` (FK), `password_hash` (string), `created_at` (datetime).
7.  **otp_codes** **[STRETCH]**: `id` (PK), `user_id` (FK), `code_hash` (string), `expires_at` (datetime), `consumed_at` (datetime, nullable).

### Module 1 — Progress, Credentials & Badges
8.  **student_progress** **[CORE]**: `id` (PK), `student_id` (FK to users), `course_id` (FK to courses), `materials_viewed` (integer), `quizzes_passed` (integer), `assignments_submitted` (integer), `completion_percentage` (double), `last_calculated_at` (datetime). *Unique composite key on (`student_id`, `course_id`).*
9.  **progress_snapshots** **[CORE]**: `id` (PK), `student_progress_id` (FK), `completion_percentage` (double), `captured_at` (datetime).
10. **certificate_templates** **[CORE]**: `id` (PK), `name` (string), `background_path` (string, nullable), `signature_path` (string, nullable), `body_text` (text — contains placeholders), `is_active` (boolean).
11. **certificates** **[CORE]**: `id` (PK), `student_id` (FK to users), `course_id` (FK to courses, nullable), `learning_path_id` (FK, nullable), `certificate_template_id` (FK), `credential_id` (string, unique — `LS-2026-A7F3D9K2`), `final_score` (double), `integrity_hash` (string), `pdf_path` (string), `issued_at` (datetime), `expires_at` (datetime, nullable), `revoked_at` (datetime, nullable), `revocation_reason` (string, nullable). *Exactly one of `course_id` / `learning_path_id` must be set.*
12. **badges** **[CORE]**: `id` (PK), `name` (string), `description` (string), `icon_path` (string, nullable), `tier` (enum: 'bronze', 'silver', 'gold'), `criteria_type` (enum: 'quiz_score', 'course_completion', 'path_completion', 'on_time_submissions', 'first_forum_post', 'login_streak'), `criteria_value` (integer), `is_active` (boolean).
13. **badge_student** **[CORE]** (Many to Many): `badge_id` (FK), `student_id` (FK to users), `awarded_at` (datetime). *Unique composite key on (`badge_id`, `student_id`) — enforces no duplicate awards.*
14. **learning_paths** **[CORE]**: `id` (PK), `title` (string), `description` (text), `certificate_template_id` (FK, nullable), `is_active` (boolean).
15. **learning_path_course** **[CORE]** (Many to Many, ordered): `learning_path_id` (FK), `course_id` (FK), `sequence` (integer).
16. **notifications** **[CORE]**: `id` (PK), `user_id` (FK), `type` (string), `message` (string), `link` (string, nullable), `is_read` (boolean, default false), `created_at` (datetime).
17. **notification_preferences** **[CORE]**: `id` (PK), `user_id` (FK), `type` (string), `enabled` (boolean).
18. **settings** **[CORE]**: `id` (PK), `key` (string, unique — e.g. `progress.quiz_weight`), `value` (string). *Stores the admin-configurable progress weighting and the certificate pass threshold.*

### Module 2 — Resources
19. **courses**: `id` (PK), `instructor_id` (FK to users), `code` (string, unique — e.g. `BMIT3173`), `title` (string), `description` (text).
20. **course_student** (Enrollment Pivot - Many to Many): `student_id` (FK), `course_id` (FK).
21. **course_materials**: `id` (PK), `course_id` (FK), `title` (string), `type` (enum: 'lecture', 'tutorial', 'practical', 'other'), `file_path` (string), `is_external` (boolean).
22. **announcements**: `id` (PK), `course_id` (FK, nullable — null means a global announcement), `author_id` (FK to users), `content` (text). *Renamed from `admin_id`: instructors post course announcements, admins post global ones.*

### Module 3 — Forum
23. **discussion_forums**: `id` (PK), `course_id` (FK, unique - 1 to 1), `title` (string).
24. **posts**: `id` (PK), `forum_id` (FK), `user_id` (FK), `content` (text).
25. **replies**: `id` (PK), `post_id` (FK), `user_id` (FK), `content` (text).

### Module 4 — Assessment
26. **quizzes**: `id` (PK), `course_id` (FK), `title` (string), `time_limit` (integer).
27. **questions**: `id` (PK), `quiz_id` (FK), `type` (string: 'mcq', 'multi' or 'text'), `question_text` (text).
28. **answers**: `id` (PK), `question_id` (FK), `answer_text` (string), `is_correct` (boolean).

### Module 5 — Evaluation
29. **assignments**: `id` (PK), `course_id` (FK), `title` (string), `description` (text, nullable), `due_date` (datetime), `allow_late_submission` (boolean, default true).
30. **submissions**: `id` (PK), `assignment_id` (FK), `student_id` (FK), `file_path` (string, nullable), `state` (string: 'draft', 'submitted' or 'graded'), `submitted_at` (datetime, nullable). *Unique composite key on (`assignment_id`, `student_id`).*
31. **quiz_attempts**: `id` (PK), `quiz_id` (FK), `student_id` (FK), `duration_seconds` (integer).
31a. **quiz_attempt_answers** *(added during implementation)*: `id` (PK), `quiz_attempt_id` (FK), `question_id` (FK), `response` (text, nullable), `is_correct` (boolean), `awarded_score` (double). *Unique composite key on (`quiz_attempt_id`, `question_id`).* Without this there is nowhere to record what a student answered, so a quiz cannot be graded or reviewed.
32. **grades**: `id` (PK), `submission_id` (FK, nullable, unique), `quiz_attempt_id` (FK, nullable, unique), `calculated_score` (double).

---

## 4. System Integration Workflow (How it Links)
- **Step 0:** Admin issues an `Invitation` (Mod 1). The recipient registers through the tokenised link — there is no open signup. Admin assigns permissions via the RBAC matrix screen.
- **Step 1:** Instructor logs in and creates a `Course`, uploads `CourseMaterials`, and posts an `Announcement` (Mod 2). Student enrolls.
- **Step 2:** Student studies materials. If confused, Student posts in `DiscussionForum` (Mod 3). The **Observer Pattern** writes to `notifications`; Module 1's inbox surfaces it in the bell dropdown, respecting the user's `notification_preferences`.
- **Step 3:** Instructor creates a `Quiz` (graded via **Strategy Pattern**, Mod 4) and an `Assignment`. Student attempts the quiz (`QuizAttempt`) and uploads an assignment (`Submission` managed by **State Pattern**, Mod 5).
- **Step 4:** The `QuizAttempt` and `Submission` records pass their data to generate a `Grade` (Mod 5).
- **Step 5:** The `Grade` write triggers the **Facade** `CredentialAuthority` (Mod 1), which:
    1. Recalculates `StudentProgress` for that course using the admin-configured weighting and writes a `progress_snapshot`.
    2. Evaluates every active `Badge` rule and awards any newly earned badges.
    3. If the course completion threshold is met, mints a `Certificate` — unique credential ID, integrity hash, DomPDF render with an embedded verification QR code.
    4. If that course was the last outstanding course in a `LearningPath`, additionally mints the pathway certificate.
    5. Writes an `activity_log` entry for each issuance.
- **Step 6:** Anyone — including a party with no account — visits `/verify/{credential_id}` or scans the certificate's QR code to confirm the credential is Valid, Expired or Revoked.

---

## 5. Constraints & Out of Scope (What we DO NOT DO)
**AI INSTRUCTION: STRICTLY ADHERE TO THESE CONSTRAINTS.**
- **NO Peer-Review:** Peer-review was explicitly removed from the final report. Submissions are reviewed directly by Instructors.
- **NO External Payment Gateways:** Courses are free. Do not implement Stripe/PayPal.
- **NO Live Chat/WebSockets:** The forum is asynchronous. Do not implement Socket.io or Laravel Reverb. Use simple database writes for notifications.
- **NO Raw SQL:** Every database query MUST use Eloquent (`Model::where()`, `$user->courses()`).
- **NO third-party credential platforms:** Do not integrate Credly, Accredible or blockchain credentialing. Verification is served entirely by our own `/verify/{credential_id}` route.
- **NO SMS or authenticator-app 2FA:** if 2FA is implemented at all, it is email OTP only.

---

## 6. Prompting Instructions for AI Code Generation
When the human asks you to code a module, output the following in order:
1. **Migrations & Models:** Provide the schema and Eloquent models with all `hasMany`/`belongsTo`/`hasOne`/`belongsToMany` relationships clearly defined according to Section 3.
2. **Design Pattern Implementation:** Provide the exact PHP classes and interfaces for the assigned Design Pattern (Facade, Adapter, Strategy, Observer, or State) located in an `app/Patterns/` namespace. DO NOT cram pattern logic inside Controllers.
3. **Controllers:** Provide the MVC Controller that binds the Models and the Design Pattern together.
4. **Blade Views:** Provide a clean Tailwind/Bootstrap UI structure for the feature.
5. **Seeders:** For Module 1, seed the `permissions` table from the matrix in Section 7, plus at least one `certificate_template` and five sample `badges`, so the system is demonstrable immediately after `migrate:fresh --seed`.

---

## 7. Role-Based Access Control (RBAC) Matrix
**AI INSTRUCTION: These permissions are seeded into the `permissions` table and resolved at runtime through Laravel Gates and Policies. Do NOT hardcode role checks like `if ($user->role === 'admin')` in Controllers — check the permission key instead. Ensure UI elements (like "Create" buttons) are hidden from roles that lack permission.**

### Role 0: Guest (unauthenticated)
**What they CAN do:**
- Visit `/verify/{credential_id}` to check a certificate's authenticity.
- Accept a valid, unexpired `Invitation` token to register.
**What they CANNOT do:**
- Anything else. All other routes require authentication.

### Role 1: Administrator (Admin)
**What they CAN do:**
- Manage system-level configurations and the `settings` table (progress weighting, pass threshold).
- Issue `Invitations`; activate, deactivate, soft-delete and unlock User accounts (Instructors and Students).
- Edit the permission matrix — grant or revoke any permission from any role.
- Create and broadcast global or course-level `Announcements`.
- Create and manage `CertificateTemplates`, `Badges` and `LearningPaths`.
- Manually issue a `Certificate`, and **revoke** any certificate with a stated reason.
- View and export the `ActivityLog`.
- View high-level system analytics.
**What they CANNOT do:**
- Cannot create `CourseMaterials`, `Quizzes`, or `Assignments`.
- Cannot participate in `DiscussionForums`.
- Cannot take quizzes, submit assignments, or receive grades.
- Cannot edit an already-issued certificate's contents — only revoke it (this preserves the integrity hash guarantee).

### Role 2: Instructor
**What they CAN do:**
- Create, edit, and manage `Courses` assigned to them.
- Upload and manage `CourseMaterials` (PDFs, external links).
- Post `Announcements` for their specific courses.
- Monitor `DiscussionForums` and reply to student `Posts`.
- Create `Quizzes` and define `Questions` / `Answers`.
- Create `Assignments`.
- Review student `Submissions` and manually assign a `Grade`.
- View `StudentProgress` and analytics for students enrolled in their courses.
- View (read-only) the certificates issued for their own courses.
- Manage their own profile, avatar and password.
**What they CANNOT do:**
- Cannot create or manage other Instructor/Admin accounts, or issue `Invitations`.
- Cannot edit the permission matrix.
- Cannot create `Badges`, `CertificateTemplates` or `LearningPaths`.
- Cannot issue or revoke a `Certificate` — issuance is automatic via the `CredentialAuthority`.
- Cannot enroll in a course as a student.
- Cannot take quizzes (`QuizAttempt`) or submit files to an `Assignment`.
- Cannot alter courses assigned to other Instructors.

### Role 3: Student
**What they CAN do:**
- Enroll in `Courses` (Many-to-Many).
- View and download `CourseMaterials` for courses they are enrolled in.
- View `Announcements` for their enrolled courses.
- Create `Posts` and `Replies` in the `DiscussionForum`.
- Take a `Quiz` (Generates a `QuizAttempt`).
- Upload files to an `Assignment` (Generates a `Submission`).
- View their own `Grades`, `StudentProgress`, `Badges` and `Certificates`.
- Download their own certificate PDFs and copy the public verification link.
- Manage their own profile, avatar, password and `NotificationPreferences`.
- Receive `Notifications`.
**What they CANNOT do:**
- Cannot access any Instructor or Admin dashboards.
- Cannot create, edit, or delete `Courses`, `CourseMaterials`, or `Announcements`.
- Cannot create `Quizzes` or `Assignments`.
- Cannot view, grade, or modify the submissions/grades of other students.
- Cannot alter their own `Grade`, `StudentProgress`, `Badges` or `Certificates` directly.
- Cannot view another student's certificate list (though they may verify a credential ID publicly, which reveals only holder name, course, score and status).

---

## 8. Module 1 Build Priority
**AI INSTRUCTION: If the human asks "what should I build first for Module 1", follow this order. Items 1–4 are the minimum for a strong demonstration.**

1. **Public verification page + credential ID + QR-embedded PDF** — the single most demonstrable feature; scan the QR on a phone during the presentation.
2. **Badge rules engine + trophy cabinet** — configurable criteria, greyed-out locked badges.
3. **Database-driven permission matrix** — turns Section 7 from documentation into a working admin screen.
4. **Invitation-based registration** — directly answers the criticism that Laravel supplies registration for free.
5. **Activity log with CSV export.**
6. **Learning paths + pathway certificates.**
7. **Certificate revocation** (small once verification exists — one nullable column and one status branch).
8. *(Stretch)* Email OTP 2FA, bulk CSV user import, password history.