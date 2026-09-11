# Revalidation

## News v0.2.2 — realtime editorial lifecycle

Headless API Core can notify a decoupled consumer when the public lifecycle of native WordPress News changes. The Provider owns **what changed** and signs a generic webhook. The Consumer owns **how its cache is invalidated**.

```text
WordPress editorial change
  -> News lifecycle observer
  -> request-local deduplication
  -> signed JSON POST
  -> Consumer revalidation endpoint
  -> cache/tag/path invalidation
```

This first implementation supports `resource="news"` only. The transport client is generic so future modules can reuse the same signing/configuration boundary without hardcoding Next.js behavior into WordPress.

## Consumer endpoint

The target is configurable and is expected to expose a private server-side route such as:

```text
POST /api/headless/revalidate
```

The plugin does not require that route name and does not know about `revalidateTag`, `revalidatePath`, Next.js components or frontend source layout. It sends a signed resource lifecycle event to the configured URL.

## Payload

Every News delivery uses this shape:

```json
{
  "resource": "news",
  "postId": 123,
  "slug": "noticia-actual",
  "previousSlug": "noticia-anterior",
  "status": "draft",
  "previousStatus": "publish",
  "event": "status_changed"
}
```

Fields:

- `resource`: currently always `news`.
- `postId`: native WordPress post ID.
- `slug`: current WordPress `post_name` at delivery time.
- `previousSlug`: previous slug when WordPress supplied it; otherwise the current slug.
- `status`: current WordPress status. Permanent deletion uses the synthetic terminal value `deleted` because no post status remains after deletion.
- `previousStatus`: prior status when known; otherwise the current status.
- `event`: one of `status_changed`, `slug_changed`, `content_updated`, `deleted`.

The payload intentionally contains no content body, secret, user credentials or private author-account data. The Consumer refetches public data from the normal REST contract after invalidation.

## HMAC signing contract

Headers:

```text
Content-Type: application/json; charset=utf-8
X-Headless-Timestamp: <unix-seconds>
X-Headless-Signature: sha256=<lowercase-hex-hmac>
```

Signature input is **exactly**:

```text
<timestamp>.<raw JSON request body>
```

Algorithm:

```text
HMAC-SHA256(shared_secret, timestamp + "." + raw_body)
```

The Consumer must validate the signature against the **raw body bytes received**, not against parsed/re-serialized JSON.

### Replay window

The Consumer must reject requests whose timestamp is outside a reasonable replay window. The initial recommended window is **300 seconds (5 minutes)** in either direction to tolerate small clock differences while limiting replay exposure.

A valid timestamp alone does not provide permanent nonce storage; it bounds the replay opportunity to the accepted window. Consumers requiring strict single-use delivery can add durable event-ID replay storage later without changing the existing HMAC input contract.

### Signature comparison

The Consumer should:

1. require both revalidation headers;
2. parse `X-Headless-Timestamp` as Unix seconds;
3. reject timestamps outside the replay window;
4. compute HMAC-SHA256 using the shared secret and exact raw body;
5. compare signatures using a timing-safe comparison;
6. parse and validate the JSON payload only after authentication succeeds;
7. reject unsupported resources/events with a non-2xx response.

## Provider configuration

No endpoint or secret is committed to this repository.

The Provider resolves configuration in this order:

1. WordPress/PHP constant;
2. environment variable;
3. plugin filter.

Supported private values:

```text
HEADLESS_REVALIDATION_URL
HEADLESS_REVALIDATION_SECRET
HEADLESS_REVALIDATION_TIMEOUT
```

`HEADLESS_REVALIDATION_SECRET` must be at least 32 characters. A 32-byte or stronger random secret is recommended.

Example `wp-config.php` configuration using environment-backed values:

```php
define( 'HEADLESS_REVALIDATION_URL', 'https://dev.example.org/api/headless/revalidate' );
define( 'HEADLESS_REVALIDATION_SECRET', getenv( 'HEADLESS_REVALIDATION_SECRET' ) ?: '' );
```

The same `HEADLESS_REVALIDATION_SECRET` value must exist privately in the Consumer server environment. It must never use a `NEXT_PUBLIC_*` name and must never be returned to browser JavaScript.

Optional filters:

```text
headless_api_core_revalidation_url
headless_api_core_revalidation_secret
headless_api_core_revalidation_timeout
headless_api_core_revalidation_allow_insecure_http
headless_api_core_revalidation_log_errors
headless_api_core_revalidation_delivery
```

HTTPS is required by default. Insecure HTTP is rejected unless explicitly enabled by filter for controlled local development.

## WordPress lifecycle hooks

News v0.2.2 uses:

- `transition_post_status`: real public visibility transitions, including `future -> publish`, `publish -> future`, `publish -> draft`, `publish -> private` and `publish -> trash`;
- `post_updated`: previous slug/status context and in-place `publish -> publish` edits;
- `save_post_post`: ensures published saves are queued even when plugins update public metadata later in the same request;
- `set_object_terms`: category changes;
- `added_post_meta`, `updated_post_meta`, `deleted_post_meta`: featured-image (`_thumbnail_id`) and Yoast (`_yoast_wpseo_*`) changes;
- `before_delete_post`: permanent deletion;
- `shutdown`: one request-local delivery pass after WordPress/editor hooks have finished.

Separate trash/untrash webhooks are deliberately not registered. `transition_post_status` already describes transitions crossing the public `publish` boundary, and duplicate hook families would emit redundant requests. Restoring a trashed post to a non-public state does not need public invalidation; publishing it later produces the normal transition event.

## Request-local deduplication

One editorial operation can execute several WordPress hooks. The lifecycle observer aggregates events by post ID until `shutdown`.

Event priority is:

```text
deleted > status_changed > slug_changed > content_updated
```

The latest current slug/status wins, while a real previous slug/status is preserved. This means a publish operation that also changes a slug can still tell the Consumer which old detail key/path must be invalidated without sending several unnecessary webhooks for the same save.

## Scheduled publication

The plugin does not publish scheduled content early.

A `future` News post remains absent from `/news` and `/news/{slug}` until WordPress itself performs the real `future -> publish` transition. When that transition happens—normally through WP-Cron—`transition_post_status` emits the revalidation event.

Operationally, scheduled publishing therefore still depends on a functioning WordPress cron mechanism.

## Consumer invalidation expectations

For `resource="news"`, a Consumer should invalidate at minimum:

- general News data/tag (`headless-news`);
- current detail tag (`headless-news:{slug}`);
- previous detail tag when `previousSlug != slug`;
- Home News surface;
- `/noticias`;
- current `/noticias/{slug}`;
- previous `/noticias/{previousSlug}` after a slug change;
- `/buscar` and any server-side search/autocomplete cache that is derived from News.

A Next.js Consumer may combine tag and path invalidation. That implementation is Consumer-owned and can evolve independently of this Provider contract.

## TTL fallback

Realtime delivery is an acceleration/invalidation channel, not the only correctness layer. The Consumer should keep a short cache TTL as fallback; **about 60 seconds** is the initial recommendation for News.

If webhook delivery fails temporarily, the next TTL refresh must still converge to the Provider's public source of truth.

## Failure behavior and logging

Outbound delivery happens after WordPress has already processed the editorial save and is flushed at request shutdown. Network errors, invalid configuration and non-2xx Consumer responses return failure internally and are logged with the prefix:

```text
[Headless API Core]
```

Secrets and signatures are never written to logs.

Webhook failure must never throw an exception that reverses or prevents the WordPress save/publication. The normal public REST contract remains authoritative even if the revalidation channel is unavailable.

The action `headless_api_core_revalidation_delivery` receives delivery success/failure, the public payload and the WordPress HTTP response/error so installations can add their own monitoring without changing the contract.

## Public visibility invariant

Revalidation does not change News visibility rules. The Provider continues to expose only published, non-password-protected native WordPress posts:

```text
GET /wp-json/headless-core/v1/news
GET /wp-json/headless-core/v1/news/{slug}
```

Draft, pending, private, trash and future-before-publication content must remain absent/404 regardless of webhook state.
