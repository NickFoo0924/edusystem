# Award rules

How badges and certificates are decided, and what an administrator can change without a
developer touching code.

---

## 1. The investigation: what was configurable, and what was not

The two halves of the award system were in completely different states.

### Badge rules — already configurable data

| Evidence | Where |
|---|---|
| Rules are table rows, not code | `badges` — `criteria_type`, `criteria_value`, `is_active` |
| A full admin CRUD screen already existed | `BadgeController` (index/create/store/edit/update/destroy), route-guarded by `can:badge.manage` |
| The engine reads whatever it finds | `BadgeRuleEvaluator` iterates active rows; nothing is hardcoded per badge |
| Awards are recorded separately from rules | `badge_student`, unique on `(badge_id, student_id)` |

An administrator could already create a badge rule and have it evaluated. What they could not do
was express conditions the engine had no arm for — there were six.

### Certificate rules — hardcoded

Certificate issuance had exactly **one** condition, written in code:

```php
// CredentialAuthority::issueIfEligible()
if ($progress->completion_percentage < $this->progress->passThreshold()) return null;
...
if (! GradeScale::isPass($score)) return null;
```

An administrator could tune **one number** (`certificate.pass_threshold` in `settings`, default 80)
and design the template it renders (`certificate_templates`). They could not say *"issue a
certificate to anyone averaging 75% in this subject"*. There was no rule to write, because
certificates were not rule-driven at all.

**Conclusion:** badges needed more condition types; certificates needed to become rule-driven.

---

## 2. The rule model

The `badges` table is now the **award rule registry** for both kinds of award. It keeps its name
rather than being renamed to `award_rules`, because renaming would have to carry
`badge_student.badge_id` and every award already made with it; this project's convention for schema
change is to add columns and document them (`implementation-notes.md`, "Added columns").

| Column | Meaning |
|---|---|
| `name`, `description` | What the award is, and the unlock condition as a sentence for students |
| `award_type` | `badge` (attaches to `badge_student`) or `certificate` (mints through the `CredentialAuthority`) |
| `tier`, `icon_path` | Badge presentation — bronze/silver/gold medal, or an uploaded icon |
| `certificate_template_id` | Which design a certificate rule renders; null falls back to the active default |
| `criteria_type` | Which condition, from the fixed list below |
| `criteria_value` | The condition's number |
| `course_id` | The subject a rule is scoped to; null means "any" |
| `is_active` | Whether the engine evaluates it |

`award_type` defaults to `badge`, so every row that already existed keeps behaving exactly as it did.

---

## 3. The condition types

A fixed, parameterised list. **Deliberately not a scripting engine** — an administrator picks a
condition and fills in its number, and never writes an expression. The trade is intentional: no
rule an admin can write is able to error, loop, or read data they should not see, and every
condition is legible to whoever marks the project.

| Condition | `criteria_value` means | Subject-scoped |
|---|---|---|
| `course_completion` | How many courses completed | — |
| `path_completion` | How many learning paths completed | — |
| `quiz_score` | Percentage reached on any one quiz | — |
| `on_time_submissions` | How many assignments submitted before the deadline | — |
| `first_forum_post` | How many forum posts (1) | — |
| `login_streak` | Consecutive days signed in | — |
| `all_quizzes_in_course` | How many subjects cleared, if no subject named | ✅ |
| `average_score_in_course` | Percentage the mean quiz mark must reach | ✅ |
| `quizzes_completed` | How many distinct quizzes passed, system-wide | — |

The last three were added by this work. Adding another means one arm in `AwardConditionEvaluator`
plus a line in the `criteria_type` enum — the price of not having shipped an interpreter.

### "Attend N classes" is deliberately absent

The task named it as a candidate condition. **Nothing in this system records attendance.**
`course_events` holds scheduled classes, but no table records who turned up. A rule referring to
attendance could never become true, and would sit permanently unearnable in every student's
cabinet. It needs an attendance mechanism first; that is a separate piece of work, not a condition
type.

---

## 4. One evaluation path

There is no separate code path for "built-in" rules. A seeded rule is a row that arrived earlier,
and nothing about it is privileged.

```
Grade written (Module 5)
  └─ Grade::created
       └─ CredentialAuthority::handleGradeRecorded()
            ├─ ProgressCalculator::recalculate()
            ├─ issueIfEligible()            ← the built-in completion-threshold rule
            ├─ evaluateCertificateRules()   ─┐
            └─ BadgeRuleEvaluator::evaluate()─┴─ both ask AwardConditionEvaluator::isSatisfied()
```

`AwardConditionEvaluator` is the single place a condition is decided, so a condition cannot mean one
thing for a badge and another for a certificate.

**Recursion note:** `evaluateCertificateRules()` is called only from `handleGradeRecorded()`, never
from inside `issueCertificate()` — issuance evaluates badges as its last step, and if it evaluated
certificate rules too, a rule that stays satisfied after issuing would call itself back.

**Certificate rules must name a subject.** A credential attests to something in particular, so the
admin form rejects a certificate rule with no course rather than letting it save and silently never
fire.

---

## 5. What happens when a rule is edited or deleted

**Decision: awards already made are retained.** Revoking something a student already holds, because
an administrator later retuned a threshold, is unfair and confusing — the student did satisfy the
rule as it stood.

| Action | Effect on the rule | Effect on awards already made |
|---|---|---|
| **Edit** the name, description or icon | Applies everywhere immediately, including on badges already held | Kept |
| **Edit** the condition or its value | Applies to future evaluations only | **Kept.** A student who no longer meets the new condition keeps the badge they earned under the old one |
| **Deactivate** (`is_active = false`) | The engine stops evaluating it; it disappears from the cabinet's locked list | **Kept**, and still shown as earned |
| **Delete** | Gone | **Badge rules: awards are destroyed too** — `badge_student` cascades on delete. Certificate rules: issued credentials survive, because a `Certificate` is its own row and does not reference the rule |

**Deactivating is the safe way to stop a rule; deleting is the destructive one.** The admin screen
offers both, the delete confirmation says so, and there is an Activate/Deactivate button on every
row precisely so that stopping a rule never requires deleting it.

**When an edit takes effect.** The rule registry is read once per request, so an administrator's
change applies from the next request onward. That is deliberate: it means a rule cannot change
underneath a student midway through their awards. In practice the only request that sees the old
set is the admin's own save.

---

## 6. What an administrator can now do without a developer

From **Award Rules** in the Administration group of the left rail:

1. Create a badge rule — pick a condition, set its number, optionally scope it to a subject, choose
   a tier, upload an icon.
2. Create a **certificate** rule — pick a condition and a subject, and optionally a certificate
   design. Satisfying it mints a real credential: unique credential ID, SHA-256 integrity hash,
   QR-coded PDF, audit entry, publicly verifiable at `/verify/{credential_id}` — through the same
   authority an automatic issuance goes through.
3. Activate or deactivate any rule without deleting it.
4. Edit any rule's condition, threshold, subject, presentation or template.

The P5 "Subject Expert" badge is not special-cased anywhere. It is a row with
`criteria_type = all_quizzes_in_course` and a `course_id`, indistinguishable to the engine from one
an administrator creates this afternoon.
