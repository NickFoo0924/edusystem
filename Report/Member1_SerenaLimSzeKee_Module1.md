# BMIT3173 Integrative Programming — Assignment 202605

**Student Name:** Serena Lim Sze Kee
**Student ID:** _[fill in]_
**Programme:** _[fill in]_
**Tutorial Group:** _[fill in]_
**System Title:** LearnSync — Integrated Educational Resource and Collaborative Learning Portal
**Chosen SDG:** SDG 4 — Quality Education
**Modules:** Module 1 — Identity, Access & Digital Credentialing

---

## AI Usage Disclosure Form

**Declaration:** ☑ AI tools were used as declared in the table below.

| AI Tool Used (name & version) | Purpose / How It Was Used | Report Section(s) Affected |
|---|---|---|
| Anthropic Claude (Opus 5) | Drafting and structuring of report prose from the delivered codebase | 1, 2, 3, 4, 5, 6 |
| Anthropic Claude (Opus 5) | Refactoring the Module 1 design pattern from Singleton to Facade, and generating the subsystem classes | 4 |
| Anthropic Claude (Opus 5) | Explaining design-pattern concepts and secure-coding techniques | 4.1, 5.2 |

> **⚠ Read before submitting.** This assignment is classified **YELLOW (Limited AI)**. The policy
> prohibits AI from *"producing core arguments or analysis, including the design-pattern
> justification, threat analysis, and secure-coding rationale"* and from *"generating the PHP code,
> design-pattern implementation … or web-service code being assessed."* The table above discloses
> that honestly. **The strongest thing you can do is rewrite Sections 4.2, 5.1 and 5.2 in your own
> words** — the underlying facts are all true of your code, so restating the reasoning yourself is
> genuinely achievable and moves those sections back inside the Yellow conditions. The lecturer may
> ask you to explain or demonstrate any part.

**Sign:** ______________________  **Date:** ______________

---

## 1. Introduction to the System

LearnSync is a Learning Management System built on Laravel 12 and PHP 8.2. Instructors publish
course materials, set quizzes and assignments, and mark submitted work; students study the
materials, sit assessments, track their progress, and take part in course discussion forums. What
distinguishes LearnSync from a conventional LMS is **verifiable digital credentialing**: completing
a course issues the student a certificate carrying a globally unique credential ID and a QR code
that **any third party can verify publicly, without an account and at no cost**.

### The Sustainable Development Goal

The system addresses **SDG 4: Quality Education**, which aims to *"ensure inclusive and equitable
quality education and promote lifelong learning opportunities for all"* (United Nations, 2015).
Two of its targets are directly relevant: **Target 4.3**, on equal access to affordable technical
and tertiary education, and **Target 4.4**, on increasing the number of people with relevant skills
for employment and decent work.

### How the system contributes

A qualification is only useful if it can be trusted by somebody who was not there when it was
earned. Conventional certificates are a PDF that can be edited in a word processor; verifying one
means contacting the institution and waiting.

LearnSync closes that gap. Every credential it issues carries:

- a unique, human-readable credential ID in the format `LS-{YEAR}-{8-CHARACTER BASE32}`, for example
  `LS-2026-A7F3D9K2`;
- a **SHA-256 integrity hash** computed over the credential's own contents, recomputed on every
  verification, so altering the underlying record is detected and reported;
- a **QR code** printed on the PDF, which resolves to the public verification page.

**Target users and beneficiaries:**

| Beneficiary | Contribution |
|---|---|
| **Students** | Earn credentials that a third party can independently confirm, at no cost |
| **Instructors** | Publish materials and assess work without administering credential issuance manually |
| **Administrators** | Configure award rules, permissions and progress weightings as data rather than code |
| **Employers and external verifiers** | Confirm a qualification in seconds, with **no account required**, by visiting `/verify/{credential_id}` or scanning the QR code |

The scope of the contribution is deliberately bounded. LearnSync does not attempt to replace formal
accreditation; it makes the credentials a course already issues **independently checkable**, which
removes the verification cost that discourages small employers from confirming qualifications at
all.

---

## 2. Module Description

**Module 1 — Identity, Access & Digital Credentialing** owns who may enter the system, what each
role may do once inside, and the credentials a student earns on the way out. It is the module every
other module depends on for authorisation, and the one that turns Module 5's grades into something
externally meaningful.

The module is presented below by function.

### 2.1 Invitation-based registration

Public self-registration is **disabled**. Visiting `/register` returns HTTP 404. An account exists
only when an administrator issues an `Invitation` carrying the recipient's email, their intended
role, an expiring token, and the issuing administrator's id. The recipient registers solely through
that tokenised link.

The controller refuses a token that is unknown, already redeemed, expired, or whose email address
already has an account. The role is taken **from the invitation**, never from the registration form,
so a recipient cannot promote themselves by tampering with the submitted data.

- **Class path:** `app/Http/Controllers/Auth/InvitedRegistrationController.php`
- **Model:** `app/Models/Invitation.php`
- **Views:** `resources/views/invitations/index.blade.php`, `resources/views/auth/register-invited.blade.php`

> **📷 Screenshot 1** — the Invitations screen showing an issued invitation and its tokenised link.
> *Class path: `resources/views/invitations/index.blade.php`*
>
> **📷 Screenshot 2** — the invited-registration form, showing the role fixed by the invitation.
> *Class path: `resources/views/auth/register-invited.blade.php`*

### 2.2 Database-driven permission matrix (RBAC)

The role-based access control rules are **rows in the database**, not hardcoded conditionals. The
system currently holds **35 permission keys** across eight groups: Course Management, User & Access
Management, System Configuration, Assessment, Forum, Credentialing, Progress and Profile.

A permission is granted to a role through the `permission_role` pivot. Code asks
`$user->can('certificate.revoke')`, and a Laravel **Gate** registered in `AppServiceProvider`
resolves that key against the database:

```php
// app/Providers/AppServiceProvider.php
private function registerPermissionGate(): void
{
    Gate::before(function (User $user, string $ability) {
        // A deactivated or locked-out account can do nothing at all.
        if (! $user->is_active) {
            return false;
        }

        return $user->permissions()->contains('key', $ability) ? true : null;
    });
}
```

There is **no `if ($user->role === 'admin')` anywhere in the application**. An administrator retunes
the entire matrix from the Permissions screen and it takes effect on the next request, with no code
change and no redeployment.

- **Class path:** `app/Http/Controllers/PermissionController.php`, `app/Providers/AppServiceProvider.php`
- **Models:** `app/Models/Permission.php`, `app/Models/PermissionRole.php`
- **View:** `resources/views/permissions/index.blade.php`

> **📷 Screenshot 3** — the permission matrix checkbox grid.
> *Class path: `resources/views/permissions/index.blade.php`*

### 2.3 Account lifecycle and login security

Administrators activate, deactivate, soft-delete and unlock accounts. Supporting controls:

- **Login throttling** — five consecutive failed attempts lock the account; **only an administrator
  can clear the lock**.
- **Password history** — a new password may not match any of the previous three.
- **Forced password change** on first login, enforced by middleware on every request.
- **Confirmation gate** — every destructive account action requires the administrator to re-enter
  *their own* password on a separate confirmation page, so no single click alters an account.

- **Class path:** `app/Http/Controllers/UserController.php`
- **Middleware:** `app/Http/Middleware/EnsureAccountIsActive.php`, `app/Http/Middleware/EnsurePasswordIsChanged.php`

> **📷 Screenshot 4** — the Accounts screen and the password confirmation page.
> *Class path: `resources/views/users/index.blade.php`, `resources/views/users/confirm.blade.php`*

### 2.4 Audit trail

Every security-relevant action writes an `ActivityLog` row recording the **actor, action, target,
IP address and user agent**. Actions currently recorded include `auth.login`, `auth.logout`,
`auth.failed`, `invitation.issued`, `user.registered`, `certificate.issued`,
`certificate.pathway_issued`, `certificate.revoked` and `course.student_removed`.

Authentication events are captured by listening to Laravel's own `Login`, `Logout` and `Failed`
events rather than by editing the login controller, so **any future authentication path is covered
automatically**. Administrators can filter the log and export it to CSV.

- **Class path:** `app/Http/Controllers/ActivityLogController.php`, `app/Models/ActivityLog.php`

> **📷 Screenshot 5** — the activity log with filters and the CSV export control.
> *Class path: `resources/views/activity_logs/index.blade.php`*

### 2.5 Progress tracking

`StudentProgress` is tracked **per student per course**, never as a single global percentage.
Completion is a weighted composite of quizzes passed, assignments submitted and forum
participation. The weighting (quiz 50% / assignment 40% / participation 10% by default) is read from
the `settings` table and is **administrator-configurable**, not a constant in code.

Each recalculation writes a `ProgressSnapshot` row, which is what allows the student dashboard to
render a progress-over-time line chart.

- **Class path:** `app/Patterns/Facade/Subsystem/ProgressCalculator.php`

> **📷 Screenshot 6** — the student dashboard progress chart.
> *Class path: `resources/views/dashboard/student.blade.php`*

### 2.6 Digital credentialing — the module's centrepiece

When a student satisfies a course's completion criteria, the `CredentialAuthority` mints a
`Certificate`:

1. A globally unique credential ID, `LS-{YEAR}-{8 CHAR BASE32}`, drawn from a **Crockford Base32**
   alphabet with I, L, O and U removed so a printed ID cannot be misread between `1/I/L` or `0/O`.
2. A **SHA-256 integrity hash** over `student_id | course_id | score | issued_at | credential_id`.
3. A PDF rendered from an administrator-designed template through DomPDF, with a **QR code**
   embedded that encodes the public verification URL.
4. An `ActivityLog` entry.

**Two independent gates must both pass before issuance**, which is a deliberate design decision:

- completion percentage ≥ the configured threshold (default 80%) — this measures *engagement*; and
- the student's **average mark is a passing grade** — this measures *achievement*.

Completion alone was insufficient: a diligent student who understood little could reach 80% on
participation, which produced certificates attesting to a failing average. A verifiable credential
must never assert that.

**Public verification** — `GET /verify/{credential_id}` is unauthenticated and reports one of five
states: `valid`, `revoked`, `tampered`, `expired`, or `not_found`. **Revocation** allows an
administrator to withdraw a credential with a stated reason; the public page immediately reports
`REVOKED` and the PDF download is refused.

**Learning paths** — an ordered collection of courses. Completing every course in a path issues a
higher-tier pathway certificate in addition to the individual ones.

- **Class path:** `app/Patterns/Facade/CredentialAuthority.php`, `app/Http/Controllers/CertificateController.php`

> **📷 Screenshot 7** — the generated certificate PDF showing the credential ID and QR code.
> **📷 Screenshot 8** — the public verification page reporting VALID, in a logged-out browser.
> *Class path: `resources/views/certificates/verify.blade.php`*
> **📷 Screenshot 9** — the same page reporting TAMPERED after a score is edited directly in the database.

### 2.7 Award rules engine (badges and certificates)

Badges and certificate rules are **configured by an administrator, never hardcoded**. A rule row
carries a name, description, award type (badge or certificate), tier, icon or certificate template,
a condition type, its threshold value, an optional subject scope, and an active flag.

Nine parameterised condition types are supported: `course_completion`, `path_completion`,
`quiz_score`, `on_time_submissions`, `first_forum_post`, `login_streak`, `all_quizzes_in_course`,
`average_score_in_course` and `quizzes_completed`.

This is deliberately **not** a scripting engine. An administrator selects a condition and fills in
its number; they never write an expression. That trade-off is intentional: no rule an administrator
can create is able to error, loop, or read data they should not see.

The student profile renders a **trophy cabinet** — earned badges in colour, unearned badges greyed
out with the condition that would unlock them.

- **Class path:** `app/Patterns/Facade/Subsystem/AwardConditionEvaluator.php`, `app/Http/Controllers/BadgeController.php`

> **📷 Screenshot 10** — the Award Rules admin screen.
> **📷 Screenshot 11** — the student trophy cabinet with earned and locked badges.
> *Class path: `resources/views/badges/cabinet.blade.php`*

### 2.8 Notification inbox

Module 3 **produces** notification events; Module 1 **owns the inbox** — the navbar bell with its
unread count, the notification history page, mark-as-read and mark-all-read, and per-user
`NotificationPreference` rows controlling which notification types each user receives.

- **Class path:** `app/Http/Controllers/NotificationController.php`

> **📷 Screenshot 12** — the notification bell with an unread badge, and the preferences screen.

---

## 3. Entity Classes

### 3.1 Entity class diagram

> **📷 Figure 1** — Entity class diagram for LearnSync.
>
> **⚠ You must draw this.** An entity *class* diagram is **not** an ERD: show classes with their
> **attributes and object references**, not tables with foreign keys. Draw it in draw.io, Visual
> Paradigm or StarUML and export as PNG.

The classes owned by Module 1, with their relationships expressed as **object references**:

```
User
  - id: int
  - name: string
  - email: string
  - schoolEmail: string
  - password: string
  - role: enum {admin, instructor, student}
  - avatarPath: string
  - bio: text
  - isActive: bool
  - failedLoginAttempts: int
  - lockedUntil: DateTime
  - mustChangePassword: bool
  - lastLoginAt: DateTime
  ── certificates:   Certificate[0..*]
  ── badges:         Badge[0..*]
  ── studentProgress: StudentProgress[0..*]
  ── notifications:  Notification[0..*]
  ── activityLogs:   ActivityLog[0..*]

Certificate
  - id: int
  - credentialId: string          // LS-2026-A7F3D9K2
  - finalScore: double
  - integrityHash: string          // SHA-256
  - pdfPath: string
  - issuedAt: DateTime
  - expiresAt: DateTime
  - revokedAt: DateTime
  - revocationReason: string
  ── student:  User[1]             // object reference, not student_id
  ── course:   Course[0..1]
  ── learningPath: LearningPath[0..1]
  ── template: CertificateTemplate[1]

Badge                              // an award rule
  - id, name, description
  - awardType: enum {badge, certificate}
  - tier: enum {bronze, silver, gold}
  - criteriaType: string
  - criteriaValue: int
  - isActive: bool
  ── course:   Course[0..1]        // subject scope
  ── students: User[0..*]          // awarded to

StudentProgress                    ProgressSnapshot
  - materialsViewed: int             - completionPercentage: double
  - quizzesPassed: int               - capturedAt: DateTime
  - assignmentsSubmitted: int        ── progress: StudentProgress[1]
  - completionPercentage: double
  - lastCalculatedAt: DateTime
  ── student: User[1]
  ── course:  Course[1]
  ── snapshots: ProgressSnapshot[0..*]

Permission          PermissionRole        Invitation           ActivityLog
  - key: string       - role: enum          - email, token       - action: string
  - label: string     ── permission:        - expiresAt          - targetType, targetId
  - group: string        Permission[1]      - acceptedAt         - ipAddress, userAgent
                                            ── invitedBy: User[1] ── user: User[0..1]

LearningPath        CertificateTemplate   Notification         NotificationPreference
  - title             - name                - type, message      - type: string
  - description       - backgroundPath      - link, reference    - enabled: bool
  - isActive          - signaturePath       - isRead: bool       ── user: User[1]
  ── courses:         - bodyText            ── user: User[1]
     Course[1..*]     - isActive
     {ordered}
```

**Note on the implementation.** In Laravel, the object reference is expressed as an Eloquent
relationship method, and the foreign-key column is an implementation detail of the mapping. For
example, `Certificate` does **not** expose `student_id` as its interface — it exposes `student`:

```php
// app/Models/Certificate.php
public function student(): BelongsTo
{
    return $this->belongsTo(User::class, 'student_id');
}
```

Calling `$certificate->student` returns a `User` **object**, and `$certificate->student->name` reads
the holder's name by navigating the reference. This is consistent with the diagram above.

---

## 4. Design Pattern

### 4.1 Description of Design Pattern

**Pattern: Facade (Structural, Gang of Four)**

The Facade pattern *"provides a unified interface to a set of interfaces in a subsystem. Facade
defines a higher-level interface that makes the subsystem easier to use"* (Gamma, Helm, Johnson &
Vlissides, 1994).

A subsystem is often a group of classes that must be used together in a particular order, each
handling one part of a task. Any client wanting the task done must know which classes exist, how to
construct them, and the order to call them. That knowledge leaks the subsystem's internal structure
into every caller, and any change to the subsystem forces a change in all of them.

A Facade is a single class that sits in front of the subsystem and offers a small vocabulary
describing **what the client wants**, rather than **how it is achieved**. The client talks only to
the Facade.

Two properties distinguish Facade from patterns it is often confused with:

- **A Facade does not seal the subsystem off.** Each collaborator remains directly usable and
  independently testable. The Facade adds a simpler entry point; it does not remove the harder one.
  This separates it from **Proxy**, which controls access to a single object.
- **A Facade defines a new, simpler interface.** This separates it from **Adapter**, which converts
  an existing interface into a different *expected* one without simplifying it. (Adapter is the
  pattern Module 2 implements, and the distinction is worth stating: Adapter makes two mismatched
  things look alike; Facade makes many things look like one.)

> **Note on Singleton.** Module 1 originally used the **Singleton** pattern. The assignment
> specification states that *"Singleton and MVC design patterns are NOT counted as one of the chosen
> design patterns"*, so it was refactored to Facade. The refactor changed only how the object is
> constructed and what it delegates to — **no public method signature and no feature behaviour
> changed**.

### 4.2 Implementation of Design Pattern

#### Class diagram

> **📷 Figure 2** — Class diagram of the Facade implementation. **You must draw this.** Structure:

```
        ┌──────────────────────────────┐
        │   CertificateController      │   ← CLIENT
        │  (and Grade::created event)  │
        └──────────────┬───────────────┘
                       │ depends only on the Facade
                       ▼
   ┌───────────────────────────────────────────────┐
   │           «Facade»  CredentialAuthority       │
   ├───────────────────────────────────────────────┤
   │ + issueCertificate(User, Course, ?float): Certificate
   │ + issuePathwayCertificate(User, LearningPath): Certificate
   │ + revoke(Certificate, string): Certificate    │
   │ + verify(string): array                       │
   │ + evaluateBadges(User): Collection            │
   │ + recalculateProgress(User, Course): StudentProgress
   │ + handleGradeRecorded(Grade): array           │
   └───┬────────┬────────┬─────────┬─────────┬─────┘
       │        │        │         │         │      delegates to
       ▼        ▼        ▼         ▼         ▼
 ┌──────────┐┌────────┐┌─────────┐┌────────┐┌──────────────────┐
 │Credential││Integrity││Certificate││Progress││ BadgeRule       │
 │IdGenerator││Hasher  ││Renderer  ││Calculator││ Evaluator      │
 ├──────────┤├────────┤├─────────┤├────────┤├──────────────────┤
 │+generate ││+hash    ││+render   ││+recalculate│+evaluate      │
 │          ││+matches ││+verifica-││+averageScoreIn│           │
 │          ││         ││ tionQrCode││+passThreshold│            │
 └──────────┘└────────┘└─────────┘└────────┘└────────┬─────────┘
                                                      │
                                                      ▼
                                          ┌───────────────────────┐
                                          │ AwardConditionEvaluator│
                                          │ + isSatisfied(User,    │
                                          │              Badge)    │
                                          └───────────────────────┘
                    ↑ THE SUBSYSTEM — five collaborators,
                      four third-party libraries
```

#### The subsystem the Facade hides

Issuing one credential is not one operation. It requires:

| # | Step | Collaborator |
|---|---|---|
| 1 | Mint a collision-free human-readable credential ID | `CredentialIdGenerator` |
| 2 | Seal the record with a SHA-256 integrity hash | `IntegrityHasher` |
| 3 | Substitute placeholders into the administrator's template | `CertificateRenderer` |
| 4 | Render that template to PDF through **DomPDF** | `CertificateRenderer` |
| 5 | Generate and embed a **QR code** encoding the verification URL | `CertificateRenderer` |
| 6 | Write the document to a private disk | `CertificateRenderer` |
| 7 | Recalculate weighted progress against configurable settings | `ProgressCalculator` |
| 8 | Snapshot that progress for the student's chart | `ProgressCalculator` |
| 9 | Evaluate every active award rule | `BadgeRuleEvaluator` → `AwardConditionEvaluator` |
| 10 | Check whether the course completed a learning path | `CredentialAuthority` |
| 11 | Write the audit trail | `ActivityLog` |

That is **five collaborators and four third-party libraries**. The Facade reduces all of it to one
method call:

```php
// app/Http/Controllers/CertificateController.php — the CLIENT
public function __construct(private CredentialAuthority $authority)
{
}

public function store(Request $request): RedirectResponse
{
    abort_unless($request->user()->can('certificate.issue'), 403);
    // ... validation ...
    $certificate = $this->authority->issueCertificate($student, $course, (float) $data['final_score']);
    // ...
}
```

`CertificateController` imports **neither DomPDF, nor the QR encoder, nor the settings table, nor
the badge rules**. It knows one object and three verbs.

Inside the Facade, the orchestration is explicit but hidden from the client:

```php
// app/Patterns/Facade/CredentialAuthority.php
class CredentialAuthority
{
    public function __construct(
        private CredentialIdGenerator $ids,
        private IntegrityHasher $hasher,
        private CertificateRenderer $renderer,
        private ProgressCalculator $progress,
        private BadgeRuleEvaluator $badges,
        private AwardConditionEvaluator $conditions,
    ) {
    }

    public function issueCertificate(User $student, Course $course, ?float $finalScore = null,
                                     ?Badge $rule = null): Certificate
    {
        // ... duplicate-credential and template checks ...

        return DB::transaction(function () use ($student, $course, $template, $score) {
            $credentialId = $this->ids->generate();                       // subsystem 1
            $issuedAt = now();

            $certificate = Certificate::create([
                'credential_id'  => $credentialId,
                'integrity_hash' => $this->hasher->hash(                  // subsystem 2
                    $student->id, $course->id, $score,
                    $issuedAt->format('Y-m-d H:i:s'), $credentialId
                ),
                'pdf_path'       => $this->renderer->pdfPathFor($credentialId),
                // ...
            ]);

            $this->renderer->render($certificate);                        // subsystem 3
            ActivityLog::record('certificate.issued', $certificate);
            $this->issueCompletedPathways($student, $course);
            $this->evaluateBadges($student);                              // subsystem 5

            return $certificate;
        });
    }
}
```

#### Construction: dependency injection, not a static accessor

The Facade is registered in a service provider and **injected**:

```php
// app/Providers/CredentialServiceProvider.php
public function register(): void
{
    $this->app->scoped(CredentialAuthority::class);
}
```

`scoped()` gives one instance per HTTP request, which is **container lifetime management, not the
Singleton pattern**: the constructor is public, there is no static state, and no global accessor
exists. A test can construct as many independent authorities as it likes.

#### Justification for choosing Facade

**1. The subsystem is real, not manufactured.** The eleven steps above are all genuinely required to
issue one credential, and they involve five distinct responsibilities and four third-party
libraries. Facade is the pattern the GoF catalogue defines for exactly this situation. The
alternative — letting `CertificateController` orchestrate DomPDF, the QR encoder, the hashing and
the settings table itself — would put infrastructure knowledge into a controller, which the MVC
architecture this project follows explicitly forbids.

**2. It gives clients a stable interface over a volatile subsystem.** The subsystem changed
substantially during development: PDF rendering, the badge engine and the progress calculation were
all rewritten, and an entire sixth collaborator (`AwardConditionEvaluator`) was added when award
rules became administrator-configurable. **Not one client changed**, because
`issueCertificate()`, `revoke()` and `verify()` kept their signatures. That is the pattern paying
for itself, observable in the project's own history.

**3. It does not over-restrict.** Each collaborator remains independently usable and unit-testable.
This matters because the previous implementation — a Singleton — did the opposite: it made a second
instance *impossible*, which prevented isolated testing without providing anything in return.

**4. The uniqueness argument that motivated the original Singleton does not survive scrutiny, and
Facade loses nothing by dropping it.** The Singleton was justified on the grounds that two
concurrent instances could mint duplicate credential IDs. In PHP this is **false**: each HTTP
request is a separate process with its own memory, so two simultaneous issuances were *always* two
separate objects. The Singleton never provided any cross-request guarantee. What actually prevents
duplicate credential IDs is the **unique database index on `certificates.credential_id`** combined
with the collision-retry loop in `CredentialIdGenerator` — both of which are untouched by the
refactor.

**5. It is distinct from every other member's pattern.** Module 2 uses Adapter (structural, but
converts rather than simplifies), Module 3 Observer, Module 4 Strategy, Module 5 State. No
duplication, as the assignment requires.

---

## 5. Software Security

### 5.1 Potential Threat/Attack

#### Threat 1 — Credential forgery and stored-record tampering

The verification page is the entire value of the credentialing feature: an employer trusts it
because it is served by the issuing institution. That makes it the highest-value target in the
system, and it can be attacked from two directions.

**Attack vector A — direct record manipulation.** The certificate's score, holder and course are
rows in a MySQL database. An attacker with database access — a compromised phpMyAdmin session,
stolen database credentials, SQL injection elsewhere in the application, or a **malicious insider
such as a student assistant with database privileges** — can simply `UPDATE` a certificate row to
raise `final_score` from 45 to 95. Nothing in the HTTP layer is involved, so no amount of
request-side validation detects it. The verification page would then faithfully report a fraudulent
credential as genuine, and the institution would be publicly vouching for it.

**Attack vector B — credential ID forgery.** If credential IDs were sequential or predictable
(`LS-2026-00001`, `LS-2026-00002`), an attacker could enumerate the space to harvest other people's
credentials, or fabricate a plausible-looking ID and print it on a forged paper certificate,
gambling that a verifier reads the ID rather than scanning the QR code.

This maps to the OWASP Secure Coding Practices categories **Cryptographic Practices** and **Data
Protection**, and to **A02:2021 Cryptographic Failures** and **A08:2021 Software and Data Integrity
Failures** in the OWASP Top Ten.

#### Threat 2 — Brute-force and credential-stuffing attack on authentication

Module 1 owns the login. It is the boundary protecting every other module, and it is exposed to the
open internet.

**Attack vector A — brute force.** An automated tool submits password guesses against a known
account. Academic email addresses are highly predictable, so the attacker already knows valid
usernames. Without a lockout, an attacker can attempt thousands of passwords; common ones fall in
minutes.

**Attack vector B — credential stuffing.** Far more effective in practice. The attacker takes
username/password pairs leaked from an unrelated breach and replays them here, exploiting password
reuse. Because each pair is tried only once or twice per account, naive rate limiting on a single
account may not trigger.

The consequences are severe and role-dependent: a compromised **student** account allows submission
of work and viewing of grades; a compromised **instructor** account allows grade alteration, which
feeds the credentialing chain; a compromised **administrator** account allows permission
modification, manual certificate issuance and revocation — a total compromise of the system's trust
model.

This maps to OWASP **Authentication and Password Management**, and to **A07:2021 Identification and
Authentication Failures**.

### 5.2 Secure Coding Practice

> Input validation is applied throughout the module (Laravel's `validate()` on every write, with
> `mimes:` and `max:` rules on uploads) but is **not** claimed as one of the two practices below, as
> the assignment requires.

#### Practice 1 — Cryptographic integrity hashing with timing-safe comparison *(mitigates Threat 1)*

**Category: Cryptographic Practices.** Rather than trusting the database record, every credential is
sealed with a cryptographic hash computed at issuance and **recomputed at every verification**.

On issuance, a SHA-256 digest is taken over the credential's own contents:

```php
// app/Patterns/Facade/Subsystem/IntegrityHasher.php
public function hash(int $studentId, ?int $courseId, float $score,
                     string $issuedAt, string $credentialId): string
{
    return hash('sha256', implode('|', [
        $studentId,
        $courseId ?? '',
        $score,
        $issuedAt,
        $credentialId,
    ]));
}
```

At verification, the digest is recomputed from the row as it currently stands and compared with the
stored value:

```php
public function matches(Certificate $certificate): bool
{
    $recomputed = $this->hash(
        $certificate->student_id,
        $certificate->course_id,
        $certificate->final_score,
        $certificate->issued_at->format('Y-m-d H:i:s'),
        $certificate->credential_id
    );

    return hash_equals($certificate->integrity_hash, $recomputed);
}
```

**Why this defeats Attack A.** The attacker who edits `final_score` in the database changes the
input to the hash. The recomputed digest no longer matches the stored one, and the verification page
reports **TAMPERED** rather than VALID. To forge successfully the attacker would have to compute a
matching SHA-256 digest — a preimage attack that is computationally infeasible.

`hash_equals()` is used deliberately in place of `===`. It performs a **constant-time** comparison,
so an attacker cannot infer the correct digest byte-by-byte by measuring how long the comparison
takes — a timing side-channel that a naive string comparison would expose.

**Why this defeats Attack B.** Credential IDs are generated from a **cryptographically secure**
pseudo-random source, not a sequence:

```php
// app/Patterns/Facade/Subsystem/CredentialIdGenerator.php
private const BASE32_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

private function randomBase32(int $length): string
{
    $alphabetLastIndex = strlen(self::BASE32_ALPHABET) - 1;
    $output = '';

    for ($i = 0; $i < $length; $i++) {
        $output .= self::BASE32_ALPHABET[random_int(0, $alphabetLastIndex)];
    }

    return $output;
}
```

`random_int()` draws from the operating system's CSPRNG, unlike `rand()` or `mt_rand()` which are
predictable from observed output. With a 32-character alphabet over 8 positions the space is
32⁸ ≈ **1.1 × 10¹²**, making enumeration impractical, and issued IDs are a vanishingly small
fraction of it.

The design also **deliberately excludes `revoked_at` from the hash**, so that revocation remains
possible after issuance without invalidating the seal — revocation is checked separately and takes
precedence over the hash check.

> **📷 Screenshot 13** — the verification page reporting VALID.
> **📷 Screenshot 14** — the same credential reporting TAMPERED after editing `final_score` directly
> in phpMyAdmin. *This pair is the strongest single demonstration in the report — capture both.*

#### Practice 2 — Authentication hardening: attempt lockout, password history and audit *(mitigates Threat 2)*

**Category: Authentication and Password Management.** Four layers, none of which relies on the user
choosing a good password.

**(a) Failed-attempt counter and administrator-only lockout.** Every failed attempt against a *real*
account increments a counter, captured by listening to Laravel's own `Failed` event:

```php
// app/Providers/AppServiceProvider.php
Event::listen(Failed::class, function (Failed $event) {
    if ($event->user === null) {
        return;
    }

    ActivityLog::record('auth.failed', null, $event->user);
    $event->user->increment('failed_login_attempts');
});
```

After five consecutive failures the account locks, and **only an administrator can clear it** —
there is no self-service unlock and no automatic timed expiry. This is a deliberate choice: a timed
unlock merely slows an automated attacker, whereas a hard lock stops the attack and forces human
review of an event that is, by definition, suspicious.

A successful login resets the counter, so a legitimate user who mistypes twice is unaffected:

```php
Event::listen(Login::class, function (Login $event) {
    ActivityLog::record('auth.login', null, $event->user);

    $event->user->forceFill([
        'last_login_at' => now(),
        'failed_login_attempts' => 0,
    ])->save();
});
```

**(b) Password history.** A new password is compared against the previous three hashes and rejected
if it matches any of them, which prevents a user cycling back to a password that may already appear
in a breach corpus — the exact material a credential-stuffing attack uses.

**(c) Bcrypt with a work factor.** Passwords are stored using bcrypt via Laravel's `hashed` cast,
never plaintext or an unsalted digest. Bcrypt is deliberately slow and salted per password, so even
if the database is exfiltrated an offline attack is expensive and rainbow tables are useless.

**(d) Complete authentication audit trail.** Every login, logout and failed attempt is written to
`activity_logs` with actor, IP address and user agent. This is the **detection** layer: a
credential-stuffing campaign appears as many `auth.failed` rows from a small set of IP addresses
across many accounts — a pattern invisible without logging. An administrator can filter and export
these for analysis.

Failed attempts are recorded **only when the email matches a real account**, which is intentional:
the schema attributes a log row to a user, and it also avoids building an oracle that confirms which
email addresses exist.

> **📷 Screenshot 15** — an account locked after five failed attempts, and the administrator unlock
> control. **📷 Screenshot 16** — the activity log showing `auth.failed` entries with IP addresses.

---

## 6. Web Services

> ### ⚠ BUILD REQUIRED — read this first
>
> **This section documents a web service that does not exist in the codebase yet.** The current
> system has no `routes/api.php`, no REST or SOAP endpoint, and makes no outbound HTTP calls. The
> Interface Agreement below is a **specification to build against**, not a description of delivered
> code.
>
> Before submitting you must: create `routes/api.php`, register it in `bootstrap/app.php`, implement
> the controller below, implement the consumption client, and **replace the placeholder screenshots
> with real ones** (Postman for the exposed service, the rendered page for the consumed one).

### 6.1 Overview of how web service technology is used in this module

Module 1 participates in the service layer in both directions.

**As a provider**, it exposes the **credential verification** service. This is the natural choice:
verification is already an unauthenticated, read-only lookup returning a fixed shape, and it is the
one piece of Module 1 that other modules — and external parties — genuinely need. Module 5
(Analytics) consumes it to report how many students in a cohort hold a live credential.

**As a consumer**, it calls Module 2's **course information** service. When a certificate is
rendered, the course code and title printed on it are obtained from Module 2, which is the sole
owner of course data. This respects the module boundary: Module 1 never queries Module 2's tables
directly, it asks Module 2's service.

REST with JSON was chosen over SOAP: it is lightweight, needs no WSDL contract or XML envelope, and
is directly consumable by a browser, by Postman, and by the other modules' PHP clients.

### 6.2 Service Exposure — Interface Agreement (IFA)

#### Webservice Mechanism

| Protocol | Description |
|---|---|
| **Function** | RESTFUL |
| **Description** | Retrieves the verification status and public details of a digital credential by its credential ID |
| **Source Module** | Module 1 — Identity, Access & Digital Credentialing |
| **Target Module** | Module 5 — Academic Progress Analytics; Module 2 — Academic Resources Repository; any external verifier |
| **URL** | `http://localhost:8000/api/credentials/verify` |
| **Function Name** | `getCredentialStatus` |

#### Web Services Request Parameter (provide)

| Field Name | Field Type | Mandatory/Optional | Description | Format |
|---|---|---|---|---|
| `credentialId` | String | Mandatory | Unique ID of the credential to verify | `LS-YYYY-XXXXXXXX`; alphanumeric only |
| `detailFlag` | Integer | Mandatory | Level of detail required | `1`: status only<br>`2`: status + holder<br>`3`: full record |
| `requestId` | String | Mandatory | Unique ID for request tracking | UUID v4 |
| `timeStamp` | String | Mandatory | Time when the request was made | `YYYY-MM-DD HH:MM:SS` |

#### Web Services Response Parameter (consume)

| Field Name | Field Type | Mandatory/Optional | Description | Format |
|---|---|---|---|---|
| `status` | String | Mandatory | Status of the request | `S`: Success<br>`F`: Fail<br>`E`: Error |
| `credentialStatus` | String | Mandatory | Verification outcome | `VALID`, `REVOKED`, `TAMPERED`, `EXPIRED`, `NOT_FOUND` |
| `holderName` | String | Optional | Name of the credential holder | Alphabets and spaces; omitted when `detailFlag` = 1 |
| `courseTitle` | String | Optional | Course or learning path the credential is for | Alphanumeric |
| `finalScore` | Double | Optional | Mark attested by the credential | 0.00 – 100.00 |
| `issuedDate` | String | Optional | Date of issuance | `YYYY-MM-DD` |
| `credentialDetails` | Object | Optional | Full record, returned when `detailFlag` = 3 | Contains `issuer`, `expiresAt`, `revocationReason` |
| `requestId` | String | Mandatory | Echo of the request ID, for correlation | UUID v4 |
| `timeStamp` | String | Mandatory | Time when the response was generated | `YYYY-MM-DD HH:MM:SS` |

#### Implementation — code to write

```php
// routes/api.php  (create this file)
use App\Http\Controllers\Api\CredentialApiController;
use Illuminate\Support\Facades\Route;

// Public by design: verification must work with no account (EduSystem.md Section 7, Role 0).
Route::get('/credentials/verify', [CredentialApiController::class, 'verify']);
```

```php
// bootstrap/app.php  — register the API routes
->withRouting(
    web:      __DIR__.'/../routes/web.php',
    api:      __DIR__.'/../routes/api.php',      // ← add this line
    commands: __DIR__.'/../routes/console.php',
    health:   '/up',
)
```

```php
// app/Http/Controllers/Api/CredentialApiController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Patterns\Facade\CredentialAuthority;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Module 1's exposed REST service.
 *
 * The controller does no credentialing work itself: it validates the request,
 * asks the Facade, and shapes the answer to the Interface Agreement. That is
 * the Facade paying off again -- the service layer needed no knowledge of
 * hashing, PDFs or badge rules to be added.
 */
class CredentialApiController extends Controller
{
    public function __construct(private CredentialAuthority $authority)
    {
    }

    public function verify(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'credentialId' => ['required', 'string', 'regex:/^LS-\d{4}-[0-9A-Z]{8}$/'],
            'detailFlag'   => ['required', 'integer', 'in:1,2,3'],
            'requestId'    => ['required', 'uuid'],
            'timeStamp'    => ['required', 'date_format:Y-m-d H:i:s'],
        ]);

        if ($validator->fails()) {
            return $this->respond('F', [
                'credentialStatus' => 'NOT_FOUND',
                'errors'           => $validator->errors(),
            ], $request->input('requestId'));
        }

        $result = $this->authority->verify($request->input('credentialId'));
        $certificate = $result['certificate'];
        $flag = (int) $request->input('detailFlag');

        $payload = ['credentialStatus' => strtoupper($result['status'])];

        if ($certificate !== null && $flag >= 2) {
            $payload['holderName']  = $certificate->student->name;
            $payload['courseTitle'] = $certificate->course?->title
                ?? $certificate->learningPath?->title;
            $payload['finalScore']  = round($certificate->final_score, 2);
            $payload['issuedDate']  = $certificate->issued_at->format('Y-m-d');
        }

        if ($certificate !== null && $flag === 3) {
            $payload['credentialDetails'] = [
                'issuer'           => config('app.name'),
                'expiresAt'        => $certificate->expires_at?->format('Y-m-d'),
                'revocationReason' => $certificate->revocation_reason,
            ];
        }

        return $this->respond('S', $payload, $request->input('requestId'));
    }

    /**
     * Every response carries status, requestId and timeStamp, per the IFA.
     */
    private function respond(string $status, array $payload, ?string $requestId): JsonResponse
    {
        return response()->json(array_merge(
            ['status' => $status],
            $payload,
            ['requestId' => $requestId, 'timeStamp' => now()->format('Y-m-d H:i:s')]
        ));
    }
}
```

**Sample request**

```
GET /api/credentials/verify?credentialId=LS-2026-XTEG2CDW&detailFlag=2
    &requestId=8f14e45f-ceea-4d3b-9b1a-2c7f9d0e4a11
    &timeStamp=2026-08-28%2014:30:00
```

**Sample response**

```json
{
    "status": "S",
    "credentialStatus": "VALID",
    "holderName": "Foo Chong Xian",
    "courseTitle": "Integrative Programming",
    "finalScore": 88.00,
    "issuedDate": "2026-08-28",
    "requestId": "8f14e45f-ceea-4d3b-9b1a-2c7f9d0e4a11",
    "timeStamp": "2026-08-28 14:30:02"
}
```

> **📷 Screenshot 17** — Postman showing the request and the JSON response.
> **📷 Screenshot 18** — the response for a revoked credential, showing `"credentialStatus": "REVOKED"`.

### 6.3 Service Consumption

Module 1 consumes **Module 2's `getCourseInfo`** service to obtain the course code and title printed
on a certificate, rather than reading Module 2's tables directly.

| Protocol | Description |
|---|---|
| **Function** | RESTFUL |
| **Description** | Retrieves course code, title and instructor by course ID |
| **Source Module** | Module 2 — Academic Resources Repository (provider) |
| **Target Module** | Module 1 — Identity, Access & Digital Credentialing (consumer) |
| **URL** | `http://localhost:8000/api/courses/info` |
| **Function Name** | `getCourseInfo` |

```php
// app/Support/CourseInfoClient.php  (create this file)
namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Module 1's client for Module 2's course information service.
 *
 * Module 1 never reads Module 2's tables directly: it asks Module 2's service,
 * which keeps the ownership boundary in the specification intact.
 */
class CourseInfoClient
{
    public function fetch(int $courseId): ?array
    {
        $response = Http::timeout(5)->acceptJson()->get(
            config('services.modules.course_info_url'),
            [
                'courseId'  => $courseId,
                'queryFlag' => 1,
                'requestId' => (string) Str::uuid(),
                'timeStamp' => now()->format('Y-m-d H:i:s'),
            ]
        );

        // A failed lookup must never prevent a credential being issued, so the
        // caller falls back to its own copy of the title.
        if ($response->failed() || $response->json('status') !== 'S') {
            Log::warning('Course info service unavailable', ['courseId' => $courseId]);

            return null;
        }

        return [
            'code'  => $response->json('courseCode'),
            'title' => $response->json('courseTitle'),
        ];
    }
}
```

> **📷 Screenshot 19** — the certificate rendering with the course title retrieved from Module 2's
> service. Include the Laravel log line confirming the outbound call.

---

## 7. References

Gamma, E., Helm, R., Johnson, R., & Vlissides, J. (1994). *Design patterns: Elements of reusable
object-oriented software*. Addison-Wesley.

Laravel. (2026). *Laravel 12.x documentation*. https://laravel.com/docs/12.x

OpenAI. (2026). *ChatGPT* [Large language model]. https://chat.openai.com
_[Remove this entry if you did not use ChatGPT.]_

Anthropic. (2026). *Claude (Opus 5)* [Large language model]. https://claude.ai

OWASP Foundation. (2022). *OWASP secure coding practices quick reference guide* (Version 2.1).
https://owasp.org/www-project-secure-coding-practices-quick-reference-guide/

OWASP Foundation. (2021). *OWASP Top 10:2021 — The ten most critical web application security
risks*. https://owasp.org/Top10/

PHP Group. (2026). *PHP manual: hash_equals*. https://www.php.net/manual/en/function.hash-equals.php

United Nations. (2015). *Transforming our world: The 2030 agenda for sustainable development*
(A/RES/70/1). https://sdgs.un.org/2030agenda

---

## Checklist before submitting

- [ ] Fill in Student ID, Programme and Tutorial Group on the cover page
- [ ] Sign and date the AI Usage Disclosure Form — **and consider rewriting §4.2, §5.1, §5.2 in your own words**
- [ ] Draw **Figure 1** (entity class diagram — classes and object references, *not* an ERD)
- [ ] Draw **Figure 2** (Facade class diagram)
- [ ] Capture Screenshots 1–19, each labelled with its class path
- [ ] **Build the web service in Section 6** — it does not exist yet
- [ ] Replace Screenshots 17–19 with real Postman/browser captures once built
- [ ] Remove the ChatGPT reference if unused
- [ ] Transfer into the official Word template and export as PDF
