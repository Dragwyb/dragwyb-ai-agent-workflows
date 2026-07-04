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

---

No filters are exposed yet. Node/integration registration filters (`wfa/nodes/register`, `wfa/integrations/register`) and workflow execution lifecycle actions (`wfa/workflow/before_run`, `wfa/workflow/after_run`, `wfa/node/before_execute`, `wfa/node/after_execute`) are planned in `docs/internal/architecture.md` §2.6 and will be documented here as they ship.
