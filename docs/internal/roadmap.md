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
