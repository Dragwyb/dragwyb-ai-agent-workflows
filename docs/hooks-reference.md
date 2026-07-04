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

---

No filters are exposed yet. An `wfa/integrations/register` filter and workflow execution lifecycle actions (`wfa/workflow/before_run`, `wfa/workflow/after_run`, `wfa/node/before_execute`, `wfa/node/after_execute`) are planned in `docs/internal/architecture.md` §2.6 and will be documented here as they ship.
