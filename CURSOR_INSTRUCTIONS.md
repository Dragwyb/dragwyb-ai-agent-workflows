# Cursor AI Development Instructions

## Project: New Workflow Automation Plugin for WordPress

**Audience:** This document is written directly to Cursor AI (and any AI pair-programmer working in this repository). It is a requirements and process specification, not a casual brief. Follow it literally, in order, and do not skip phases. If any instruction here conflicts with a later, more specific instruction in this same document, the more specific instruction wins. If you are ever uncertain whether an action is permitted, stop and ask rather than guessing.

---

## 0. Purpose and Non-Negotiable Ground Rules

You are building a brand-new WordPress plugin. The project workspace also contains the complete source code of an existing plugin called **Bit Pi**. Bit Pi exists in this workspace **for reference and competitive analysis only**. It is not a starting point, a template, or a codebase to fork.

Read the following rules before touching any file, and re-read them before you write a single line of production code:

1. **No copying, ever.** You must not copy, paraphrase-but-keep-structurally-identical, or lightly rename any code, function, class, method, SQL schema, file, asset, icon, string, or configuration from the Bit Pi plugin. This includes CSS class names, JS module structure, PHP class hierarchies, and REST route naming — if it is recognizably derived from Bit Pi's actual implementation rather than independently designed by you, it is not acceptable.
2. **No branding leakage.** No file, string, comment, constant, slug, database table, option key, hook name, or asset filename may reference "Bit Pi," "bitpi," "bit_pi," or any close variant, anywhere in the finished plugin, including in code comments, changelog files, or documentation.
3. **Understand before you build.** You must complete the full analysis phase (Section 1) before creating any plugin file other than scaffolding/config files explicitly listed as pre-analysis-safe in Section 1.6.
4. **Build incrementally.** You must never attempt to generate the entire plugin in one pass. Follow the feature-by-feature workflow in Section 10.
5. **Security and standards are not optional extras.** Every feature must satisfy the security checklist (Section 5) and coding standards checklist (Section 4) before it is considered "done."
6. **When in doubt, ask the human maintainer.** If a requirement is ambiguous, if Bit Pi does something in a way you cannot safely interpret without risk of copying it, or if a decision has significant architectural consequences, pause and raise the question instead of guessing.

Treat this document as a living checklist. At the start of every work session, re-read the section relevant to the phase you are in.

---

## 1. Phase One — Analysis (Mandatory, Before Any Code)

You must produce a written internal analysis before writing plugin code. This analysis is your own original work product — a set of your own notes and conclusions, not a copy of Bit Pi's code or comments — and should be saved to `/docs/internal/bitpi-analysis.md` (this file is for the development team only and will not ship with the plugin; ensure the build process excludes the entire `/docs/internal/` directory from the final distributable package).

### 1.1 Structural Inventory

Walk the entire Bit Pi plugin directory tree and produce, in your own words:

- A list of every top-level directory and its apparent responsibility (e.g., "admin UI," "REST controllers," "database layer," "integrations," "cron/queue handling," "public-facing assets").
- A list of every PHP class, its namespace, and a one-sentence description of its responsibility.
- A map of how the admin menu is structured (parent pages, subpages, tabs).
- A description of the plugin's activation/deactivation/uninstall behavior (what tables it creates, what options it registers, what cron events it schedules).
- A description of the database schema: table names, columns, indexes, foreign-key-like relationships (even if enforced only in application code).
- A description of every REST API route or admin-ajax action registered, including its purpose, expected inputs, and expected outputs — described functionally, not by copying route-handler code.
- A description of every third-party service integration (e.g., email providers, messaging platforms, payment processors, AI providers) and how credentials for each are stored and used.
- A description of the front-end/workflow-builder UI: what JS framework or vanilla approach it uses, how the visual canvas is rendered, how nodes are added/connected/configured, and how the builder state is persisted.
- A description of the licensing/update-check mechanism, if any, and how settings are structured.

### 1.2 Functional Understanding

For each major feature area (e.g., trigger nodes, action nodes, conditional logic, scheduling, logging, error handling, user permissions, multi-workflow management), write a plain-language description of:

- What the feature does from a user's perspective.
- What the feature does from a system perspective (what gets stored, what gets executed, what gets logged).
- The overall user journey: how does someone discover, create, configure, test, and monitor a workflow?

### 1.3 Strengths and Weaknesses Assessment

Produce a candid assessment covering:

- **Architecture strengths/weaknesses** — Is logic separated cleanly? Are there god classes? Is there tight coupling between the UI layer and the execution engine? Is there a service layer, or is everything crammed into controller-like classes?
- **Security review** — Look specifically for: missing nonce checks on state-changing actions, missing or insufficient capability checks, direct and unsanitized use of `$_GET`/`$_POST`/`$_REQUEST`, unescaped output in admin screens, raw SQL string concatenation instead of `$wpdb->prepare()`, insecure deserialization (e.g., unsafe `unserialize()` calls on user-controllable data), overly permissive REST route permission callbacks (e.g., `__return_true`), secrets stored in plaintext options instead of encrypted or otherwise protected storage, and insufficient validation on file uploads.
- **Performance review** — Look for N+1 query patterns, missing indexes on frequently queried columns, synchronous execution of what should be queued/background work, unbounded loops over large datasets, lack of caching (transients/object cache) for expensive or repeated lookups, and unnecessarily large or unminified assets loaded on every admin page rather than only where needed.
- **UX review** — Identify friction points: unclear onboarding, confusing navigation, too many clicks to accomplish common tasks, unclear error messages, lack of inline help/documentation, inconsistent visual design, and accessibility gaps (missing labels, poor keyboard navigation, insufficient color contrast, no ARIA roles on custom widgets).
- **Code quality review** — Identify duplicated logic that should be extracted into shared services/utilities, inconsistent naming conventions, missing or inconsistent PHPDoc, mixed concerns within single files, and lack of automated tests.
- **Scalability review** — Identify anything that would degrade badly with, for example, thousands of workflows, high-frequency triggers, or large execution logs (unbounded log tables, lack of log rotation/pruning, lack of pagination in admin list tables, synchronous webhook processing that could time out).

### 1.4 Opportunity List

From the above, produce a prioritized list of concrete opportunities for improvement. Each item should state: the problem observed (described functionally, in your own words), why it matters, and your proposed better approach. This list becomes the backbone of your feature roadmap in Section 2.

### 1.5 Explicit Non-Goals

Note anything from Bit Pi you intentionally will **not** replicate (features that are low-value, legally risky, or poor practice) and why.

### 1.6 Pre-Analysis-Safe Scaffolding

The only files you may create before completing Sections 1.1–1.5 are:
- `composer.json` / `package.json` with no plugin-specific logic yet.
- `.gitignore`, `.editorconfig`, `phpcs.xml.dist`, `.eslintrc`.
- An empty `/docs/internal/` directory for your analysis notes.

Do not create the main plugin bootstrap file, any admin screens, or any database migration until analysis is complete and you have shared a summary of it with the maintainer for a sanity check.

---

## 2. Phase Two — Product & Architecture Design

Once analysis is complete, produce a design document at `/docs/internal/architecture.md` covering the items below. This is where "redesign, don't clone" gets made concrete.

### 2.1 Naming and Branding Decisions

Before any further design work, establish and document the project's canonical identifiers. Use placeholders consistently throughout this document and all future code — replace every instance below with the actual chosen values before development begins, and never fall back to Bit Pi's names if a value is temporarily undecided:

- **Plugin Name:** `{{PLUGIN_NAME}}`
- **Plugin Slug (folder + main file):** `{{plugin-slug}}`
- **Text Domain:** `{{plugin-slug}}`
- **PHP Namespace Root:** `{{VendorName}}\{{PluginName}}`
- **Function/Constant Prefix:** `{{PREFIX}}_` (uppercase for constants, e.g. `{{PREFIX}}_VERSION`)
- **Option Name Prefix:** `{{prefix}}_option_`
- **Database Table Prefix:** `{{$wpdb->prefix}}{{prefix}}_`
- **Hook Prefix (actions/filters):** `{{prefix}}/` (e.g. `{{prefix}}/workflow/before_run`)
- **REST API Namespace:** `{{prefix}}/v1`
- **Admin Menu Slug:** `{{prefix}}-dashboard`
- **Asset Handle Prefix:** `{{prefix}}-`
- **CSS Class/Custom Property Prefix:** `.{{prefix}}-` / `--{{prefix}}-*`

Every subsequent section of this document and every file you create must use these identifiers. Do a final repository-wide search before release to confirm zero leftover references to Bit Pi naming.

### 2.2 High-Level Architecture

Design (do not just describe Bit Pi's version) the following layers:

- **Core/Bootstrap layer** — plugin activation, deactivation, uninstall, dependency checks (PHP version, WordPress version), autoloading (PSR-4 via Composer), and a central plugin container/service locator or lightweight dependency-injection container.
- **Domain layer** — plain PHP classes/interfaces representing core concepts (Workflow, Node, Trigger, Action, Connection, Execution, ExecutionLog) independent of WordPress-specific storage details where practical.
- **Persistence layer** — repository classes wrapping `$wpdb` access, each responsible for one table or aggregate, all queries parameterized via `$wpdb->prepare()`.
- **Service layer** — application services orchestrating domain logic (e.g., `WorkflowExecutionService`, `TriggerRegistry`, `IntegrationManager`), each with a single clear responsibility.
- **Admin/UI layer** — controllers for admin pages, asset enqueueing, and the workflow-builder application shell.
- **REST API layer** — controllers extending `WP_REST_Controller` with strict permission callbacks and schema-validated arguments.
- **Integration layer** — one class per third-party integration behind a common interface, so new integrations can be added without modifying core execution logic.
- **Background processing layer** — queued/cron-driven execution for anything that should not block a web request (webhook processing, scheduled triggers, retries).

Favor composition over inheritance. Avoid a single "God" plugin class. No PHP file should mix, for example, database access, HTTP response formatting, and business logic in one place — split these into distinct classes with clear names.

### 2.3 Database Schema Redesign

Do not reuse Bit Pi's table/column names or structure verbatim. Independently design a schema that satisfies the same functional needs plus your identified improvements (e.g., normalized execution logs, indexed status/timestamp columns for fast filtering, soft-delete support if useful, a versioned workflow-definition column to support future migrations). Document every table, column, type, index, and the reasoning behind each choice.

### 2.4 Admin UI & Workflow Builder Redesign

Design your own information architecture and interaction model:

- Define the navigation structure (dashboard, workflow list, workflow editor, executions/logs, settings, integrations).
- Define the workflow-builder interaction model (canvas, node palette, node configuration panel, connection validation, autosave behavior, undo/redo if in scope).
- Define onboarding: first-run experience, empty states, contextual help.
- Define how errors, warnings, and execution status are surfaced to the user.

All visual and interaction decisions must be your own design, informed by general good UX practice and by the weaknesses identified in Section 1.3 — not a re-skin of Bit Pi's actual layout.

### 2.5 Settings Redesign

Design a settings structure grouped logically (General, Integrations, Security/API Keys, Advanced, Logging/Retention). Use the WordPress Settings API or a custom-but-standards-compliant equivalent, with all values sanitized on save and escaped on output.

### 2.6 Extensibility Plan

Document the public actions and filters you will expose (using the `{{prefix}}/` hook namespace), the plan for allowing third-party node/integration registration, and any documented public PHP API for developers.

---

## 3. Feature Roadmap

Break the full feature set into an ordered list of discrete, independently shippable increments (e.g., "Core plugin bootstrap + activation/deactivation," "Workflow CRUD + database layer," "Node/trigger registry," "Visual builder shell," "Execution engine (synchronous)," "Background/queued execution," "Logging & execution history UI," "Settings screens," "First integration," "REST API," "Additional integrations," "Onboarding & polish"). Store this list in `/docs/internal/roadmap.md` and update it as work progresses. Do not begin implementation until this roadmap exists and has been reviewed against the opportunity list from Section 1.4.

---

## 4. Coding Standards (Apply to Every File, Every Time)

- **PHP:** Follow WordPress-PHP Coding Standards (WPCS) exactly, enforced via PHP_CodeSniffer with the WordPress ruleset. Use strict typing (`declare(strict_types=1);`) in new domain/service classes where practical. Use PSR-4 autoloading via Composer for your namespace. One class per file, filenames matching WordPress class-file naming conventions for hookable files, or PSR-4 conventions for autoloaded library-style classes — be consistent and document the convention chosen.
- **JavaScript:** Follow the WordPress JavaScript Coding Standards (based on ESLint config `@wordpress/eslint-plugin` or equivalent). Prefer modern ES modules and, if using a build step, document the build tooling (e.g., `@wordpress/scripts`) in the README.
- **CSS:** Follow WordPress CSS Coding Standards; use consistent naming (BEM-like or utility-based, your choice, documented once and applied consistently) under the `{{prefix}}-` prefix.
- **HTML:** Semantic, accessible markup; every form control has an associated label; every icon-only control has an accessible name.
- **General:** Apply SOLID principles, DRY, and KISS. No function should try to do more than one thing. No class should exceed a reasonable single responsibility — if a class is accumulating unrelated methods, split it. Favor clear, descriptive names over comments that explain unclear names.
- **PHPDoc:** Every class, method, and function has a PHPDoc block describing purpose, parameters, return type, and any thrown exceptions. Inline comments are used sparingly, only to explain *why*, not *what*, when the *why* is not obvious from the code itself.
- Run PHPCS and any configured linters as part of your own self-review before considering a feature increment complete (see Section 11).

---

## 5. Security Requirements (Apply to Every Feature, Every Time)

Before marking any feature complete, verify all of the following that are applicable:

- **Input handling:** Every value read from `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, or REST/AJAX request bodies is sanitized with the appropriate WordPress function (`sanitize_text_field()`, `sanitize_email()`, `absint()`, `wp_kses_post()`, etc.) or validated against a strict allow-list/schema before use.
- **Output escaping:** Every value echoed into HTML, HTML attributes, URLs, or JavaScript context uses the correct escaping function (`esc_html()`, `esc_attr()`, `esc_url()`, `wp_json_encode()` for JS data, etc.), applied at the point of output, not the point of storage.
- **Nonces:** Every state-changing form submission, AJAX action, and REST write operation verifies a nonce (`wp_verify_nonce()` / `check_admin_referer()` / `check_ajax_referer()`, or the REST `X-WP-Nonce` header verified via `rest_cookie_check_errors` for cookie-authenticated requests).
- **Capability checks:** Every admin screen, AJAX handler, and REST route callback checks `current_user_can()` with an appropriately specific capability before performing any privileged action — never rely on nonce verification alone as an authorization mechanism.
- **Database access:** All SQL uses `$wpdb->prepare()` with placeholders for any interpolated value; never build queries via raw string concatenation of user input. Prefer the `$wpdb` helper methods (`get_results`, `get_row`, `insert`, `update`, `delete`) which handle escaping for you when used correctly.
- **REST API:** Every route defines an explicit `permission_callback` (never leave it unset or use `__return_true` for anything that touches non-public data); define argument schemas with type/sanitize/validate callbacks for every parameter.
- **AJAX:** Every `wp_ajax_*` handler checks both the nonce and the user's capability before doing any work; `wp_ajax_nopriv_*` handlers are used only where genuinely needed for logged-out functionality, and are held to the same input-validation standard.
- **File uploads:** Validate MIME type and file extension against an allow-list, use `wp_handle_upload()` where appropriate, never trust the client-supplied filename or MIME type alone, and store uploads outside of directly executable contexts where feasible.
- **Secrets/API keys:** Never log API keys or secrets in plaintext logs. Store third-party credentials using WordPress options with appropriate protection, mask them in the UI after entry (show only a partial/last-4 reveal), and provide a way to rotate/revoke them.
- **XSS prevention:** Treat all user-supplied and third-party-service-supplied data as untrusted on output, including data coming back from external APIs.
- **CSRF prevention:** Nonces on all state-changing requests, as above; do not perform state changes on `GET` requests.
- **SQL injection prevention:** As above — `$wpdb->prepare()` everywhere, no exceptions.
- **Privilege escalation prevention:** Never allow a lower-privileged user to modify data belonging to a higher-privileged user or to change their own capabilities/roles through a plugin-provided form or endpoint.
- Assume this plugin will undergo a professional third-party security audit before release, and build accordingly.

---

## 6. Performance Requirements

- Index database columns used in `WHERE`, `ORDER BY`, or `JOIN` clauses for frequently run queries (e.g., workflow status, execution timestamp).
- Avoid N+1 query patterns; batch-load related data.
- Use transients or the WordPress object cache for expensive or frequently repeated lookups (e.g., aggregated dashboard stats).
- Enqueue admin JS/CSS only on the specific screens that need them, never plugin-wide.
- Move anything that could be slow or unreliable (webhook delivery, third-party API calls, bulk operations) into background processing via WP-Cron or a queue mechanism, with retry/backoff logic, rather than blocking the main request.
- Paginate all admin list tables and REST collection endpoints; never load unbounded result sets.
- Provide log retention/pruning options so execution history tables do not grow unbounded.
- Profile and document expected performance characteristics for the "large site" case (many workflows, high trigger frequency, long execution history) as part of each relevant feature's self-review.

---

## 7. UI & UX Requirements

- Use a clean, modern visual style consistent with current WordPress admin design conventions (or a clearly justified custom design system), not a copy of Bit Pi's visual style.
- Ensure responsive behavior for common admin viewport widths.
- Meet basic accessibility expectations: proper label associations, visible focus states, sufficient color contrast, keyboard operability of the workflow builder's core actions, and ARIA roles/attributes on custom interactive widgets (canvas, node palette, modals).
- Design the workflow builder to minimize clicks for the most common actions (adding a node, connecting nodes, saving).
- Design a first-run onboarding experience (empty state with clear call-to-action, optional guided first workflow).
- Provide clear, specific, actionable error messages (not raw exception text) in both the UI and execution logs, with a way to view technical detail on demand for advanced users.

---

## 8. Extensibility Requirements

- Expose well-named, documented WordPress actions and filters at key extension points (before/after workflow execution, before/after node execution, on registration of node types, on registration of integrations).
- Design the node/trigger/integration registries so third-party code (or your own future code) can register new types without modifying core files.
- Document the public hook API and any public PHP classes/interfaces intended for extension in `/docs/hooks-reference.md`.

---

## 9. Development Workflow (Strict Process — Follow for Every Feature)

Never generate the whole plugin in one pass. For each item in the Section 3 roadmap, follow this loop exactly:

1. **Analyze** — Restate what this feature needs to do and how it fits the architecture from Section 2.
2. **Design** — Briefly describe the classes/files you will create or modify and why, before writing code.
3. **Explain** — Summarize the design in plain language as if briefing a reviewer, before implementation.
4. **Implement** — Write the code for this feature only. Do not start the next roadmap item in the same pass.
5. **Self-review** — Re-read your own diff looking for bugs, edge cases, and duplicated logic.
6. **Security review** — Walk the Section 5 checklist against the new code specifically.
7. **Performance review** — Walk the Section 6 checklist against the new code specifically.
8. **WPCS/standards review** — Run/simulate PHPCS and the JS/CSS linters against the new code; fix violations.
9. **Refactor** — Clean up anything the reviews surfaced.
10. **Report** — Summarize what was built, what was checked, and what (if anything) is deferred, before moving to the next roadmap item.

Do not compress or skip steps to move faster. A feature is not "done" until it has passed steps 5–8.

---

## 10. Code Review Standard

After every feature increment, review your own work as a strict senior reviewer would, explicitly checking for:

- Logic bugs and unhandled edge cases (empty inputs, missing optional fields, race conditions in queued execution, duplicate webhook delivery).
- Duplicated code that should be extracted into a shared method/service.
- Any of the Section 5 security items that may have been missed.
- Any of the Section 6 performance items that may have been missed.
- Coding standard violations (naming, formatting, missing PHPDoc).
- Maintainability concerns (classes growing too large, unclear responsibilities, tight coupling introduced by this change).

Document the outcome of this review briefly in the feature's entry in `/docs/internal/roadmap.md` before considering it complete.

---

## 11. Documentation Requirements

- Every class, method, and function has a complete PHPDoc block (`@param`, `@return`, `@throws` as applicable).
- Maintain `/docs/hooks-reference.md` listing every public action/filter, its parameters, and an example use case.
- Maintain a `readme.txt` in the standard WordPress.org plugin readme format (short description, installation, FAQ, changelog) using the project's real name and branding only.
- Maintain a developer-facing `README.md` describing local setup, build tooling, coding standards tooling, and how to run linters.
- Keep `/docs/internal/architecture.md` and `/docs/internal/roadmap.md` up to date as living documents throughout development.

---

## 12. Definition of Done / Release Readiness

Before considering the plugin ready for release, confirm:

- Zero references to Bit Pi (or any variant spelling) anywhere in the codebase, comments, documentation, or assets — do a full repository text search as a final check.
- Zero code, schema, or assets copied from Bit Pi — every implementation is independently designed per Section 2.
- All features on the Section 3 roadmap have completed the full loop in Section 9, including passing self-review, security review, and standards review.
- The plugin passes PHPCS against the WordPress ruleset with no unresolved warnings/errors.
- The plugin satisfies the WordPress.org Plugin Directory guidelines (proper readme.txt, appropriate licensing declared, no obfuscated code, no external code loading without disclosure, sanitized/escaped/nonce-protected throughout).
- Activation, deactivation, and uninstall routines are clean (uninstall removes the plugin's own data only when the user has opted in via a "remove data on uninstall" setting, and never touches unrelated data).
- Documentation from Section 11 is complete and accurate.

---

## 13. Communication Expectations

Throughout the project, before starting each new roadmap item, briefly state what you are about to build and why, in the order defined by Section 3. After finishing each item, briefly report what was built, what was reviewed, and anything flagged for follow-up. If you encounter a point where correctly avoiding replication of Bit Pi's approach requires a design decision with real tradeoffs, present the options and your recommendation rather than silently picking one.

Begin now with Phase One (Section 1). Do not proceed to Phase Two until the analysis document exists and has been reviewed.
