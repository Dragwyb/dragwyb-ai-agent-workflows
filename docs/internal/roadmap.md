# Feature Roadmap

Ordered, independently shippable increments. Each item follows the Section 9 development loop in full (Analyze → Design → Explain → Implement → Self-review → Security review → Performance review → WPCS/standards review → Refactor → Report) before the next item begins. Status and review notes are appended to each item as it completes.

1. **Core plugin bootstrap + activation/deactivation** — main file, container, autoloading, requirements check, activation/deactivation/uninstall skeleton (no tables yet beyond a version option). *(in progress)*
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
