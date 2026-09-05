# Syllabus alignment audit

BAIT3173 Integrative Programming — where each chapter of the module syllabus is demonstrated in
LearnSync, and where it is not.

Source: the nine chapter decks in `chapter_syllabus/`, plus the OWASP Secure Coding Practices quick
reference that Chapter 2 is built on.

A coursework project is not required to demonstrate every chapter, and forcing it produces
contrived code. Where a topic does not apply, that is recorded as such rather than answered with
an invented feature.

---

## 1. The chapters

| # | Chapter | What it covers |
|---|---|---|
| 1 | Scripting | Origins of scripting, PHP as a server-side language, syntax, forms, superglobals, sessions and cookies |
| 2 | Software Security Practices | Secure development lifecycle, and the OWASP control families: input validation, output encoding, authentication and password management, session management, access control, cryptographic practices, error handling and logging, data protection, database security, file management, memory management |
| 3 | Integrative Coding | Reuse, the object-oriented pillars, and the GoF design patterns — Observer, State, Proxy, Facade, Adapter, Singleton, Decorator, Strategy, Factory Method are the ones taught in depth |
| 4A | Data Encoding & XML | Bits and bytes, ASCII, Unicode and UTF, then XML as a structured interchange format — elements, attributes, DTD, CDATA, well-formedness |
| 4B | XML Parsing, XPath & XSLT | SAX versus DOM parsing in PHP, navigating with XPath, transforming with XSL/XSLT |
| 5A | Web Services | SOAP and WSDL, REST, JSON versus XML payloads, HTTP verbs as a service interface |
| 5B | Network Programming | Sockets, TCP and UDP clients and servers, request/response over the wire |
| 5C | Middleware | Enterprise middleware — RMI, message buses, synchronous versus asynchronous integration |
| 6 | Programming Paradigms | Imperative, object-oriented, functional and declarative; compilers, interpreters and VMs; the modern language landscape |

---

## 2. Topic-by-topic coverage

| Topic | Where it appears in the code | Covered |
|---|---|---|
| **PHP as a server-side language** | The entire application — Laravel 12 on PHP 8.2 | Fully |
| **Forms, request handling, superglobals** | Every controller; Laravel's `Request` object wraps the superglobals rather than using them raw, which is the framework-idiomatic form of the same topic | Fully |
| **Sessions and cookies** | `config/session.php`, database session driver; the stale-tab handling in `bootstrap/app.php` | Fully |
| **OOP pillars** | Interfaces and concrete implementations throughout `app/Patterns/`; Eloquent models inheriting `Model` | Fully |
| **Design patterns** | Five of the nine taught patterns, one per module: Facade (`CredentialAuthority`), Adapter (`DisplayableMaterial`, `CalendarEntry`), Observer (`SystemNotificationObserver`), Strategy (`GradingStrategy`), State (`SubmissionState`) | Fully |
| **Input validation** | 32 `validate()` call sites across 19 controllers, with `mimes:` and `max:` rules on every upload | Partly — see 3.1 |
| **Output encoding** | Blade `{{ }}` escapes by default. The single `{!! !!}` in `certificates/pdf.blade.php` is `nl2br(e($text))` — escaped first, then line breaks added | Fully |
| **Authentication & password management** | Breeze scaffolding extended: invitation-only registration, password history (no reuse of the last 3), forced change on first login, five-failure lockout that only an administrator can clear | Fully |
| **Session management** | Framework-provided; session regenerated on login by Breeze | Fully |
| **Access control** | 77 permission-key checks; `permissions` / `permission_role` resolved by a Gate, editable at runtime. No `if ($user->role === ...)` anywhere | Fully |
| **Cryptographic practices** | Bcrypt for passwords; SHA-256 integrity hash on every certificate, recomputed at verification | Fully |
| **Error handling & logging** | `activity_logs` records actor, target, IP and user agent for security-relevant actions; admin can filter and export | Fully |
| **Database security** | Eloquent everywhere — zero `DB::raw`, `DB::select`, `DB::statement` or `DB::table` in `app/`. Queries are parameter-bound by the ORM, which is the chapter's SQL-injection defence | Fully |
| **File management** | Uploads constrained by extension and size; stored under `storage/app/public` and served through a symlink rather than from the web root | Fully |
| **CSRF** | Laravel's token on every form; a form submitted after its session changed is refused | Fully |
| **XSS** | Blade auto-escaping, as above | Fully |
| **SQL injection** | Eloquent parameter binding, as above | Fully |
| **XML: encoding, structure, elements and attributes** | The analytics export, built with `DOMDocument` in `AnalyticsController::buildXml()` and served at `/analytics/export.xml` | Fully |
| **XML parsing — DOM, XPath, XSLT** | `DOMDocument` builds the document (the DOM half of the DOM/SAX pair); `resources/xml/analytics-chart.xsl` transforms it with `for-each`, `value-of` and XPath selects. SAX is not used — it is read-only and streaming, so it cannot build a document | Fully, apart from SAX |
| **XSD schema validation** | `resources/xml/analytics.xsd` — typed restrictions (percentage 0–100), `nonNegativeInteger` counts, `xs:date` / `xs:dateTime`, and an enumeration of A/B/C/D/F. Enforced by `DOMDocument::schemaValidate()` before every transform | Fully |
| **Web services — SOAP / WSDL** | Nothing. No SOAP endpoint or WSDL | Not at all |
| **Web services — REST / JSON** | No JSON API. Routes are HTML-returning web routes; `routes/api.php` is unused. One `response()->json` was added for the stale-tab case | Not at all (as a service interface) |
| **HTTP verbs as an interface** | Routes use GET/POST/PUT/PATCH/DELETE correctly through resource routing, so the verb semantics are demonstrated even without an API | Partly |
| **Sockets, TCP/UDP** | Nothing | Not at all |
| **Middleware (enterprise: RMI, message bus)** | Nothing in the RMI/message-bus sense. `app/Http/Middleware/` is Laravel HTTP middleware, which is a different meaning of the word | Not at all |
| **Paradigms** | Object-oriented throughout, with functional style in the collection pipelines (`map`, `filter`, `groupBy`) and declarative Blade templates | Partly, and incidentally |

---

## 3. Findings

### 3.1 Cheap and worth doing

**Form Request validation.** The syllabus teaches validation as a separable concern, and Laravel's
idiom for it is a Form Request class. The project has exactly one (`ProfileUpdateRequest`, which
Breeze generated) against 32 inline `validate()` call sites. The rules themselves are already
correct — this is purely about where they live.

Doing all 32 would be churn for its own sake. The defensible version is to extract the handful with
the most rules, where a request class genuinely reads better and the authorisation check can move
into `authorize()`, and to leave simple two-field validations inline. Candidates: the quiz question
store, the course event store, the assignment store.

*Cost: low. Risk: low — validation rules move unchanged, and tests cover the affected routes.*

### 3.2 Expensive or risky — recommend leaving

**A REST/JSON API layer (Chapter 5A).** Genuinely absent, and the most conspicuous gap. Adding one
properly means API routes, resource classes, token authentication, and a second authorisation path
parallel to the web one. That is a second application surface over assessed, working code, and the
permission model would have to be re-proven against it.

If some coverage is wanted cheaply, the honest minimum is to expose *one* read-only endpoint that
already has a natural consumer — the public credential verification, which is already an
unauthenticated lookup returning a fixed shape. `GET /api/verify/{credential_id}` returning JSON
would demonstrate REST, JSON payloads and HTTP status semantics in about thirty lines, without a
second auth model. Anything beyond that is not worth the risk.

**XML and XSLT (Chapters 4A/4B) — since addressed.** These were the two chapters with no
representation. They are now covered by the analytics completion-trend chart, which runs a real
pipeline: Eloquent → `DOMDocument` → XSD validation → XSLT → SVG.

The approach was chosen because SVG is itself an XML vocabulary, so producing the chart is a
genuine XML-to-XML transformation rather than an export invented to tick a box, and the document
doubles as a data export at `/analytics/export.xml`. A production system would reach for a
charting library, and that is the honest comparison: this approach is more work for the same
picture. It is used here because nothing else in the system exercises XML, schema validation or
XSLT, and the syllabus covers all three.

Requires `ext-xsl`. Without it the document is still built and still validated, and only the chart
is skipped.

### 3.3 Legitimately out of scope

**Chapter 5B, Network Programming.** Sockets, TCP and UDP clients and servers. A Laravel web
application speaks HTTP through the framework and a web server; opening raw sockets would be
actively wrong here. The chapter's material is about a layer this project correctly delegates.

**Chapter 5C, Middleware** in its enterprise sense — RMI, message buses, synchronous versus
asynchronous integration. The specification forbids WebSockets and message brokers
(EduSystem.md §5: no live chat, no Reverb, database writes for notifications). The closest honest
claim is that the reminder command is an asynchronous producer feeding the notification inbox,
which is a message-passing shape without a broker. Note the word collision in any report: Laravel
"middleware" is HTTP request filtering, not the middleware this chapter teaches.

**Chapter 6, Paradigms.** Conceptual and comparative — a survey of language families and execution
models. It is examined knowledge rather than something an application demonstrates. The project is
object-oriented with functional touches, which is all that can meaningfully be claimed.

---

## 4. Summary

Of the nine chapters, **five are covered fully** (1 Scripting, 2 Software Security, 3 Integrative
Coding, 4A Data Encoding & XML, 4B XML Parsing & XSLT), **one partly and incidentally**
(6 Paradigms), **one is absent** (5A Web Services — there is no JSON or SOAP service interface),
and **two are out of scope** (5B Network Programming, 5C Middleware).

The covered chapters include the ones carrying the most assessment weight: Chapter 2 is named on
the written quiz, and Chapter 3 is the design-pattern chapter the entire five-module structure
rests on, where five of the nine patterns it teaches are implemented, each with a written
justification. Chapters 4A and 4B are demonstrated by the analytics pipeline.
