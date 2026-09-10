# REST API

## Namespace

`/wp-json/headless-core/v1/`

The `v1` namespace is the public compatibility boundary for the first contract generation.

## Health

### `GET /wp-json/headless-core/v1/health`

Public, read-only endpoint used to verify that the plugin is loaded and the v1 REST namespace is available.

Expected HTTP status: `200 OK`.

Response contract:

```json
{
  "ok": true,
  "service": "Headless API Core",
  "version": "<installed-plugin-version>"
}
```

No authentication is required for Health. The route still declares an explicit permission callback.

---

## News — v0.2.2 compatible public contract

News wraps the native WordPress `post` type. The Provider exposes published, non-password-protected posts only.

v0.2.2 keeps the same public GET routes and response shapes validated in v0.2.1. The v0.2.2 change is the addition of a **signed outbound editorial revalidation channel**; it does not add a public mutation endpoint or expose Consumer internals through REST.

### `GET /wp-json/headless-core/v1/news`

Public, read-only collection endpoint.

Supported query parameters:

| Parameter | Default | Rules |
| --- | --- | --- |
| `page` | `1` | Integer >= 1 |
| `per_page` | `12` | Integer from 1 to 50 |
| `order` | `desc` | `asc` or `desc` |
| `orderby` | `date` | `date` or `modified` |

`orderby=title` is intentionally not part of the contract. News consumers require stable chronological ordering, so ordering remains limited to publication and modification dates.

Collection response:

```json
{
  "items": [
    {
      "id": 123,
      "slug": "noticia-ejemplo",
      "title": "Noticia ejemplo",
      "excerpt": "Resumen público de la noticia.",
      "publishedAt": "2026-09-09T23:31:20-04:00",
      "modifiedAt": "2026-09-09T23:34:04-04:00",
      "featuredImage": {
        "url": "https://cms.example.org/wp-content/uploads/example.jpg",
        "alt": "Texto alternativo",
        "width": 1600,
        "height": 900
      },
      "categories": [
        {
          "id": 4,
          "slug": "noticias",
          "name": "Noticias"
        }
      ],
      "author": {
        "name": "Display Name"
      }
    }
  ],
  "pagination": {
    "page": 1,
    "perPage": 12,
    "totalItems": 24,
    "totalPages": 2
  }
}
```

`featuredImage` is `null` when no featured image exists. `categories` is always an array.

### Public visibility boundary

Collection queries are constrained to:

```text
post_type = post
post_status = publish
has_password = false
```

Draft, pending, private, trash, future-before-publication and password-protected posts are not public News items.

The realtime revalidation channel does not weaken or replace this rule; it only helps Consumers discard stale cache sooner after WordPress changes the source-of-truth state.

### Editorial date/time boundary

`publishedAt` and `modifiedAt` are serialized as ISO 8601 using the timezone configured for the WordPress site. The Provider preserves the editorial wall-clock date/time and includes the site offset.

For example, a post entered in a UTC-04:00 WordPress site as `09/09/2026 23:31` is exposed as:

```text
2026-09-09T23:31:00-04:00
```

WordPress query ordering remains source-of-truth ordering by native `post_date` / `post_modified` semantics selected through `orderby=date|modified`.

### Public author boundary

News exposes only:

```json
{
  "author": {
    "name": "Display Name"
  }
}
```

`author.name` comes from the WordPress author's `display_name`.

The contract intentionally does **not** expose email, login, username, roles, capabilities, credentials or additional user-account metadata.

Full article HTML is intentionally omitted from the collection response to avoid over-fetching.

### Source slug boundary

`slug` is the native published WordPress `post_name`. Headless API Core does not silently rewrite source slugs.

If legacy content contains undesirable slugs, correct the permalink in WordPress editorial data before the frontend treats that URL as canonical.

### `GET /wp-json/headless-core/v1/news/{slug}`

Public, read-only detail endpoint.

A successful response contains the same summary fields plus `content` and `seo`:

```json
{
  "id": 123,
  "slug": "noticia-ejemplo",
  "title": "Noticia ejemplo",
  "excerpt": "Resumen público de la noticia.",
  "content": "<p>Contenido HTML renderizado por WordPress.</p>",
  "publishedAt": "2026-09-09T23:31:20-04:00",
  "modifiedAt": "2026-09-09T23:34:04-04:00",
  "featuredImage": null,
  "categories": [],
  "author": {
    "name": "Display Name"
  },
  "seo": {
    "source": "wordpress",
    "title": "Noticia ejemplo",
    "description": "Resumen público de la noticia.",
    "openGraph": {
      "title": "Noticia ejemplo",
      "description": "Resumen público de la noticia.",
      "images": []
    }
  }
}
```

Unknown, unpublished or password-protected slugs return HTTP `404` with WordPress REST error code:

```text
headless_core_news_not_found
```

### SEO boundary

The News detail endpoint exposes a small provider-owned SEO shape rather than leaking Yoast's raw contract to consumers.

When Yoast SEO is available, Headless API Core may use its supported Surfaces API to obtain SEO title/description/Open Graph text. Native WordPress title/excerpt/image values are the fallback.

The provider intentionally does **not** forward CMS canonical URLs, robots directives or Schema data in this first News contract. Those values may contain CMS-domain assumptions and require a configurable public-frontend URL strategy before they can be safely exposed.

Consumers must not call Yoast APIs directly as a contractual dependency of Headless API Core.

---

## News outbound revalidation — v0.2.2

This is **not** a public REST endpoint exposed by WordPress. It is an authenticated outbound request sent by the plugin to a configured Consumer endpoint after relevant News lifecycle changes.

Expected Consumer endpoint example:

```text
POST https://consumer.example.org/api/headless/revalidate
```

Payload:

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

Authentication headers:

```text
X-Headless-Timestamp: <unix-seconds>
X-Headless-Signature: sha256=<hmac-sha256-hex>
```

The signature covers `<timestamp>.<raw JSON body>` with the private shared `HEADLESS_REVALIDATION_SECRET`.

The Consumer owns framework-specific cache invalidation. Headless API Core does not expose or depend on Next.js `revalidateTag()` / `revalidatePath()` internals.

See `docs/REVALIDATION.md` for lifecycle events, signing verification, replay-window requirements, failure semantics and configuration.

## Planned, not implemented

- `GET /headless-core/v1/hero`
- `GET /headless-core/v1/services`
- `GET /headless-core/v1/directory`
- `GET /headless-core/v1/galleries`
- `GET /headless-core/v1/settings`

These routes remain deferred until News v0.2.2 lifecycle/revalidation is closed and promoted.

## Breaking changes

Do not silently remove fields, rename fields, change field meaning or alter response shapes used by consumers. Breaking changes must be documented and versioned, including coordinated updates in known consumer repositories.
