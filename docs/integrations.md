# Integrations Reference

Every node type the plugin ships that talks to something outside the workflow engine itself, what (if anything) it stores credentials for, and exactly how those credentials are used. This file is updated every time a roadmap increment ships a new integration (see `docs/internal/roadmap.md`).

For how connections themselves are stored and encrypted, see `docs/internal/architecture.md` §2.3 and §2.5. This file is about how each individual integration *uses* that storage — or, for the two below that don't need it, why not.

## WordPress Hook — trigger (`wp_hook_trigger`)

**What it is:** Starts a workflow whenever a specified WordPress action hook fires (`do_action()`). This is not a third-party service — it is WordPress core (or another already-installed plugin announcing its own events) — so there is no external account, API, or credential involved at all.

**Credentials:** None. `bind()` just calls `add_action()` on the hook name the workflow author typed in.

**Config fields:** `hook_name` (required), `priority`, `accepted_args`.

## WooCommerce Order Completed — trigger (`woocommerce_order_completed_trigger`)

**What it is:** Starts a workflow when a WooCommerce order is marked `completed` (`woocommerce_order_status_completed`). Roadmap item 15's first demand-driven integration.

**Availability:** Registered only when WooCommerce is active (`class_exists( 'WooCommerce' )` and `function_exists( 'WC' )` at `init`). Sites without WooCommerce never see this node in the builder palette. If WooCommerce is later deactivated, existing workflows that use this trigger stop firing (the type is no longer in the registry); the saved graph is preserved and the builder shows the usual "type not currently registered" warning.

**Credentials:** None. WooCommerce is a co-installed WordPress plugin, not a remote API — there is nothing to store in `wfa_connections`.

**Config fields:** None (the event is fixed to "order completed").

**Trigger payload** (passed to actions as the run's trigger data):

| Key | Notes |
| --- | --- |
| `source` | Always `"woocommerce"` |
| `event` | Always `"order_completed"` |
| `order_id` | Always present |
| `status`, `currency`, `total`, `subtotal`, `customer_id` | When the order object is available |
| `billing_email`, `billing_first_name`, `billing_last_name`, `billing_phone` | When available |
| `payment_method`, `order_key` | When available |
| `items` | Array of `{product_id, variation_id, name, quantity, total}` |

## HTTP Request — action (`http_request_action`)

**What it is:** Sends a single outbound HTTP request to a URL the workflow author configures — this is the plugin's general-purpose "talk to any API" building block, not an integration with one specific named service.

**Credentials:** Optional. The `connection_id` config field (a "connection" picker in the builder) lets the author attach a stored `Connection` (see `docs/internal/architecture.md` §2.3) so the request is authenticated automatically instead of the author having to paste a secret into the plain-text Headers field. When set:

| Connection auth type | Header added |
| --- | --- |
| API Key | `Authorization: Bearer <api_key>` |
| Bearer Token | `Authorization: Bearer <token>` |
| Username & Password | `Authorization: Basic <base64(username:password)>` |

This mapping is a deliberate v1 simplification: `Authorization: Bearer <key>` is the single most common convention for a modern REST API key, but it is not universal — some APIs expect the key in a custom header or a query parameter instead. For those, the connection picker can be left unset and the header/query-string value added manually via the existing Headers/URL fields, or a future increment can add a way to pick where an API-key connection's value is injected instead of hardcoding `Authorization: Bearer`.

The decrypted credential value only ever exists inside `HttpRequestAction::execute()`'s local scope for the single outbound `wp_safe_remote_request()` call that needs it — it is never logged, and `wfa_workflow_run_logs` only ever stores the node's *result* (status code, response body), never its request headers.

If the configured connection no longer exists, or its stored value fails to decrypt, the action fails loudly (`success: false` with a clear error) rather than silently sending the request unauthenticated.

**Config fields:** `url` (required), `method`, `headers`, `body`, `connection_id` (optional).

## Send Email — action (`send_email_action`)

**What it is:** Sends an email via WordPress core's own `wp_mail()`.

**Credentials:** None — and deliberately so. `wp_mail()` already sends through whatever mail transport the site has configured: PHP's built-in `mail()` by default, or any SMTP/API-based transactional-email plugin (Mailgun, SendGrid, Postmark, an SMTP relay, etc.) already active on the site. This action does not talk to any provider's API directly and does not store, encrypt, or manage any mail-provider credentials of its own — it is a thin wrapper around a WordPress core function, and inherits whichever provider (if any) is already wired into `wp_mail()` at the site level.

If a future increment adds a *direct* API-based email provider integration (bypassing `wp_mail()` to call, say, a provider's HTTP API for delivery analytics), it will need its own `Connection` (its own row in `ConnectionAuthTypes`) and will be documented here as a new, separate integration rather than folded into this one.

**Config fields:** `to` (required, comma-separated), `subject` (required), `message` (required), `headers` (optional, e.g. `From`/`Reply-To`/`Content-Type`).

## Inbound Webhooks (not a node type)

**What it is:** A public `POST` endpoint (`/wp-json/wfa/v1/webhooks/{public_id}`) that starts a linked workflow when an external service calls it. Webhooks are managed under the admin **Webhooks** menu (not as a builder node type): each row in `wfa_webhooks` has an unguessable UUID `public_id`, an optional HMAC signing secret, an optional IP allow-list, and a `workflow_id`.

**Credentials / secrets:**

| Secret | Storage | Use |
| --- | --- | --- |
| Signing secret | Encrypted at rest via `Core\Encryption` (same key material as connections) in `wfa_webhooks.signing_secret` | Callers must send `X-WFA-Signature: sha256=<hex>` where `<hex>` is `hash_hmac('sha256', <raw body>, <secret>)`. Verified with `hash_equals()`. |
| IP allow-list | Plaintext JSON array of IPv4/IPv6 addresses and/or IPv4 CIDRs | Request `REMOTE_ADDR` must match an entry (or the list is empty = any IP). Deliberately does **not** trust `X-Forwarded-For`. |

Site Settings → Advanced can require a signing secret on *every* webhook (`require_webhook_signing`). When that is on, ingress rejects webhooks that have no secret, and the admin forms refuse to save without one.

On success the plugin queues the workflow (default) or runs it synchronously (when background execution is disabled), with trigger payload:

```json
{ "source": "webhook", "client_ip": "…", "body": { /* parsed JSON, or { "raw": "…" } */ } }
```

Only **active** workflows run; draft/paused/missing/unlinked webhooks return `409`. Unknown `public_id` returns `404`. Bad signature returns `401`. IP denied returns `403`.

See `docs/rest-api.md` for the exact response shape.

---

## Connections picker — `wfa/v1/connections`

Any integration that supports the optional `connection_id` config field (see HTTP Request above) relies on the builder fetching `GET /wfa/v1/connections` (see `docs/rest-api.md`) to render that field as a dropdown of the site's stored connections, identified only by `id`/`label`/`integration_slug`/`auth_type` — never by any credential value. See `src/Rest/ConnectionsController.php`.
