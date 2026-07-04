# REST API Reference

All endpoints are namespaced under `wfa/v1` and require an authenticated request from a user with the `manage_options` capability (custom, more granular capabilities are planned — see `docs/internal/roadmap.md` item 14). Requests must be authenticated the same way as any other WordPress REST API request (logged-in cookie + `X-WP-Nonce` header, or an application password).

This file is updated every time a roadmap increment ships a new REST resource.

## Workflows — `wfa/v1/workflows`

### `GET /wfa/v1/workflows`

Lists workflows.

**Query parameters**

| Name | Type | Default | Description |
| --- | --- | --- | --- |
| `page` | integer | `1` | 1-indexed page number. |
| `per_page` | integer | `20` | Rows per page, `1`–`100`. |
| `status` | integer | — | Filter to one of `0` (draft), `1` (active), `2` (paused). |
| `include_trashed` | boolean | `false` | Include soft-deleted workflows. |

Response is a JSON array of workflow objects (see schema below). Pagination totals are returned via the `X-WP-Total` and `X-WP-TotalPages` response headers, matching core WordPress REST API conventions.

### `POST /wfa/v1/workflows`

Creates a workflow. Body: `title` (required, string), `graph` (optional, object), `settings` (optional, object or `null`). Returns `201` with the created workflow and a `Location` header.

### `GET /wfa/v1/workflows/{id}`

Retrieves a single workflow. Query parameter `include_trashed` (boolean, default `false`) allows fetching a trashed workflow by id.

### `PUT` / `PATCH /wfa/v1/workflows/{id}`

Updates a workflow. Body accepts any of `title`, `graph`, `settings`, `status` — only the fields present in the request are changed.

### `DELETE /wfa/v1/workflows/{id}`

Moves the workflow to the trash (soft delete). Pass `force=true` as a query parameter to permanently delete the workflow and its nodes instead. Response: `{ "deleted": true, "previous": { ...workflow } }`.

### `POST /wfa/v1/workflows/{id}/restore`

Restores a previously trashed workflow and returns it.

### `POST /wfa/v1/workflows/{id}/run`

Runs the workflow synchronously ("run now"/test action) and returns its outcome once every node has finished executing — the request blocks for the full duration of the run; there is no queued/background mode yet (see `docs/internal/roadmap.md` item 8). Works regardless of the workflow's status (draft/active/paused), since testing a workflow before activating it is a valid use case; a workflow's *automatic* trigger only fires for workflows with `status: 1` (active).

**Response**

```json
{
  "id": 42,
  "workflow_id": 7,
  "status": "success",
  "started_at": "2026-07-04T10:15:00+00:00",
  "finished_at": "2026-07-04T10:15:01+00:00",
  "logs": [
    {
      "node_id": 103,
      "status": "success",
      "message": null,
      "output": { "success": true, "status_code": 200, "body": "…" },
      "duration_ms": 842
    }
  ]
}
```

`status` is one of `queued`, `running`, `success`, `failed`, `partial` (a run is `partial` when some, but not all, of its nodes succeeded before execution stopped at the first failure — see `WorkflowExecutionService`). A dedicated, paginated `wfa/v1/runs` resource for browsing run history is deferred to a later roadmap item (Logging & execution history UI); this action endpoint exists so the engine is usable/testable before that UI ships.

### Workflow object schema

| Field | Type | Notes |
| --- | --- | --- |
| `id` | integer | Read-only. |
| `title` | string | Required on create. |
| `status` | integer | `0` draft, `1` active, `2` paused. |
| `definition_version` | integer | Read-only; schema version of `graph`. |
| `graph` | object | The builder graph (nodes/connections). |
| `settings` | object or `null` | Per-workflow settings. |
| `run_count` | integer | Read-only. |
| `is_trashed` | boolean | Read-only. |
| `created_at` | string (date-time) | Read-only. |
| `updated_at` | string (date-time) | Read-only. |

Node-level endpoints (`workflow_nodes`) are still not exposed over REST — the visual builder (item 6) reads/writes a workflow's entire node graph as the single `graph` field above, not as separate node resources, and the execution engine (item 7) reconciles `wfa_workflow_nodes` from that same `graph` field internally (see `WorkflowService::syncNodesFromGraph()`) rather than requiring the client to manage node rows directly. They may still be added later if a use case needs to address a single node without rewriting the whole graph.

## Node types — `wfa/v1/node-types`

### `GET /wfa/v1/node-types`

Read-only. Lists every trigger and action node type currently registered against the server-side `NodeTypeRegistry` (see `docs/hooks-reference.md` — `wfa/nodes/register`). This is what powers the visual builder's node palette; there is no corresponding write endpoint because node types are only ever registered in PHP.

**Response**

```json
{
  "triggers": [
    { "slug": "wp_hook_trigger", "label": "WordPress Hook", "description": "…", "config_schema": { "hook_name": { "type": "string", "label": "Hook name", "required": true } } }
  ],
  "actions": [
    { "slug": "http_request_action", "label": "HTTP Request", "description": "…", "config_schema": { "url": { "type": "string", "label": "Request URL", "required": true }, "method": { "type": "string", "label": "HTTP method", "default": "GET" }, "connection_id": { "type": "connection", "label": "Authenticate with connection (optional)", "default": 0 } } },
    { "slug": "send_email_action", "label": "Send Email", "description": "…", "config_schema": { "to": { "type": "string", "label": "To (comma-separated for multiple recipients)", "required": true }, "subject": { "type": "string", "label": "Subject", "required": true } } }
  ]
}
```

`config_schema` mirrors `Domain\Contracts\NodeTypeInterface::configSchema()` on the PHP side field-for-field; the builder renders its node configuration panel generically from this shape, so a third-party node type registered via `wfa/nodes/register` needs no front-end changes to show up there. The one field `type` that needs an extra data source to render meaningfully is `connection` — see below.

## Connections — `wfa/v1/connections`

### `GET /wfa/v1/connections`

Read-only. Lists every stored `Connection` (see `docs/internal/architecture.md` §2.3), stripped down to just enough to tell them apart in a picker — this is what powers the builder's "connection" config field type (e.g. `HttpRequestAction`'s `connection_id`, see `docs/integrations.md`). Never includes credential values, not even masked; the admin-only Connections screen (`admin.php?page=wfa-connections`) is the only place those are ever rendered (masked) or edited.

**Response**

```json
[
  { "id": 3, "label": "Acme CRM", "integration_slug": "acme_crm", "auth_type": "bearer_token", "auth_type_label": "Bearer Token" }
]
```

Returns at most 100 connections (no pagination parameters yet) — see `src/Rest/ConnectionsController.php` for the rationale; this is a picker data source, not the admin list table (which does paginate, at 20 per page, via its own non-REST `WP_List_Table` screen).

## Inbound webhooks — `wfa/v1/webhooks/{public_id}`

### `POST /wfa/v1/webhooks/{public_id}`

**Public.** No WordPress authentication, cookie, or nonce is required — callers are external services. Security is the unguessable `public_id` UUID, plus optional per-webhook HMAC signing and IP allow-list (see `docs/integrations.md`). This is the only route in the plugin whose `permission_callback` is `__return_true`.

**Path parameter:** `public_id` — 36-character UUID from the Webhooks admin screen.

**Headers (optional, depending on webhook config):**

| Header | Value |
| --- | --- |
| `X-WFA-Signature` | `sha256=<hex>` where `<hex>` is `hash_hmac('sha256', <raw request body>, <signing secret>)` |
| `Content-Type` | Prefer `application/json`; non-JSON bodies are stored as `{ "raw": "…" }` in the trigger payload |

**Success responses**

- `202 Accepted` when the run was queued for background execution (default):

```json
{ "run_id": 42, "status": "queued", "queued": true }
```

- `200 OK` when background execution is disabled and the run finished in-request:

```json
{ "run_id": 42, "status": "success", "queued": false }
```

**Error responses** (all `WP_Error` JSON): `404` unknown webhook, `403` IP denied or signing required but missing, `401` invalid signature, `409` workflow inactive/unlinked, `500` internal failure.
