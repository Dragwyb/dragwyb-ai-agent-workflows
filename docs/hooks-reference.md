# Extensibility Reference

Public actions, filters, and PHP contracts third-party code (or your own companion plugins) may use to extend Workflow Automate.

This document is the **authoritative list of shipped extension points** as of roadmap item 17. It is kept in sync with the code: every `do_action( 'wfa/…' )` / `apply_filters( 'wfa/…' )` in `src/` appears here, and nothing is listed that is not implemented.

Related docs:

- [`docs/integrations.md`](integrations.md) — built-in node types and credentials
- [`docs/rest-api.md`](rest-api.md) — HTTP API (not an extension surface for PHP, but useful for headless clients)
- [`docs/internal/architecture.md`](internal/architecture.md) — internal design notes (development only)

---

## Actions

### `wfa/loaded`

Fires once, on `plugins_loaded`, after the plugin has confirmed its PHP/WordPress version requirements (and OpenSSL for credential encryption) are met. Service bindings, node-type registration hooks, REST routes, and admin screens are registered by this point.

**When it does not fire:** requirements check failed (unsupported PHP/WordPress, or missing OpenSSL). The plugin shows an admin notice and does not boot.

**Parameters**

| Name | Type | Description |
| --- | --- | --- |
| `$container` | `WorkflowAutomate\Plugin\Core\Container` | The plugin's service container. Prefer resolving known services (e.g. `WorkflowService::class`) over reaching into private internals. |

**Example**

```php
add_action(
	'wfa/loaded',
	function ( $container ) {
		// Safe to assume minimum PHP/WP/OpenSSL requirements are met.
		$workflows = $container->get( \WorkflowAutomate\Plugin\Service\WorkflowService::class );
	}
);
```

---

### `wfa/nodes/register`

Fires once per request, on `init` (default priority 10), so core and third-party code can register trigger and action node types into `NodeTypeRegistry`.

Fired on `init` (not during this plugin's own `plugins_loaded` handler) so that any plugin that hooks this action from inside its own `plugins_loaded` callback is guaranteed to have registered its listener before this fires, regardless of plugin load order.

Built-in types register on this same action via `Integration\BuiltInNodeTypes` (including optional co-plugin types such as WooCommerce, only when that plugin is active).

**Parameters**

| Name | Type | Description |
| --- | --- | --- |
| `$registry` | `WorkflowAutomate\Plugin\Service\NodeTypeRegistry` | Call `registerTrigger()` / `registerAction()`. Duplicate slugs trigger `_doing_it_wrong()` and overwrite the previous registration. |

**Example — custom action**

```php
add_action(
	'wfa/nodes/register',
	function ( $registry ) {
		$registry->registerAction( new My_Log_Message_Action() );
	}
);

class My_Log_Message_Action implements \WorkflowAutomate\Plugin\Domain\Contracts\ActionInterface {

	public function slug(): string {
		return 'my_plugin_log_message';
	}

	public function label(): string {
		return __( 'Log message', 'my-plugin' );
	}

	public function description(): string {
		return __( 'Writes a line to the PHP error log.', 'my-plugin' );
	}

	public function configSchema(): array {
		return array(
			'message' => array(
				'type' => 'string',
				'label' => __( 'Message', 'my-plugin' ),
				'required' => true,
			),
		);
	}

	public function execute( array $config, array $context ): array {
		$message = isset( $config['message'] ) ? (string) $config['message'] : '';

		if ( '' === $message ) {
			return array(
				'success' => false,
				'error' => __( 'No message configured.', 'my-plugin' ),
			);
		}

		// Prefer reporting failures over throwing for expected errors.
		error_log( $message );

		return array( 'success' => true );
	}
}
```

**Example — custom trigger**

```php
add_action(
	'wfa/nodes/register',
	function ( $registry ) {
		$registry->registerTrigger( new My_Daily_Cron_Trigger() );
	}
);

class My_Daily_Cron_Trigger implements \WorkflowAutomate\Plugin\Domain\Contracts\TriggerInterface {

	public function slug(): string {
		return 'my_plugin_daily_cron';
	}

	public function label(): string {
		return __( 'Daily cron', 'my-plugin' );
	}

	public function description(): string {
		return __( 'Starts the workflow once per day via WP-Cron.', 'my-plugin' );
	}

	public function configSchema(): array {
		return array();
	}

	public function bind( array $config, callable $on_fire ): void {
		// Do not call $on_fire from inside bind() — only when the event fires.
		add_action(
			'my_plugin_daily_event',
			static function () use ( $on_fire, $config ) {
				$on_fire(
					array(
						'source' => 'my_plugin',
						'fired_at' => gmdate( 'c' ),
					),
					$config
				);
			}
		);
	}
}
```

A custom action implements `ActionInterface`; a custom trigger implements `TriggerInterface`. Both extend `NodeTypeInterface` (metadata only — do not implement `NodeTypeInterface` alone).

Registered types appear automatically in:

- the builder palette and config panel (`GET /wfa/v1/node-types` serializes `configSchema()`)
- live trigger binding (`WorkflowTriggerBinder` on `init` priority 20)
- the execution engine (`NodeExecutionService`)

No front-end changes are required for a new node type, as long as `configSchema()` uses field types the builder already understands (see [Config schema field types](#config-schema-field-types) below).

---

### `wfa/workflow/before_run`

Fires immediately before a run's **nodes** execute, inside `WorkflowExecutionService::executeNodes()`.

By this point a `wfa_workflow_runs` row already exists with status `running` (created by `run()`, `rerun()`, or by `BackgroundRunner` claiming a previously `queued` row).

**Fires for:**

- Manual "Run now" / REST `POST …/workflows/{id}/run`
- Admin "Re-run"
- Background execution of a claimed queued run (live triggers and webhooks when background execution is enabled)

**Does not fire for:**

- `queue()` alone (row inserted as `queued`; hooks run later when the run is claimed and executed)
- A claimed run whose workflow was deleted/trashed before execution (finished as `failed` without entering `executeNodes()`)

**Parameters**

| Name | Type | Description |
| --- | --- | --- |
| `$workflow_id` | `int` | The workflow about to run. |
| `$trigger_payload` | `array` | Data from the triggering event; empty array for a manual run with no payload. |

---

### `wfa/workflow/after_run`

Fires immediately after a run finishes executing nodes, whatever its final status (`success`, `failed`, or `partial`). The run row has already been finalized (`finished_at` set) and `run_count` incremented.

Same fire / no-fire rules as `wfa/workflow/before_run`.

**Parameters**

| Name | Type | Description |
| --- | --- | --- |
| `$run` | `WorkflowAutomate\Plugin\Domain\WorkflowRun` | The completed run (`id()`, `workflowId()`, `status()`, `triggerPayload()`, etc.). |
| `$trigger_payload` | `array` | Same payload passed to `before_run`. |

**Example**

```php
add_action(
	'wfa/workflow/after_run',
	function ( $run, $trigger_payload ) {
		if ( 'failed' === $run->status() ) {
			// Notify an external monitor, write a custom log, etc.
		}
	},
	10,
	2
);
```

---

### `wfa/node/before_execute`

Fires immediately before a single **action** node executes. Trigger nodes are skipped by the execution loop and never pass through this hook.

**Parameters**

| Name | Type | Description |
| --- | --- | --- |
| `$node` | `WorkflowAutomate\Plugin\Domain\WorkflowNode` | The node about to execute (`nodeType()`, `label()`, `config()`, `clientNodeId()`, etc.). |
| `$context` | `array` | Runtime data: `trigger` (payload) and `nodes` (map of prior action results keyed by client node id). |

---

### `wfa/node/after_execute`

Fires immediately after a single action node executes, whether it succeeded or failed. The `$result` array is what was returned from `ActionInterface::execute()` (or a synthetic failure if the action threw or returned an invalid shape).

**Parameters**

| Name | Type | Description |
| --- | --- | --- |
| `$node` | `WorkflowAutomate\Plugin\Domain\WorkflowNode` | The node that just executed. |
| `$result` | `array` | At minimum `{ success: bool, error?: string }`. Action-specific keys may be present (e.g. `status_code`, `body` for HTTP Request). Never includes decrypted connection secrets. |
| `$context` | `array` | Same shape as `before_execute` (prior node outputs are those completed *before* this node). |

**Example**

```php
add_action(
	'wfa/node/after_execute',
	function ( $node, $result, $context ) {
		if ( empty( $result['success'] ) ) {
			error_log(
				sprintf(
					'Workflow node %s failed: %s',
					$node->nodeType(),
					$result['error'] ?? 'unknown'
				)
			);
		}
	},
	10,
	3
);
```

---

## Filters

No public `wfa/…` filters are shipped.

An `wfa/integrations/register` filter (and an `IntegrationInterface` above individual node types) was considered in early architecture notes and is **explicitly deferred**: optional co-plugin integrations register individual triggers/actions on `wfa/nodes/register` only when the co-plugin is active (see `BuiltInNodeTypes` and `docs/integrations.md`). That pattern does not require a second registry layer. If a future release adds filters, they will be documented here.

---

## Public PHP contracts

### `TriggerInterface` / `ActionInterface`

| | Trigger | Action |
| --- | --- | --- |
| Namespace | `WorkflowAutomate\Plugin\Domain\Contracts` | same |
| Register with | `$registry->registerTrigger( $instance )` | `$registry->registerAction( $instance )` |
| Lifecycle method | `bind( array $config, callable $on_fire ): void` | `execute( array $config, array $context ): array` |
| Rules | Do **not** call `$on_fire` from inside `bind()` — only when the real event fires. `$on_fire( array $payload, array $config )`. | Do **not** throw for expected failures; return `{ success: false, error: '…' }`. Throwing is reserved for programmer error; the engine catches `Throwable` and records a failed node either way. |

Shared metadata (`slug()`, `label()`, `description()`, `configSchema()`) comes from `NodeTypeInterface`. **Slugs must be stable** once workflows may reference them (stored in `wfa_workflow_nodes.node_type` and in the builder graph).

### Config schema field types

The builder renders `configSchema()` generically. Supported `type` values:

| `type` | UI control |
| --- | --- |
| `string` (default) | Text input |
| `integer` | Text input (stored as string in the graph unless you coerce in `execute()`) |
| `boolean` | Toggle |
| `object` / `array` | JSON textarea (invalid JSON is not committed) |
| `connection` | Connection picker (`GET /wfa/v1/connections`); value is a connection id (`0` = none) |

Unknown types fall back to a text input.

### `NodeTypeRegistry`

Passed only to `wfa/nodes/register` listeners. Public methods for registrars:

- `registerTrigger( TriggerInterface $trigger ): void`
- `registerAction( ActionInterface $action ): void`

Lookup methods (`trigger()`, `action()`, `triggers()`, `actions()`) are used by the plugin's own engine and REST layer; third-party code normally only registers.

### `Core\Container`

Available on `wfa/loaded`. Use `get( string $id )` for documented service class names (e.g. `WorkflowService::class`, `ConnectionService::class`). Treat other bindings as internal unless documented here.

### Capabilities

Admin and authenticated REST routes check granular capabilities (see `Core\Capabilities`), not `manage_options` directly. Anyone with `manage_options` receives every plugin capability via a `user_has_cap` filter.

| Constant | Capability string | Typical use |
| --- | --- | --- |
| `Capabilities::ACCESS` | `wfa_access` | See the plugin menu (implied by any granular cap) |
| `Capabilities::MANAGE_WORKFLOWS` | `wfa_manage_workflows` | Workflows, builder, workflow REST |
| `Capabilities::MANAGE_RUNS` | `wfa_manage_runs` | Runs list/detail, re-run |
| `Capabilities::MANAGE_CONNECTIONS` | `wfa_manage_connections` | Connections admin |
| `Capabilities::MANAGE_WEBHOOKS` | `wfa_manage_webhooks` | Webhooks admin |
| `Capabilities::MANAGE_SETTINGS` | `wfa_manage_settings` | Settings |

Site owners assign these with WordPress role tools. Companion plugins that add admin UI should check the matching capability (or introduce their own and document it).

---

## What is not an extension point

- **Inbound webhooks** (`POST /wfa/v1/webhooks/{public_id}`) are a product feature for external HTTP callers, not a PHP hook. See `docs/integrations.md` and `docs/rest-api.md`.
- **Connection auth types** (`ConnectionAuthTypes`) are a fixed built-in list, not a register hook.
- **Internal classes** under `Admin\`, `Persistence\`, `Database\`, and most of `Service\` are not guaranteed stable for third-party use beyond what this document names.

---

## Stability

Hook names and parameter lists above are part of the public API for this major line of development. Prefer listening on these actions over copying internal class methods. If a hook must change in a breaking way, it will be called out in the plugin changelog.
