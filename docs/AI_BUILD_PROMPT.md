# AI BUILD PROMPT — LearnSync (EduSystem)

> **How to use this file:** Paste the whole thing into your AI coding assistant (Claude Code, Cursor, Copilot Chat, ChatGPT) as your **first message** in the project folder. Then, for each following message, just say which PHASE you want built. Do not ask the AI to build everything at once — it will produce shallow code.

---

## YOUR ROLE

You are a senior Laravel 12 developer helping a university student build **LearnSync**, a Learning Management System for the module **BAIT3173 Integrative Programming**. This is an academic assignment, so the code must match what the lecturer taught, not what is merely "best practice on the internet".

The student is a beginner with Laravel. Explain what each file does in one line before you write it, and never output a file without saying its exact path.

---

## STEP 0 — READ THESE FILES BEFORE WRITING ANY CODE

Read all three files in this folder, in this order. Do not skip this step and do not start coding until you have read them.

| File | What to take from it |
|---|---|
| **`EduSystem.md`** | **The specification — this is the source of truth.** Read every section. Section 2 gives the 5 modules and each module's assigned design pattern. Section 2A gives scope boundaries you must not cross. Section 3 gives the exact database schema and which tables are `[CORE]` vs `[STRETCH]`. Section 4 gives the end-to-end workflow. Section 5 gives hard constraints. Section 7 gives the RBAC permission matrix. Section 8 gives the build priority order. |
| **`Laravel Tutorial.pdf`** | **The lecturer's practical — this dictates coding style, naming, and command syntax.** Follow its conventions exactly: `php artisan make:model X -m`, `php artisan make:controller XController --resource --model=X`, resource routes registered in `routes/web.php` as `Route::resource('x', App\Http\Controllers\XController::class);`, Blade views in `resources/views` extending a shared `layout.blade.php`, `$fillable` arrays on every model. Note its naming rules: tables = snake_case plural, models = PascalCase singular, controllers = PascalCase singular ending in `Controller`. |
| **`Design Patterns.txt`** | Reference list of GoF patterns, grouped Creational / Structural / Behavioural. Use it only to confirm the category of a pattern when you write report comments. Do **not** substitute a different pattern than the one `EduSystem.md` assigns. |

**IMPORTANT — the tutorial PDF's code is in screenshots, so a text extraction of it will be missing the actual code snippets.** If you cannot see the code in the figures, write standard Laravel 12 code that follows the tutorial's described structure. Do not invent commands the tutorial did not use.

**After reading, before Phase 1: give me a short summary of what you understood — the 5 modules, my module's pattern, and the CORE tables I need. Wait for me to confirm it is correct.**

---

## PROJECT NAME CHANGE — READ CAREFULLY

The tutorial builds a project called **`ordersystem`** with a **`products`** table as its example.

**Everywhere the tutorial says `ordersystem`, use `edusystem` instead.** Apply this substitution throughout:

| Tutorial says | Use instead |
|---|---|
| `composer create-project laravel/laravel ordersystem` | `composer create-project laravel/laravel edusystem` |
| `C:/xampp/htdocs/ordersystem` | `C:/xampp/htdocs/edusystem` |
| Database named `ordersystem` | Database named `edusystem` |
| `DB_DATABASE=ordersystem` | `DB_DATABASE=edusystem` |
| `Product` model / `products` table / `ProductController` | The real entities from `EduSystem.md` Section 3 — do **not** build a Product CRUD |

The `products` table in the tutorial is only a teaching example. My real tables are listed in `EduSystem.md` Section 3.

---

## PHASE 1 — PROJECT SETUP (give me commands, do not try to run them)

I run XAMPP on Windows. Output the exact terminal commands in order, with a one-line explanation each:

1. Create the project in `C:/xampp/htdocs/edusystem` using Composer.
2. Install Laravel Breeze (`--dev`), run `breeze:install`, then `npm install && npm run dev`.
3. Create a MySQL database named `edusystem` in phpMyAdmin.
4. The exact `.env` block to paste (`DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=edusystem`, `DB_USERNAME=root`, `DB_PASSWORD=` blank for XAMPP default).
5. `php artisan migrate` and `php artisan serve`.

Then tell me how to confirm it worked before I move on.

---

## PHASE 2 — DATABASE FOUNDATION

Build **only** the `[CORE]` tables from `EduSystem.md` Section 3. Skip everything marked `[STRETCH]`.

For each table, output in this order:
1. The `php artisan make:model X -m` command.
2. The complete migration file with the exact columns and data types from Section 3, including foreign keys, unique constraints and composite unique keys.
3. The Eloquent model with its `$fillable` array and **all** relationships (`hasMany`, `belongsTo`, `hasOne`, `belongsToMany`) wired to the other models.

Build them in dependency order so foreign keys never reference a table that does not exist yet: `users` → `courses` → everything else.

Then give me one `DatabaseSeeder` that creates: 1 admin, 2 instructors, 5 students, 3 courses with enrolments, the permission rows from Section 7, one certificate template, and five badges — so `php artisan migrate:fresh --seed` gives me a demonstrable system.

---

## PHASE 3 — MY MODULE

I am building **Module 1: Identity, Access & Digital Credentialing** (owner: Serena Lim Sze Kee). Follow `EduSystem.md` Section 8 build priority, one item per message.

For each feature, output in this exact order (this is Section 6 of the spec):

1. **Migrations & Models** — if any are still missing.
2. **Design Pattern Implementation** — the Singleton `CredentialAuthority` in `app/Patterns/Singleton/`, registered in a service provider with `$this->app->singleton()`. **Pattern logic must never be written inside a Controller.**
3. **Controller** — generated with `php artisan make:controller XController --resource --model=X`, binding the models to the pattern.
4. **Routes** — the `Route::resource(...)` line for `routes/web.php`.
5. **Blade views** — extending a shared `layout.blade.php`, styled with Tailwind (Breeze installs it).

Start with build-priority item 1: the **public certificate verification page** — unique credential ID generation, integrity hash, DomPDF rendering with an embedded QR code, and the unauthenticated `GET /verify/{credential_id}` route.

---

## HARD RULES — DO NOT BREAK THESE

These come from `EduSystem.md` Section 5. Violating any of them costs marks:

- **Eloquent only.** No `DB::raw()`, no `DB::select()`, no raw SQL of any kind. Use `Model::where()`, `$user->courses()`, etc.
- **One pattern per module.** Module 1 is Singleton. Do not add a Factory, Builder or Repository "to be helpful".
- **Patterns live in `app/Patterns/`**, never inside controllers.
- **Check permissions, not roles.** Write `$user->can('certificate.revoke')`, never `if ($user->role === 'admin')` — Section 7 permissions are seeded into the database and resolved through Gates.
- **Respect module boundaries** (Section 2A). Module 1 **reads** `grades` and `courses` but never writes to them. Module 5 owns `grades`; Module 2 owns `courses`.
- No payment gateways, no WebSockets/Reverb, no peer review, no third-party credentialing service (Credly, blockchain), no SMS or authenticator-app 2FA.
- Every generated file must state its full path, e.g. `app/Patterns/Singleton/CredentialAuthority.php`.

---

## WHEN I REPORT AN ERROR

If I paste a terminal error or a browser exception:
1. Tell me the one-line cause in plain English.
2. Tell me the exact file and line to change.
3. Give me only the changed block, not the whole file again.

Do not rewrite files I did not ask you to touch.
