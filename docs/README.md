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
| **`AI_BUILD_PROMPT.md`** | The build brief that drove development, including the phase order and the hard rules (Eloquent only, one pattern per module, patterns never inside controllers). |

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

**One new table**

- `quiz_attempt_answers` — Section 3 gives `quiz_attempts` only a duration, leaving nowhere to
  record what a student actually answered. Grading and reviewing an attempt are both impossible
  without it.

**Added columns**

- `courses.code` — a course is identified by its code as much as its name
- `course_materials.title` — a resource list of bare file paths is unusable
- `assignments.description` — an assignment with no brief cannot be acted on
- `assignments.allow_late_submission` — the per-assignment late policy; defaults to accepting late
  work and labelling it
- `submissions.submitted_at` — needed to tell an on-time submission from a late one, which the
  `on_time_submissions` badge rule depends on
- `submissions` composite unique on `(assignment_id, student_id)` — one submission per student per
  assignment

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
  faces no server-side cutoff.
- **`materials_viewed` is always 0** — Section 3 has no view-tracking table, so the participation
  share of progress is measured by forum activity instead.
- **No edit screens** for course materials, quiz questions or announcements — create and delete
  only. Courses and assignments *are* editable.
- **No automated tests for the design patterns.** The 29 passing tests cover authentication,
  invitation-only registration and profiles. Pattern behaviour was verified manually.
