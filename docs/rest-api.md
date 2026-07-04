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

Node-level endpoints (`workflow_nodes`) are not yet exposed over REST; they will be documented here once a later increment (the visual builder or execution engine) needs them.
