# Project documents

The source documents LearnSync was built from, versioned alongside the code so the
specification and the implementation can never drift apart in the repository.

These are **reference copies**. They are not read at runtime and nothing in `app/` depends on
them.

| File | What it is |
|---|---|
| **`EduSystem.md`** | The specification, and the source of truth. Section 2 assigns each of the five modules its GoF design pattern; Section 2A draws the module boundaries; Section 3 gives the exact schema and marks tables `[CORE]` or `[STRETCH]`; Section 4 gives the end-to-end workflow; Section 5 lists the hard constraints; Section 7 is the RBAC matrix; Section 8 gives the Module 1 build order. |
| **`Laravel Tutorial.pdf`** | BAIT3173 Appendix 3.1, the lecturer's practical. Dictates the coding conventions this project follows: `make:model X -m`, resource controllers bound to a model, resource routes in `routes/web.php`, Blade views extending a shared `layout.blade.php`, `$fillable` on every model, and the snake_case-plural / PascalCase-singular naming rules. Its `ordersystem` / `products` example is replaced throughout by `edusystem` and the real entities from Section 3. |
| **`Design Patterns.txt`** | The GoF catalogue grouped Creational / Structural / Behavioural. Used to confirm each pattern's category when writing the report. |

## How the specification maps onto the code

| Specification | Implementation |
|---|---|
| Section 2 — five modules, one pattern each | `app/Patterns/{Singleton,Adapter,Observer,Strategy,State}` |
| Section 3 — schema | `database/migrations/` and `app/Models/` |
| Section 4 — workflow Step 5 | `CredentialAuthority::handleGradeRecorded()`, triggered by a `Grade::created` event |
| Section 5 — no raw SQL | every query is Eloquent; no `DB::raw()` or `DB::select()` anywhere |
| Section 7 — RBAC matrix | seeded into `permissions` / `permission_role`, resolved by a Gate, editable at `/permissions` |
| Section 8 — Module 1 build order | all seven items built; see the root `README.md` |

## Deviations from Section 3

Documented here so the ERD can be updated before submission.

**Three new tables**

- `quiz_attempt_answers` — Section 3 gives `quiz_attempts` only a duration, leaving nowhere to
  record what a student actually answered. Grading and reviewing an attempt are both impossible
  without it.
- `course_invitations` — Section 3 has `course_student` and nothing else, which makes enrolment a
  thing a student simply does. Section 7 gives them the `course.enroll` permission but says nothing
  about *which* course, so the only implementable reading was a catalogue anyone could enrol from.
  This table restores the instructor's decision: `(course_id, student_id, invited_by, accepted_at)`,
  unique on the first two. A row is not an enrolment — accepting is what writes `course_student`,
  and `accepted_at` separates the two states.
- `announcement_comments` — Section 3 makes an announcement a one-way broadcast, so the obvious
  question about a notice had nowhere to go but a forum thread detached from it.
  `(announcement_id, user_id, body)`, flat and deliberately unthreaded: anything needing a real
  thread belongs in Module 3's forum. Observed by `SystemNotificationObserver`, which makes it the
  Observer pattern's third subject.

**One new permission**

- `announcement.comment`, held by instructor and student. Administrators are excluded for the same
  reason Section 7 keeps them out of forums — they can read and moderate every thread, but they do
  not take part. This brings the matrix to 34 keys. It is inserted by both the seeder and its own
  migration, because a fresh `migrate:fresh --seed` rebuilds the matrix from the seeder while an
  existing database only runs migrations; the seeder uses `updateOrCreate` so the two paths cannot
  collide.

**Added columns**

- `courses.code` — a course is identified by its code as much as its name, e.g. `BMIT3173`
- `courses.class_code` — the six-character join code a student can enrol with unaided. Deliberately
  not `code`: that one is public and guessable, so letting it grant enrolment would make the policy
  above unenforceable. Unique, and regenerating it revokes the previous one.
- `course_materials.title` — a resource list of bare file paths is unusable
- `assignments.description` — an assignment with no brief cannot be acted on
- `assignments.allow_late_submission` — the per-assignment late policy; defaults to accepting late
  work and labelling it "Turned in late"
- `submissions.submitted_at` — needed to tell an on-time submission from a late one, which the
  `on_time_submissions` badge rule depends on
- `submissions` composite unique on `(assignment_id, student_id)` — one submission per student per
  assignment
- `users.school_email` — the institutional address published on a lecturer's contact card, kept
  apart from `email`, which is the login identity and may be personal. Nullable, falling back to
  the account address so a card can never render with no way to make contact.
- `users.phone` and `users.show_phone` — an optional contact number for lecturers. Two columns
  because "no number given" and "a number given but withheld from students" are different states:
  a lecturer can keep one on record without publishing it. Hidden by default.

**Widened enum**

- `course_materials.type` gains `other`, so the course page can show its four fixed sections —
  Lecture notes, Tutorial question, Practical question, Others — and nothing has to be filed under
  a heading it does not belong in.

**New question type**

- `questions.type` accepts `multi` alongside `mcq` and `text`: a multiple-choice question with
  several correct answers, all of which must be selected. How many to select is derived from the
  number of options flagged correct rather than stored, since a question claiming "choose 3" while
  holding two correct answers would be unanswerable.

**Relaxed constraint**

- `badges.icon_path` is nullable — a badge rule must be definable before its artwork exists

**Extra model**

- `PermissionRole` — `permission_role` pivots a permission against a *role enum*, not a roles
  table, so `belongsToMany` has nothing to point at. Giving the pivot a model keeps the admin
  permission grid pure Eloquent instead of reaching for `DB::table()`, which Section 5 forbids.

**Seeder**

- Two certificate templates rather than one, because a pathway certificate has to read "the
  learning path", not "the course".

## Known gaps

Honest accounting of what is not finished, as of the current commit.

- **Email OTP two-factor** — `OtpCode` works and is tested (hashed, single-use, expiring) but is
  **not wired into the login flow**; there is no challenge screen. Marked `[STRETCH]` in Section 3.
- **Quiz time limit is client-side only** — the countdown is JavaScript. A student who disables it
  faces no server-side cutoff, and the duration recorded on the attempt is self-reported.
- **`materials_viewed` is always 0** — Section 3 has no view-tracking table, so the participation
  share of progress is measured by forum activity instead.
- **No edit screens** for course materials, quiz questions, quizzes or announcements — create and
  delete only. Courses, assignments, badges, learning paths and certificate templates *are*
  editable.
- **No automated tests for the design patterns.** The 29 passing tests cover authentication,
  invitation-only registration and profiles. Pattern behaviour was verified by exercising the
  running system rather than in code.
- **The multiple-answer selection count is enforced in the browser**, not the request. The server
  grades whatever arrives rather than rejecting it, so bypassing the form scores accordingly
  instead of erroring — fair, but not the same as a server-side constraint.

## Corrections made during the build

Two defects in the credentialing logic only became visible once fifty students with varied
histories existed, and both are worth knowing about because the first version looked plausible:

- `quizzesPassedIn()` used `certificate.pass_threshold` (80) to decide whether a **quiz** had been
  passed, so on a four-question quiz, three correct out of four was recorded as a failure. Passing
  a quiz is the academic pass mark; earning a certificate is 80% of overall progress. They are
  different bars and are now separated.
- `issueIfEligible()` certified on `completion_percentage` alone, which measures engagement rather
  than achievement. A diligent student who understood little reached the threshold on
  participation and was issued a certificate carrying a **39.67% average**. Completion and a
  passing mark are now both required, so no credential can attest to a failing grade.

A third was a build-configuration fault rather than logic: Tailwind was only scanning
`resources/views`, so class names decided in PHP were purged. `GradeScale::classesFor()` returns
`bg-orange-100` for a D, that class was absent from the compiled stylesheet, and D-grade badges had
been rendering with no background. `./app/**/*.php` is now in the content paths.
