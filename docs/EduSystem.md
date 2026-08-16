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
**AI INSTRUCTION: When asked to code a specific module, you MUST incorporate the assigned Design Pattern into the Laravel architecture (e.g., create an `app/Patterns/` directory).**

### Module 1: Identity, Access & Digital Credentialing
*   **Owner:** Serena Lim Sze Kee
*   **Entities Managed:** User, Instructor, Student, Administrator, Permission, Invitation, ActivityLog, StudentProgress, Badge, Certificate, CertificateTemplate, LearningPath, NotificationPreference.
*   **Assigned Pattern:** **Singleton Pattern (Creational)**

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
*   **Issuance** — when a student satisfies a course's completion criteria, the Singleton mints a `Certificate` bearing a globally unique human-readable **credential ID** (format: `LS-{YEAR}-{8 CHAR BASE32}`, e.g. `LS-2026-A7F3D9K2`), renders the template to PDF via DomPDF, and stores the file path.
*   **Public verification** — `GET /verify/{credential_id}` is an unauthenticated route. It displays the holder's name, course, score, issue date and status (**Valid / Expired / Revoked**). A QR code encoding this URL is embedded in every generated PDF, so the certificate can be verified by scanning it with a phone.
*   **Integrity hash** — on issuance the system stores `SHA-256(student_id | course_id | score | issued_at | credential_id)`. The verification page recomputes and compares this hash, proving the record has not been tampered with in the database.
*   **Revocation** — an Administrator may revoke a certificate with a stated reason; the verification page immediately reports `REVOKED` and the PDF download is disabled.
*   **Learning Paths** — a `LearningPath` is an ordered collection of courses (e.g. "Web Development Pathway" = HTML → PHP → Laravel). Completing every course in the path issues a higher-tier **pathway certificate** in addition to the individual course certificates.

#### 1D. Badge Rules Engine
*   `Badge` records are **rules configured by an Administrator**, never hardcoded: `criteria_type` (`quiz_score`, `course_completion`, `path_completion`, `on_time_submissions`, `first_forum_post`, `login_streak`), `criteria_value`, `tier` (bronze / silver / gold) and an icon.
*   After any grade event, the Singleton evaluates all active badge rules for that student and awards any newly satisfied badges.
*   The student profile renders a **trophy cabinet**: earned badges in colour, unearned badges greyed out with their unlock condition displayed ("Score 90% or higher on any quiz").

#### 1E. Notification Inbox
*   Module 3 **produces** notification events. Module 1 **owns the inbox**: the navbar bell with unread count, the notification history page, mark-as-read / mark-all-read, and per-user `NotificationPreference` rows controlling which notification types the user receives.

#### Design Pattern Implementation — Singleton
Create `app/Patterns/Singleton/CredentialAuthority.php`, bound in a service provider via `$this->app->singleton(CredentialAuthority::class, ...)`.

**Justification (use this wording in the report):** The `CredentialAuthority` models a real-world *certificate authority*. Only one authority may exist in the system, because it is the sole issuer of credential IDs and the sole arbiter of whether a student has already been credentialed for a given course. If two instances existed concurrently — for example when a grade event and a manual admin issuance fire at the same time — they could mint duplicate credential IDs or issue two certificates for the same achievement, destroying the uniqueness guarantee that public verification depends upon. The Singleton also loads the badge rule registry once and holds it in memory for the lifetime of the request, so the rule set is evaluated from one consistent source.

The class exposes: `issueCertificate(Student, Course)`, `issuePathwayCertificate(Student, LearningPath)`, `revoke(Certificate, string $reason)`, `verify(string $credentialId)`, `evaluateBadges(Student)` and `recalculateProgress(Student, Course)`.

> **Do NOT** justify this Singleton as "preventing memory bloat" — Laravel's service container already manages object lifetimes, and a marker will challenge that reasoning. The uniqueness-of-issuing-authority argument above is the defensible one.

---

### Module 2: Academic Resources Repository Module
*   **Owner:** Foo Chong Xian
*   **Entities Managed:** Course, CourseMaterial, Announcement.
*   **Functions:** Manages the `Course` hub. Instructors upload `CourseMaterial` (Lecture Notes, Tutorials, Practicals) and broadcast `Announcements`. Students browse resources for courses they are enrolled in.
*   **Assigned Pattern:** **Adapter Pattern (Structural)**
    *   *Implementation:* Instructors can upload standard internal files (PDFs) OR link to external resources (e.g., YouTube video links). Create an `ExternalResourceAdapter` that wraps external links into a standard `DisplayableMaterial` interface so the Blade view displays both uniformly.

### Module 3: Student Forum & Notification System
*   **Owner:** Ong Shun Yan
*   **Entities Managed:** DiscussionForum, Post, Reply.
*   **Functions:** A course-specific Q&A Discussion Forum. When a student posts a question or reply, the system utilizes an API-driven notification service to alert the relevant instructor or student.
*   **Assigned Pattern:** **Observer Pattern (Behavioral)**
    *   *Implementation:* The `Post` entity is the *Subject*. Create a `SystemNotificationObserver` as the *Observer*. When a Post is saved (`notify()`), the observer automatically triggers to write an alert to the `notifications` table for the target user.

### Module 4: Skill Assessment & Quiz
*   **Owner:** Wong Siew Lam
*   **Entities Managed:** Quiz, Question, Answer.
*   **Functions:** Instructors create `Quizzes` with dynamic `Questions` (Multiple Choice or Fill-in-the-blank). The system automatically evaluates student answers using an automated grading engine.
*   **Assigned Pattern:** **Strategy Pattern (Behavioral)**
    *   *Implementation:* Create a `GradingStrategy` interface. Implement `MCQGradingStrategy` (checks specific correct answer choices) and `TextMatchGradingStrategy` (checks string similarity for fill-in-the-blanks). The controller swaps strategies dynamically based on the question type.

### Module 5: Academic Progress Analytics & Dashboard
*   **Owner:** Ong Kwong Wei
*   **Entities Managed:** Assignment, Submission, QuizAttempt, Grade.
*   **Functions:** Manages the evaluation and grading lifecycle. Handles file-based `Assignments` and student `Submissions`. It captures `QuizAttempts` and processes marks into an authoritative `Grade` record.
*   **Assigned Pattern:** **State Pattern (Behavioral)**
    *   *Implementation:* A `Submission` goes through states: `DraftState` (student can edit/re-upload), `SubmittedState` (locked from edits, waiting for review), and `GradedState` (grade generated). The state object dictates whether the `updateFile()` or `assignGrade()` methods are allowed.

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
- **Step 5:** The `Grade` write triggers the **Singleton** `CredentialAuthority` (Mod 1), which:
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
2. **Design Pattern Implementation:** Provide the exact PHP classes and interfaces for the assigned Design Pattern (Singleton, Adapter, Strategy, Observer, or State) located in an `app/Patterns/` namespace. DO NOT cram pattern logic inside Controllers.
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