# Feature Roadmap

Ordered, independently shippable increments. Each item follows the Section 9 development loop in full (Analyze → Design → Explain → Implement → Self-review → Security review → Performance review → WPCS/standards review → Refactor → Report) before the next item begins. Status and review notes are appended to each item as it completes.

1. **Core plugin bootstrap + activation/deactivation** — main file, container, autoloading, requirements check, activation/deactivation/uninstall skeleton (no tables yet beyond a version option).
2. **Database layer + Workflow CRUD** — migrations for `wfa_workflows`, `wfa_workflow_nodes`; `WorkflowRepository`; `WorkflowService`; internal-only (no UI/REST yet).
3. **REST API for workflows** — `WP_REST_Controller` for workflow CRUD with schema + permission callbacks.
4. **Admin shell + menu** — top-level admin menu, screen scaffolding, scoped asset enqueueing.
5. **Node/trigger/action registry** — `NodeTypeRegistry`, `TriggerInterface`, `ActionInterface`, first built-in node types (e.g. WP hook trigger, HTTP request action).
6. **Visual builder shell (front-end)** — React app shell, canvas rendering, node palette, save/autosave wired to the REST API.
7. **Execution engine (synchronous)** — `WorkflowExecutionService`, `NodeExecutionService`, `wfa_workflow_runs` / `wfa_workflow_run_logs` tables and repositories.
8. **Background/queued execution** — WP-Cron-driven batch claiming for webhook/async runs, retry/backoff.
9. **Logging & execution history UI** — paginated runs list, run detail view, re-run action.
10. **Settings screens** — general, retention/logging, advanced; uninstall opt-in data removal setting.
11. **Connections + credential encryption** — `wfa_connections` table, `ConnectionService`, encryption-at-rest via `wp_salt()`-derived key.
12. **First real integrations** — WordPress core hooks trigger, outbound HTTP action, email action.
13. **Webhooks (inbound)** — `wfa_webhooks` table, public ingress endpoint, optional signing secret + IP allow-list enforcement.
14. **Roles & capabilities** — custom capabilities layered over `manage_options`.
15. **Additional integrations** — expand catalog incrementally based on user demand (forms plugin, WooCommerce, etc.), one integration per increment.
16. **Onboarding & polish** — empty states, guided first workflow, accessibility pass on the builder.
17. **Extensibility documentation** — finalize `/docs/hooks-reference.md` against all hooks actually shipped.

This list will be updated as work progresses; completed items get a review summary appended below their entry.

---

## Completed Item Log

### 1. Core plugin bootstrap + activation/deactivation — done

**Built:** `workflow-automate.php` bootstrap with a backward-compatible PHP version gate (plain procedural code, no 7.4+ syntax, so it can report an unsupported-PHP notice instead of fataling); `src/autoload.php` dependency-free PSR-4 fallback autoloader used only when no Composer `vendor/autoload.php` exists; `src/Core/{Plugin,Container,Requirements,Activator,Deactivator,Uninstaller,Options}.php`; top-level `uninstall.php`. `composer.json` carries the real PSR-4 mapping for environments where Composer is available.

**Self-review:** Verified activation/deactivation/uninstall call chains, confirmed `uninstall.php` doesn't depend on any constant not defined within its own require chain, removed a dead constant define left over from an earlier draft.

**Security review:** No user input handled yet (N/A for nonces/capability/SQL/output-escaping on those axes); the two admin notices emitted use `esc_html()`; `wp_die()` on failed activation only renders our own generated strings, no reflected request data; activation/deactivation hook callbacks are inherently capability-gated by WordPress core's plugin-activation flow.

**Performance review:** No queries, no scheduled events, no enqueued assets introduced — nothing to optimize yet; structure leaves clear extension points for later increments to add cron cleanup without touching this class's public API.

**Standards review:** Manually verified WPCS-style spacing (`if ( ! defined( ... ) )`) consistently across all new files (no `phpcs`/`php` CLI available in this environment to run automated checks — flagged as a known gap; see Report below). All new classes use `declare(strict_types=1)` and full PHPDoc blocks per Section 4.

**Refactor:** Fixed one spacing inconsistency in `workflow-automate.php`; removed a redundant constant define in `uninstall.php`; generalized one code comment in `Uninstaller.php` to avoid referencing the competitor analysis file by name from within shipped source.

**Deferred / follow-up:** Automated PHPCS/PHPUnit/PHPStan runs are blocked in this environment (no `php`/`composer` on `PATH`) — must be run in CI or a proper local PHP environment before this increment is considered fully verified against Section 12's "passes PHPCS with no unresolved warnings" gate.

### 2. Database layer + Workflow CRUD — done

**Built:** `src/Database/{Migration,MigrationRunner,SchemaMigrations,Table}.php` and `src/Database/Migrations/{CreateWorkflowsTable,CreateWorkflowNodesTable}.php` (schema via `dbDelta()`, idempotent applied-migration tracking); `src/Domain/{Workflow,WorkflowNode}.php` (immutable, persistence-agnostic entities); `src/Persistence/{WorkflowRepository,WorkflowNodeRepository}.php` (all `$wpdb` access, fully parameterized); `src/Service/WorkflowService.php` (CRUD orchestration, status transitions, node management). Wired migrations into `Activator::activate()` (real schema creation on activation) and defensively into `Plugin::load()` (capability-gated re-check for already-active sites). Registered `WorkflowRepository`, `WorkflowNodeRepository`, and `WorkflowService` as container singletons. Extended `Uninstaller` to drop tables (via each migration's `down()`, reverse order) when the opt-in data-removal setting is enabled.

**Self-review:** Verified insert/update/delete/list code paths for each repository, confirmed partial-update methods only touch explicitly provided columns, confirmed `find()` calls after insert/update use `include_trashed = true` so a caller immediately sees a row it just wrote even if soft-deleted logic were ever layered on top. Confirmed the workflow→node cascade only happens through `WorkflowService::delete($id, true)`, never implicitly.

**Security review:** No REST/AJAX/admin-facing entry points yet, so most of Section 5's request-input checklist remains N/A by design (deferred to roadmap item 3, which owns request sanitization/validation per the architecture's layering). All SQL goes through `$wpdb->prepare()` or the `$wpdb->insert()/update()/delete()` helpers — no raw string concatenation of values. The only direct string interpolation is the plugin's own hardcoded table names (never user input), each annotated with a `phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared` and a comment explaining why. `wp_json_encode()` is used for all JSON columns instead of raw `json_encode()`.

**Performance review:** Added indexes on `status` and `deleted_at` on `wfa_workflows`, and on `workflow_id` (plus a unique compound index) on `wfa_workflow_nodes`, per the architecture document — directly targeting the reference product's thin-indexing weakness. `paginate()` clamps `per_page` to a max of 100 and always applies `LIMIT`/`OFFSET`; `findByWorkflow()` caps at 1000 rows as a defensive bound. The defensive re-migration check in `Plugin::load()` is gated behind `is_admin() && current_user_can('manage_options')` so it never runs on front-end requests, and the applied-migrations check itself is a single cheap option read once schema is up to date.

**Standards review:** Consistent WPCS-style spacing and full PHPDoc across all new files (manually verified; no `phpcs` CLI available — see known gap below). Corrected two inaccurate `phpcs:ignore` annotations found during self-review (a `WordPress.DB.PreparedSQL.NotPrepared` suppression was mistakenly added on lines that already call `prepare()` directly — removed as noise).

**Refactor:** Removed the redundant/inaccurate ignore comments noted above; added an "Implementation note" to `docs/internal/architecture.md` §2.3 documenting the dbDelta/no-SQL-FK decision now that it's actually implemented, so the design doc and code stay in sync.

**Deferred / follow-up:** Same PHPCS/PHPUnit/PHPStan CLI gap as item 1 — flagged again, not yet resolved. `WorkflowService` and the repositories are unused by any request-facing code until roadmap item 3 (REST API) lands; they are covered structurally but not yet exercised end-to-end without a running WordPress + MySQL environment, which this session also cannot access directly.

### 3. REST API for workflows — done

**Built:** `src/Rest/WorkflowsController.php`, a `WP_REST_Controller` subclass exposing `wfa/v1/workflows` — collection `GET`/`POST`, item `GET`/`PUT`/`PATCH`/`DELETE`, and a custom `POST .../{id}/restore` action for un-trashing. Includes a full JSON-Schema `get_item_schema()`, `get_collection_params()`, and `prepare_item_for_response()` with `_links` (self/collection), following the same `WP_REST_Controller` contract WordPress core itself uses (`get_endpoint_args_for_item_schema()`, `get_public_item_schema()`, `X-WP-Total`/`X-WP-TotalPages` pagination headers, `{deleted, previous}` delete response) — a deliberate choice to use documented core conventions rather than the reference plugin's own bespoke router. `src/Rest/RestApi.php` is a thin `rest_api_init` bootstrap so `Plugin::load()` only gains one delegated call. Node-level (`workflow_nodes`) REST endpoints are intentionally out of scope for this increment.

**Self-review:** Confirmed every route has an explicit `permission_callback`. Confirmed `update_item()` runs `title`/`graph`/`settings` through `WorkflowService::update()`'s own validation before separately calling `changeStatus()` when a `status` field is present, so status changes can't bypass the enum check. Confirmed `delete_item()` and `restore_item()` both look the workflow up first and return a 404 before attempting the mutation, rather than inferring "not found" from the service's boolean return alone. Confirmed `graph`/`settings` are cast to `(object)` in `prepare_item_for_response()` so an empty PHP array never serializes as JSON `[]` when the schema promises an `object`.

**Security review:** Every `*_permissions_check` uses `current_user_can('manage_options')`, returning `rest_authorization_required_code()` (distinguishes 401 logged-out vs 403 logged-in-but-forbidden) — no route uses `__return_true`. No manual nonce check was added beyond that: WordPress's own `rest_cookie_check_errors` filter already enforces `X-WP-Nonce` on cookie-authenticated REST requests before any permission callback runs, so an additional check would be redundant and isn't the standard REST-API pattern. Write endpoints are validated in two independent layers — the JSON-Schema `args` (type/enum, `sanitize_text_field` on `title`) generated by `get_endpoint_args_for_item_schema()`, and `WorkflowService`'s own domain validation (non-empty title, status enum) — neither layer trusts the other. No request data reaches SQL or HTML directly; every response goes through `rest_ensure_response()`.

**Performance review:** The list endpoint delegates straight to the already-indexed, paginated `WorkflowRepository::paginate()` from item 2; `per_page` is clamped to `[1, 100]` at the schema level in addition to the repository's own clamp, so no unbounded query is reachable from this controller. `get_item`/`get_items` each issue exactly one `SELECT` — node data is deliberately not joined/expanded on the workflow response yet, avoiding an N+1 the reference product doesn't obviously guard against either.

**Standards review:** PHP 7.4 compatibility maintained — no union return types on permission-check/callback methods (matching how `WP_REST_Controller`'s own parent methods are also left untyped), documented instead via PHPDoc `@return`. WPCS-style array/spacing conventions verified manually (no `phpcs` CLI in this environment — same known gap as items 1–2).

**Refactor:** Caught during self-review: the schema-derived `POST` args would have advertised an accepted `status` field even though `create_item()` never forwards it (new workflows always start as `draft`, enforced in `WorkflowService::create()`). Added `getCreateArgs()` to strip `status` from the create-only arg set so the advertised API surface matches actual behavior.

**Deferred / follow-up:** Same PHPCS/PHPUnit/PHPStan CLI gap as items 1–2, still unresolved. This increment is verified structurally only — no running WordPress + MySQL environment is available in this session to exercise `wfa/v1/workflows` over real HTTP; flagged for a manual or CI smoke test before this item is considered fully verified against Section 12's gates. Node-level REST endpoints are deferred to whichever later increment first needs them (builder shell, item 6, or execution engine, item 7).
