# Hooks Reference

Public actions and filters exposed by Workflow Automate, in the order they were introduced. This file is updated every time a roadmap increment ships a new hook (see `docs/internal/roadmap.md`).

## Actions

### `wfa/loaded`

Fires once, on `plugins_loaded`, after the plugin has confirmed its PHP/WordPress version requirements are met.

**Parameters**

| Name | Type | Description |
| --- | --- | --- |
| `$container` | `WorkflowAutomate\Plugin\Core\Container` | The plugin's service container. |

**Example**

```php
add_action(
	'wfa/loaded',
	function ( $container ) {
		// Safe to assume the plugin's minimum PHP/WP requirements are met here.
	}
);
```

### `wfa/nodes/register`

Fires once, on `init`, to let core and third-party code register trigger and action node types into the plugin's `NodeTypeRegistry`. Fired on `init` (rather than during this plugin's own `plugins_loaded` handler) specifically so that any plugin hooking this action from inside its own `plugins_loaded` callback is guaranteed to have already registered by the time this fires, regardless of plugin load order.

**Parameters**

| Name | Type | Description |
| --- | --- | --- |
| `$registry` | `WorkflowAutomate\Plugin\Service\NodeTypeRegistry` | The registry to register trigger/action instances into. |

**Example**

```php
add_action(
	'wfa/nodes/register',
	function ( $registry ) {
		$registry->registerAction( new My_Custom_Action() );
	}
);
```

A custom action implements `WorkflowAutomate\Plugin\Domain\Contracts\ActionInterface`; a custom trigger implements `WorkflowAutomate\Plugin\Domain\Contracts\TriggerInterface`. Both are documented in their own source files.

### `wfa/workflow/before_run`

Fires immediately before a workflow run starts (both a manual "run now" and an automatic, trigger-fired run — see `WorkflowAutomate\Plugin\Integration\WorkflowTriggerBinder`), before the `wfa_workflow_runs` row is even created.

**Parameters**

| Name | Type | Description |
| --- | --- | --- |
| `$workflow_id` | `int` | The workflow about to run. |
| `$trigger_payload` | `array` | Data the triggering event provided; empty for a manual run. |

### `wfa/workflow/after_run`

Fires immediately after a workflow run finishes, whatever its final status (`success`, `failed`, or `partial`).

**Parameters**

| Name | Type | Description |
| --- | --- | --- |
| `$run` | `WorkflowAutomate\Plugin\Domain\WorkflowRun` | The completed run. |
| `$trigger_payload` | `array` | Data the triggering event provided; empty for a manual run. |

### `wfa/node/before_execute`

Fires immediately before a single node (an action; trigger nodes do not go through this) executes.

**Parameters**

| Name | Type | Description |
| --- | --- | --- |
| `$node` | `WorkflowAutomate\Plugin\Domain\WorkflowNode` | The node about to execute. |
| `$context` | `array` | Runtime data available to this node (trigger payload, prior node outputs). |

### `wfa/node/after_execute`

Fires immediately after a single node executes, whether it succeeded or failed.

**Parameters**

| Name | Type | Description |
| --- | --- | --- |
| `$node` | `WorkflowAutomate\Plugin\Domain\WorkflowNode` | The node that just executed. |
| `$result` | `array` | Its outcome — at minimum `{success: bool, error?: string}`. |
| `$context` | `array` | Runtime data that was available to this node. |

**Example**

```php
add_action(
	'wfa/node/after_execute',
	function ( $node, $result, $context ) {
		if ( empty( $result['success'] ) ) {
			error_log( sprintf( 'Workflow node %s failed: %s', $node->nodeType(), $result['error'] ?? 'unknown' ) );
		}
	},
	10,
	3
);
```

---

No filters are exposed yet. An `wfa/integrations/register` filter is planned in `docs/internal/architecture.md` §2.6 and will be documented here as it ships.
