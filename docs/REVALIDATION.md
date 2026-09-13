# Revalidation

Headless API Core can notify a decoupled Consumer when a public Provider resource changes. The Provider owns **what changed** and signs a generic webhook. The Consumer owns **how its cache is invalidated**.

```text
WordPress editorial change
  -> resource lifecycle observer
  -> request-local deduplication
  -> signed JSON POST
  -> Consumer revalidation endpoint
  -> cache/tag/path invalidation
```

The signed transport is shared by News and Hero. Each module owns only its lifecycle observer and resource payload; neither module hardcodes Next.js behavior into WordPress.

## Consumer endpoint

The target is configurable and is expected to expose a private server-side route such as:

```text
POST /api/headless/revalidate
```

The plugin does not require that route name and does not know about `revalidateTag`, `revalidatePath`, Next.js components or frontend source layout. It sends a signed resource lifecycle event to the configured URL.

## Payloads

### News

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

News events may be `status_changed`, `slug_changed`, `content_updated` or `deleted`.

### Hero

Hero intentionally does not use slug fields because the public contract is one ordered collection.

```json
{
  "resource": "hero",
  "postId": 123,
  "status": "publish",
  "previousStatus": "draft",
  "event": "status_changed"
}
```

A published in-place update uses:

```json
{
  "resource": "hero",
  "postId": 123,
  "status": "publish",
  "previousStatus": "publish",
  "event": "content_updated"
}
```

Permanent deletion uses `event="deleted"` and the synthetic terminal `status="deleted"`.

The payload intentionally contains no content body, secret, user credentials or private account data. After invalidation, the Consumer refetches public data from the normal REST contract.

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

The Consumer must reject requests whose timestamp is outside a reasonable replay window. The recommended initial window is **300 seconds (5 minutes)** in either direction to tolerate small clock differences while limiting replay exposure.

A valid timestamp bounds the replay opportunity but does not provide durable nonce storage. Consumers requiring strict single-use delivery can add event-ID replay storage later without changing the current HMAC input contract.

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

Example `wp-config.php` configuration using an environment-backed secret:

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
```

Delivery monitoring action:

```text
headless_api_core_revalidation_delivery
```

HTTPS is required by default. Insecure HTTP is rejected unless explicitly enabled by filter for controlled local development.

## News lifecycle hooks

News uses:

- `transition_post_status`: real public visibility transitions, including `future -> publish`, `publish -> future`, `publish -> draft`, `publish -> private` and `publish -> trash`;
- `post_updated`: previous slug/status context and in-place `publish -> publish` edits;
- `save_post_post`: ensures published saves are queued even when plugins update public metadata later in the same request;
- `set_object_terms`: category changes;
- `added_post_meta`, `updated_post_meta`, `deleted_post_meta`: featured-image (`_thumbnail_id`) and Yoast (`_yoast_wpseo_*`) changes;
- `before_delete_post`: permanent deletion;
- `shutdown`: one request-local delivery pass after WordPress/editor hooks have finished.

Separate trash/untrash webhooks are deliberately not registered. `transition_post_status` already describes transitions crossing the public `publish` boundary.

## Hero lifecycle hooks

Hero v0.3.4 reuses the same architecture with an isolated `Hero_Revalidation` observer:

- `transition_post_status`: real transitions crossing the public `publish` boundary, including `draft/private/future -> publish` and `publish -> draft/private/trash/future`;
- `post_updated`: published in-place changes, including `menu_order` changes;
- `save_post_headless_hero` at priority 30: queues a published save after the guided Hero admin has persisted its fields;
- `added_post_meta`, `updated_post_meta`, `deleted_post_meta`: watches `_thumbnail_id`, `_headless_hero_mobile_image_id`, `_headless_hero_href`, `_headless_hero_alt` and `_headless_hero_object_position`;
- `before_delete_post`: permanent deletion;
- `shutdown`: one request-local delivery pass after WordPress/editor hooks have finished.

The observer deliberately does not create its own scheduler. A `future` Hero remains non-public until WordPress itself performs the real `future -> publish` transition, normally through WP-Cron.

## Request-local deduplication

One editorial operation can execute several WordPress hooks. Each lifecycle observer aggregates events by post ID until `shutdown`.

News event priority:

```text
deleted > status_changed > slug_changed > content_updated
```

Hero event priority:

```text
deleted > status_changed > content_updated
```

The latest current status wins while a real previous status is preserved. For News, old/new slug context is also preserved when relevant.

This means a Hero publish that also changes image metadata still sends one `status_changed` event rather than several redundant webhooks.

## Scheduled publication

The plugin does not publish scheduled content early.

A `future` News or Hero item remains absent from its public endpoint until WordPress itself performs the real `future -> publish` transition. When that happens, `transition_post_status` emits the revalidation event.

Operationally, scheduled publishing therefore still depends on a functioning WordPress cron mechanism.

## Consumer invalidation expectations

### News

For `resource="news"`, a Consumer should invalidate at minimum:

- general News data/tag (`headless-news`);
- current detail tag (`headless-news:{slug}`);
- previous detail tag when `previousSlug != slug`;
- Home News surface;
- `/noticias`;
- current `/noticias/{slug}`;
- previous `/noticias/{previousSlug}` after a slug change;
- `/buscar` and any server-side search/autocomplete cache derived from News.

### Hero

For `resource="hero"`, a Consumer should invalidate at minimum:

- the general Hero data/tag (`headless-hero`);
- the Home surface (`/`) that renders the Hero.

A Next.js Consumer may combine tag and path invalidation. That implementation is Consumer-owned and can evolve independently of this Provider contract.

## TTL fallback

Realtime delivery is an acceleration/invalidation channel, not the only correctness layer. The Consumer should keep a short cache TTL as fallback; **about 60 seconds** remains the initial recommendation.

If webhook delivery fails temporarily, the next TTL refresh must still converge to the Provider's public source of truth.

## Failure behavior and logging

Outbound delivery is flushed at request shutdown after WordPress has already processed the editorial operation. Network errors, invalid configuration and non-2xx Consumer responses return failure internally and are logged with the prefix:

```text
[Headless API Core]
```

Secrets and signatures are never written to logs.

Webhook failure must never throw an exception that reverses or prevents the WordPress save/publication. The normal public REST contracts remain authoritative even when the revalidation channel is unavailable.

The action `headless_api_core_revalidation_delivery` receives delivery success/failure, the public payload and the WordPress HTTP response/error so installations can add monitoring without changing the contract.

## Public visibility invariant

Revalidation does not change public visibility rules.

News continues to expose only published, non-password-protected native WordPress posts:

```text
GET /wp-json/headless-core/v1/news
GET /wp-json/headless-core/v1/news/{slug}
```

Hero continues to expose only published `headless_hero` items that satisfy the existing serializer requirements:

```text
GET /wp-json/headless-core/v1/hero
```

Draft, private, trash and future-before-publication Hero items remain absent regardless of webhook state. The Hero REST shape, serialization and ordering contract are unchanged by revalidation.
