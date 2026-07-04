# Competitive Analysis: Reference Plugin (Bit Flows / Bit Pi)

**Status:** Phase One complete — awaiting maintainer review before Phase Two.  
**Scope:** Functional and architectural understanding only. No code, schema, naming, or assets from the reference plugin may be reused in our implementation.  
**Reference product:** Marketed as “Bit Flows”; internal slug/text domain `bit-pi`; PHP namespace `BitApps\Pi`; version observed `1.23.0`. Pro features live in a separate companion plugin.

This document is an original analysis for the development team. It must not ship in the distributable package (`/docs/internal/` is excluded from release builds).

---

## 1.1 Structural Inventory

### Top-level directories

| Directory / file | Responsibility |
| --- | --- |
| `bit-pi.php` | Thin bootstrap; loads `backend/bootstrap.php`. |
| `backend/` | All PHP application code: app, DB migrations, route hook files. |
| `backend/app/` | PSR-4 autoloaded application (`BitApps\Pi\`). |
| `backend/db/Migrations/` | Schema and option migrations run on activate / version bump. |
| `backend/hooks/` | Route registration files (`api.php` public routes, `ajax.php` admin routes). |
| `assets/` | Built SPA (Vite-hashed JS/CSS chunks), logos, fonts references. |
| `languages/` | POT and a large frontend-extracted strings PHP file for i18n. |
| `vendor/` | Composer deps (`bitapps/wp-kit`, `wp-database`, `wp-validator`) namespaced under `BitApps\Pi\Deps\` via Imposter. |
| `composer.json` | Autoload, scripts (PHPCS, PHPStan, PHPUnit), Imposter config. |
| `readme.txt` | WordPress.org-style marketing/readme. |

### Application sub-areas (`backend/app/`)

| Area | Responsibility |
| --- | --- |
| `Plugin.php`, `Config.php`, `Dotenv.php` | Singleton bootstrap, constants/options helpers, optional `.env` for dev. |
| `Providers/` | Activation/uninstall installer, admin/API hook loading, OAuth rewrite rules. |
| `Views/` | Admin shell: menu registration, asset enqueue, SPA mount point, plugin row links. |
| `HTTP/Controllers/` | Request handlers for flows, nodes, connections, webhooks, history, settings, proxy, etc. |
| `HTTP/Requests/` | Validation rule objects for write endpoints. |
| `HTTP/Middleware/` | Nonce check and `manage_options` capability check. |
| `Model/` | Active-record-style models over custom tables (via BitApps WPDatabase). |
| `Services/` | Application orchestration (flows, nodes, history, logs, connections, templates, polling). |
| `src/Flow/` | Execution engine: executor, node executor, variables, failure notifier. |
| `src/Queue/` | Async/background process (custom queue, not Action Scheduler). |
| `src/Integrations/` | Per-app trigger/action implementations (free tier subset). |
| `src/Tools/` | In-flow utilities: condition, delay, schedule, parsers, AI agent, etc. |
| `src/Authorization/` | OAuth2, API key, bearer, basic auth helpers with field-level encryption. |
| `src/Log/`, `src/Mcp/` | Execution logging helpers; MCP client for AI tool servers. |
| `Helpers/`, `Factories/`, `Rules/` | Shared utilities, proxy request parsing, validation rules. |

### PHP classes (by namespace / responsibility)

Summarized by responsibility rather than line-by-line inventory of ~200 files:

**Core / bootstrap (`BitApps\Pi`)**

- `Plugin` — Singleton; registers installer, providers, middleware map, DB migrate-on-load.
- `Config` — Slugs, versions, option/table prefixes, path/URL helpers, pro-plugin detection.
- `Dotenv` — Loads optional `.env` for local Vite/dev flags.

**Providers**

- `InstallerProvider` — Activation, deactivation, uninstall hooks; migration list; clears scheduled cleanup on deactivate.
- `HookProvider` — Loads API/AJAX route files; schedules daily history cleanup and MCP schema cleanup.
- `RewriteRuleProvider` — Pretty permalink for OAuth callback.

**Views**

- `Layout` — Registers top-level admin menu and hash-routed submenus.
- `Body` — Renders SPA root mount (`#bit-apps-root`) with loading placeholder.
- `Head` — Enqueues Vite-built (or dev-server) React app and localizes config/nonce.
- `HtmlTagModifier` — Admin HTML/body class tweaks for full-app layout.
- `PluginPageActions` — Plugins list “Settings / License” links.

**HTTP layer**

- Controllers: `FlowController`, `NodeController`, `FlowSettingsController`, `GlobalSettingsController`, `ConnectionController`, `WebhookController`, `WebhookDispatchController`, `HistoryController`, `DashboardController`, `TagController`, `CustomAppController`, `CustomMachineController`, `AuthorizationController`, `ProxyController`, `HookListenerController`, `FlowNodeTestController`, `McpClientController`, `SmtpController`, `SystemInfoController`, `RedirectController`, `OauthCallbackController`.
- Middleware: `NonceCheckerMiddleware`, `AdminCheckerMiddleware`.
- Request DTOs for validated writes (flows, nodes, connections, webhooks, proxy, etc.).

**Models**

- `Flow`, `FlowNode`, `FlowHistory`, `FlowLog`, `FlowTag`, `Tag`, `Connection`, `Webhook`, `CustomApp`, `CustomMachine`.

**Services**

- `FlowService`, `NodeService`, `FlowHistoryService`, `LogService`, `ConnectionService`, `CustomAppService`, `FlowTemplateImportService`, `Polling`, `SystemInfo`.

**Execution / queue**

- `FlowExecutor` — Orchestrates a run; extends background process handler; supports capture/run-once listener modes and re-execute.
- `NodeExecutor` — Resolves integration class by app slug (free or pro namespace) and runs actions.
- `NodeInfoProvider`, `GlobalNodeVariables`, `GlobalNodes`, `GlobalFlow` — Runtime context and variable store for a run.
- `FailedTaskNotifier` — Failure notification emails.
- `BackgroundProcessHandler`, `AsyncRequest` — Custom async queue via admin-ajax-style endpoint.

**Integrations (free plugin, illustrative)**

Triggers: WordPress core events, Contact Form 7, Elementor Form, WPForms, WooCommerce, custom app WP hooks, Google Sheet polling, webhook ingress, WP action-hook listener.

Actions / services: WordPress CRUD-style tasks, Mail, HTTP/API request, Webhook outbound, Telegram, WhatsApp, Google Sheets, MySQL, OpenAI, Gemini, Claude, DeepSeek, Perplexity, Groq, OpenRouter, ElevenLabs, MCP client tool, custom app actions, WordPress action hooks.

**Tools (in-flow nodes)**

Condition (typed comparisons), Delay, Schedule, Repeater, Iterator, DateTime, JSON/XML/CSV parsers, Image helper, AI Agent (chat models, memory, tool schemas).

**Authorization**

Factory + OAuth2 / Bearer / Basic / API key strategies; selective field encryption via `Hash` helper.

**Helpers / misc**

`Hash`, `Utility`, `Parser`, `Node`, `MixInputHandler`; `ProxyRequestParserFactory`; menu definition class.

### Admin menu structure

Single top-level menu (capability `manage_options`), SPA-driven via hash routes:

| Label | Route (hash) |
| --- | --- |
| Bit Flows (parent) | `admin.php?page=bit-pi` |
| Dashboard | `#/` |
| Flows | `#/flows` |
| Connections | `#/connections` |
| Webhooks | `#/webhooks` |
| Custom Apps | `#/custom-apps` |
| Settings | `#/settings` |
| System Info | `#/system-info` |
| SMTP | `#/smtp` |
| Support / License | `#/license` |

There are no classic PHP admin pages beyond the shell; all screens are client-side routes.

### Activation / deactivation / uninstall

**Activation**

- Version/PHP/WP checks via BitApps `Installer`.
- Runs migration list: flows, webhooks, flow_nodes (+ machine_label column), tags, connections, custom_apps, custom_machines, flow_histories, flow_logs, flow_tag pivot, plugin options (`db_version`, `installed`, `version`, `app_settings`).
- Multisite-aware activation path.

**Deactivation**

- Clears scheduled hooks: flow history cleanup, MCP tool schema cleanup.
- Flushes rewrite rules.

**Uninstall**

- Runs migration `down()` in reverse-ish order: drops custom tables and deletes prefixed options (including `secret_key`).
- No user-facing “keep data on uninstall” toggle observed; uninstall appears to always drop plugin data.

**Runtime scheduling**

- Daily `flow_history_cleanup` (log retention).
- Background process restart cron for stuck queues.
- Pro-only “cloud cron” option for more reliable scheduling.

### Database schema (functional description)

Tables use `{wpdb_prefix}bit_pi_{name}`. Application-level FKs are declared in migrations; indexes beyond PK/FK are minimal.

**`flows`**

- Identity, title, run counter, active flag.
- `map` / `data` / `settings` as longtext JSON (canvas graph, node payload, per-flow settings).
- Trigger type enum: `wp_hook`, `webhook`, `schedule`.
- Listener flags for capture / run-once testing modes.
- Timestamps.

**`flow_nodes`**

- Belongs to flow; client `node_id`; `app_slug` / `machine_slug` / optional `machine_label`.
- `field_mapping`, `data`, `variables` as serialized/JSON blobs.
- Cascade delete with flow.

**`flow_histories`**

- One row per execution attempt; optional `parent_history_id` for re-runs.
- Status enum: processing, success, failed, partial-success.
- Cascade with flow.

**`flow_logs`**

- Per-node log within a history: status, messages, input/output/details blobs.
- Optional parent node id migration exists but is commented out of the active migration list.
- Cascade with history.

**`tags` / `flow_tag`**

- Tag metadata and many-to-many link to flows.

**`connections`**

- App slug, auth type, display names, `encrypt_keys` (comma list of fields encrypted), `auth_details` longtext, status.

**`webhooks`**

- Title, optional unique `flow_id`, app/webhook slugs, IP restrictions, details JSON.

**`custom_apps` / `custom_machines`**

- User-defined “apps” and their trigger/action “machines” with config JSON, optional connection, status.

**Options (prefixed `bit_pi_`)**

- `db_version`, `installed`, `version`, `app_settings`, `global_settings` (log retention, notifications, cloud cron), `secret_key` (encryption key material), `settings`, plus caches for active trigger nodes.

### REST / AJAX surface (functional)

Routing uses BitApps WPKit `Route` helpers, not `WP_REST_Controller` subclasses. Namespace effectively `bit-pi/v1` for public API routes; admin routes are authenticated AJAX-style routes with middleware.

**Public (no admin auth)**

| Route | Purpose | Inputs | Outputs |
| --- | --- | --- | --- |
| OAuth callback | Completes OAuth redirect | Query params from provider | Redirect / session update |
| `webhook/callback/{trigger_id}` | Ingress for inbound webhooks | UUID trigger id; request body/headers | Accept / error; may start flow |
| `background_process_request` | Continues queued execution | Queue payload (noAuth group) | Process continuation |

**Admin (middleware: nonce + `manage_options`)**

| Area | Operations |
| --- | --- |
| Flows | Show, save, update, search, delete, re-execute, list variables |
| Flow / global settings | Get/update per-flow and global settings |
| Nodes | Show, store, save/upsert, update, clone, delete |
| Tags | List, save, update, status, delete |
| Connections | List, save, update, delete |
| Custom apps / machines | Full CRUD + status toggles |
| Webhooks | List, save, show, update, title update, delete |
| Testing | Test-run a single node; hook capture start/stop |
| Proxy | Server-side HTTP proxy for builder/config calls |
| Auth | Refresh OAuth tokens |
| History | Paginated list by flow; show one history |
| Dashboard / system info | Aggregated stats; environment diagnostics |
| SMTP | Status + install helper for companion SMTP plugin |
| MCP | List tools from configured MCP server |

CORS headers on the admin route file set `Access-Control-Allow-Origin: *` and handle OPTIONS preflight — notable for security review.

### Third-party integrations and credentials

**Credential storage**

- Connections table stores auth payloads; selected fields encrypted with AES-256-CBC (`Hash` helper) using a plugin option `secret_key` (generated as prefix + timestamp if missing — weak key derivation).
- Auth types: OAuth2, API key, bearer token, basic auth.
- OAuth uses a dedicated redirect URI (pretty or plain permalink).

**Integration categories (free + marketed pro)**

- Forms: CF7, Elementor, WPForms, Bit Form (and many more in pro marketing).
- Commerce / WP: WooCommerce, core WP user/post/plugin events.
- Messaging: Telegram, WhatsApp, Mail (WP mail / SMTP helper).
- Data: Google Sheets (action + polling trigger), MySQL (remote DB).
- AI: OpenAI, Gemini, Claude, DeepSeek, Perplexity, Groq, OpenRouter, ElevenLabs, built-in AI Agent + MCP client.
- Generic: HTTP request, outbound webhook, custom app/API connector, WP action hooks.

Pro plugin expands integration catalog and features (human-in-the-loop, cloud cron, additional apps). License UI is a frontend route (`#/license`); free plugin detects pro via class existence.

### Frontend / workflow builder

- **Stack:** React + TypeScript entry (`main.tsx` in dev), Vite build, code-split hashed assets under `assets/`.
- **Mount:** Single admin page injects `#bit-apps-root`; client hash router owns all screens.
- **Canvas:** Visual node graph stored in flow `map` / `data` JSON; nodes reference `app_slug` + `machine_slug`.
- **Config:** Side panels per node; field mapping and mix-input variable tokens from prior nodes.
- **Persistence:** REST/AJAX save endpoints for flows and nodes; autosave behavior is client-driven (not fully inspectable from PHP alone).
- **Testing:** Hook capture mode records trigger payload without full run; run-once and per-node test-run endpoints.
- **UX extras (marketing/strings):** Dark mode, templates, dashboard stats, execution history viewer.

### Licensing / updates / settings

- Free plugin is GPL and WordPress.org-oriented; pro is a separate plugin with license screen in the SPA.
- Global settings: log retention days (default 7), failure notification email, optional pro cloud cron.
- Per-flow settings: e.g. on-node-fail continue/stop, background process toggle.
- System Info screen exposes PHP/WP/extensions for support.
- No classic Settings API pages; settings are SPA + option storage.

---

## 1.2 Functional Understanding

### Triggers

**User:** Pick an event (form submit, WP hook, webhook URL, schedule, sheet change) as the start of a flow.  
**System:** Registers WP hooks only for active flows’ trigger apps (cached list); webhook rows map public UUID URLs to flows; schedules use WP-Cron (or pro cloud cron). Trigger payload is stored as the first history log and seeds node variables.

### Actions / integrations

**User:** Add action nodes (send email, call API, write sheet, AI call, etc.) and map fields from prior steps.  
**System:** `NodeExecutor` instantiates the integration class, runs `execute()`, logs input/output/status, and writes variables for downstream nodes.

### Conditional logic and tools

**User:** Branch with conditions; delay; loop; parse JSON/XML/CSV; format dates; run an AI agent with tools.  
**System:** `FlowToolsFactory` dispatches tool nodes; condition comparisons are typed (text, numeric, date, boolean, array). Delay/schedule interact with the background queue.

### Scheduling and background execution

**User:** Flows can run in the background so the triggering request does not wait for the full chain.  
**System:** Custom async queue (`BackgroundProcessHandler`) posts to an unauthenticated background endpoint; cron restarts stuck processes. Default flow setting enables background processing.

### Logging and history

**User:** Open a flow’s run history, inspect per-node input/output, re-run a failed execution.  
**System:** `flow_histories` + `flow_logs`; daily cleanup deletes histories older than retention days (cascade should remove logs via FK). Re-execute creates a new history linked via `parent_history_id`.

### Error handling

**User:** Configure continue-vs-stop on node failure; optional email on failure.  
**System:** Status rollup to success / failed / partial-success; `FailedTaskNotifier` for emails.

### Permissions

**User:** Only administrators manage the product.  
**System:** All admin routes require `manage_options` + nonce. No granular roles (editor cannot manage flows). Public webhook and background endpoints are intentionally open but keyed by UUID / queue protocol.

### Multi-workflow management

**User:** List/search flows, tag them, activate/deactivate, clone nodes, build custom apps.  
**System:** Flows table + tags pivot; custom apps/machines for user-defined API connectors.

### User journey (end-to-end)

1. Install/activate → tables and options created.  
2. Open admin menu → SPA dashboard.  
3. Create flow → land on visual builder.  
4. Choose trigger → optionally capture a sample payload.  
5. Add actions/tools → map fields → save.  
6. Activate flow → real events execute (sync or background).  
7. Monitor history → debug node I/O → adjust mappings or re-execute.  
8. Manage connections (OAuth/API keys), webhooks, custom apps, global retention/notifications.  
9. Optional pro upgrade for more apps / cloud cron / human-in-the-loop.

---

## 1.3 Strengths and Weaknesses Assessment

### Architecture

**Strengths**

- Clear-ish layering: Controllers → Services → Models; execution engine separated under `src/Flow`.
- Integration interface (`ActionInterface`) and hook-register interface for triggers.
- Request validation objects on many write paths.
- Migration-based schema evolution.
- Background execution and log retention exist.

**Weaknesses**

- Heavy dependence on proprietary BitApps kits (not standard WP REST controllers / `$wpdb` repositories).
- Models are active-record style; limited domain layer independent of persistence.
- `FlowExecutor` is large and mixes queue, listener modes, and orchestration.
- Integration discovery by string class paths is brittle.
- Free/pro split via parallel namespaces increases complexity.
- God-adjacent config singleton and global variable stores during execution.

### Security

**Strengths**

- Admin routes gated by nonce + `manage_options`.
- Many inputs validated via request classes / validator package.
- Selective encryption of connection secrets.
- Webhook trigger IDs validated as UUIDs.

**Weaknesses / risks**

- **SSRF:** `ProxyController` intentionally allows arbitrary URLs; private-IP protection is TODO/commented out. High severity for an admin-only proxy (still dangerous on compromised admin sessions and for internal network scanning).
- **CORS `*`** on authenticated admin AJAX route bootstrap is overly permissive.
- **Background process endpoint** is in `noAuth` group — must rely entirely on internal queue secrets/nonces; any weakness is critical.
- **Encryption key** is a weak, predictable option value (`prefix + time()`), stored in `wp_options` in plaintext; not using WordPress salts/`wp_salt`.
- **Uninstall always wipes data** — no opt-in retention (data-loss risk, not direct exploit).
- **Capability model** is all-or-nothing admin; no custom caps for agencies/multi-author sites.
- Logs store full input/output — may retain PII/secrets from third-party responses.
- `maybe_unserialize` usage in queue/helpers — lower risk if data is trusted, but worth hardening.
- Some migrations use unprepared `SHOW COLUMNS` / `ALTER TABLE` (table names internal, lower risk).

### Performance

**Strengths**

- Background processing for long flows.
- Trigger hook registration limited to active flows (cached).
- History list is paginated in the API.
- Log retention cleanup job.

**Weaknesses**

- Minimal secondary indexes (e.g. filtering histories by `flow_id` + `created_at` / status may degrade).
- Large JSON blobs (`map`, `data`, logs input/output) grow tables quickly.
- Custom queue may be less robust than Action Scheduler under load.
- SPA is large (many chunks); acceptable if only enqueued on plugin pages (it is screen-gated).
- Synchronous webhook path can still kick off work depending on settings/listener mode.
- Cleanup deletes histories by date expression — ensure logs cascade and consider batching for large tables.

### UX

**Strengths**

- Modern full-page SPA builder (Zapier-like mental model).
- Hook capture and node test-run reduce “save and pray” friction.
- Dashboard, tags, templates (marketing), dark mode.

**Weaknesses**

- Steep learning curve; onboarding depends on empty states/templates (quality varies).
- Everything requires administrator — not ideal for ops roles.
- License/support mixed into product nav.
- Accessibility of custom canvas widgets is unknown from PHP; custom canvases often fail keyboard/ARIA expectations.
- Error messages sometimes generic (“Something went wrong” on proxy failures).

### Code quality

**Strengths**

- Composer toolchain: PHPCS/WPCS, PHPStan, PHPUnit scripts present.
- Consistent namespacing; one class per file generally.
- Validation request objects.

**Weaknesses**

- Inconsistent PHPDoc depth.
- Duplication across AI provider integrations (similar action/service patterns).
- Mixed concerns in controllers vs services in places.
- Frontend source is not in the distributed plugin (only built assets) — harder to audit.

### Scalability

**Strengths**

- Retention setting prevents infinite log growth (if cron runs).
- Background execution avoids some request timeouts.

**Weaknesses**

- Unbounded growth still possible if cron is broken (common on low-traffic WP sites) — pro cloud cron acknowledges this.
- No obvious partitioning/archival strategy beyond delete-by-age.
- High-frequency triggers (e.g. busy Woo store) could flood histories/logs and queue.
- No multi-worker queue visibility/metrics beyond system info.

---

## 1.4 Opportunity List (prioritized)

These opportunities become the backbone of our roadmap (Phase Two / Section 3). Each item is a functional problem and a better approach — **not** a prescription to copy the reference implementation.

1. **Hardened outbound HTTP / no open proxy**  
   *Problem:* Admin proxy can hit arbitrary URLs including internal IPs.  
   *Why it matters:* Classic SSRF; audit failure.  
   *Approach:* Deny private/link-local/metadata IPs; allow-list schemes; optional domain allow-list; never offer a generic open proxy if avoidable (integrations call out directly with validated URLs).

2. **Strong secret storage**  
   *Problem:* Weak encryption key material in options.  
   *Why it matters:* DB dumps expose API keys easily.  
   *Approach:* Derive keys from `wp_salt` + site-specific secret; encrypt at rest; mask secrets in UI; support rotation.

3. **Standard WordPress REST API**  
   *Problem:* Custom router obscures WP conventions and permission patterns.  
   *Why it matters:* Easier audits, better tooling, familiar extension points.  
   *Approach:* `WP_REST_Controller` per resource, schema-validated args, explicit `permission_callback`s, Application Passwords friendly.

4. **Clear domain + repository architecture**  
   *Problem:* Active-record models and fat executors blur boundaries.  
   *Why it matters:* Testability and long-term maintenance.  
   *Approach:* Domain entities (Workflow, Node, Execution) + repositories using `$wpdb->prepare()` + thin application services.

5. **Reliable queue (Action Scheduler or equivalent)**  
   *Problem:* Custom background process and WP-Cron fragility.  
   *Why it matters:* Missed runs on low-traffic sites; hard-to-debug stuck jobs.  
   *Approach:* Action Scheduler (widely used in Woo ecosystem), retries with backoff, admin queue UI.

6. **Indexed, normalized execution logging with retention**  
   *Problem:* Large blobs and thin indexes.  
   *Why it matters:* History UI and cleanup slow down at scale.  
   *Approach:* Indexed `(workflow_id, status, created_at)`; optional payload externalization; batched pruning; default retention with user control.

7. **Granular capabilities**  
   *Problem:* `manage_options` only.  
   *Why it matters:* Agencies need editor-level operators without full admin.  
   *Approach:* Custom caps (`manage_workflows`, `edit_workflows`, `view_executions`) mapped to roles.

8. **First-class extensibility**  
   *Problem:* Third parties must mimic internal class paths / pro namespaces.  
   *Why it matters:* Ecosystem growth.  
   *Approach:* Public registries and documented hooks (`{prefix}/nodes/register`, etc.) without modifying core.

9. **Safer public ingress**  
   *Problem:* Webhooks are public by design; background endpoint is unauthenticated.  
   *Why it matters:* Abuse, replay, DoS.  
   *Approach:* Optional signing secrets, IP allow-lists (reference has IP restrictions field — keep as a designed feature), rate limits, idempotency keys.

10. **Onboarding and accessibility**  
    *Problem:* Power-user builder without strong guided first run / a11y guarantees.  
    *Why it matters:* Activation-to-value time; WP.org expectations.  
    *Approach:* Guided first workflow, empty states, keyboard-operable builder core actions, ARIA on custom widgets.

11. **Opt-in data removal on uninstall**  
    *Problem:* Silent full wipe or (conversely) orphaned tables if mishandled.  
    *Why it matters:* Trust and compliance.  
    *Approach:* Setting “remove all data on uninstall” defaulting to off; uninstall respects it.

12. **MVP integration set, quality over quantity**  
    *Problem:* Reference competes on 300+ apps; free tier still sprawling.  
    *Why it matters:* Maintenance cost and security surface.  
    *Approach:* Ship excellent core (WP hooks, forms subset, webhook, HTTP, email) and a clean custom connector; add apps incrementally.

13. **Versioned workflow definitions**  
    *Problem:* Monolithic JSON map/data without explicit schema version.  
    *Why it matters:* Safe migrations of builder format.  
    *Approach:* `definition_version` column + migrators for graph format changes.

14. **No branding/license upsell in core nav for our product decisions**  
    *Problem:* License/support clutter (product choice).  
    *Why it matters:* Cleaner UX for an open product.  
    *Approach:* Defer licensing entirely unless/until we have a pro SKU; keep settings IA clean.

---

## 1.5 Explicit Non-Goals

We will **not** replicate the following from the reference product:

1. **Any code, schema names, CSS/JS structure, or branding** — legal and product requirement.  
2. **BitApps proprietary framework dependency** — we use WordPress APIs + Composer PSR-4 of our own design.  
3. **300+ integration catalog at launch** — out of scope; quality core set only.  
4. **Pro companion plugin / license activation UI** — not in initial roadmap unless product direction changes.  
5. **Cloud cron SaaS dependency** — prefer on-site Action Scheduler + clear docs for real system cron.  
6. **Open admin HTTP proxy** — reject this pattern; integrations own their HTTP calls.  
7. **Weak reversible encryption with predictable keys** — non-negotiable to do better.  
8. **MCP / full AI agent platform in v1** — valuable later; not required for MVP automation.  
9. **Human-in-the-loop approval product** — defer.  
10. **SMTP plugin installer upsell screen** — out of scope; document using WP mail / existing SMTP plugins.  
11. **Copying their visual design, menu labels, or hash-route IA** — we design our own IA in Phase Two.  
12. **Always-on uninstall data deletion without consent** — we will use explicit opt-in.

---

## Analysis metadata

- **Reference path analyzed:** `wp-content/plugins/bit-pi` (sibling of this project).  
- **Method:** Directory walk, migration and route inventory, bootstrap/execution/security-sensitive file review. Frontend conclusions drawn from enqueue entrypoints and built asset manifest patterns (React/Vite), not from copying UI code.  
- **Next gate:** Maintainer sanity-check of this document, then Phase Two (`docs/internal/architecture.md`) and roadmap (`docs/internal/roadmap.md`). No plugin bootstrap or production code until that review.
