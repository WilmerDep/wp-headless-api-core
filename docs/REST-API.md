# REST API

## Namespace

`/wp-json/headless-core/v1/`

The `v1` namespace is the public compatibility boundary for the first contract generation.

## Health

### `GET /wp-json/headless-core/v1/health`

Public, read-only endpoint used to verify that the plugin is loaded and the v1 REST namespace is available.

Expected HTTP status: `200 OK`.

```json
{
  "ok": true,
  "service": "Headless API Core",
  "version": "<installed-plugin-version>"
}
```

No authentication is required. The route still declares an explicit permission callback.

---

## News — v0.2.2 public contract

News wraps the native WordPress `post` type. The Provider exposes published, non-password-protected posts only.

### `GET /wp-json/headless-core/v1/news`

Public, read-only collection endpoint.

Supported query parameters:

| Parameter | Default | Rules |
| --- | --- | --- |
| `page` | `1` | Integer >= 1 |
| `per_page` | `12` | Integer from 1 to 50 |
| `order` | `desc` | `asc` or `desc` |
| `orderby` | `date` | `date` or `modified` |

Collection shape:

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

### News public visibility boundary

Collection queries are constrained to:

```text
post_type = post
post_status = publish
has_password = false
```

Draft, pending, private, trash, future-before-publication and password-protected posts are not public News items.

### News Provider freshness/cache boundary

News collection and detail queries bypass persistent `WP_Query` result caching. News REST responses apply:

```text
Cache-Control: no-store, no-cache, must-revalidate, max-age=0
Pragma: no-cache
Expires: 0
```

Consumer applications may cache News according to their own documented strategy. Provider freshness and Consumer caching are separate concerns.

### News editorial date/time boundary

`publishedAt` and `modifiedAt` are ISO 8601 values using the WordPress site timezone. The Provider preserves the editorial wall-clock value and includes the site offset.

### News public author boundary

News exposes only:

```json
{
  "author": {
    "name": "Display Name"
  }
}
```

The contract intentionally does not expose email, login, username, roles, capabilities, credentials or additional user-account metadata.

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

Unknown, unpublished or password-protected slugs return HTTP `404` with code:

```text
headless_core_news_not_found
```

### News SEO boundary

The detail endpoint exposes a provider-owned SEO shape rather than leaking a third-party SEO plugin contract. When Yoast SEO is available, Headless API Core may use its supported Surfaces API. Native WordPress values remain the fallback.

Consumers must not call Yoast APIs directly as a contractual dependency of Headless API Core.

---

## News outbound revalidation — v0.2.2

This is not a public WordPress REST endpoint. It is an authenticated outbound request sent to a configured Consumer after relevant News lifecycle changes.

Example target:

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

The Consumer owns framework-specific invalidation. Headless API Core does not depend on Next.js `revalidateTag()` or `revalidatePath()` internals.

See `docs/REVALIDATION.md` for the detailed signing/lifecycle contract.

---

## Hero — v0.3.0 candidate

Hero uses the dedicated editorial-only WordPress post type:

```text
headless_hero
```

The raw CPT is not exposed through native WordPress REST. Consumers depend on the Headless API Core contract only.

### `GET /wp-json/headless-core/v1/hero`

Public, read-only collection endpoint.

Response shape:

```json
{
  "items": [
    {
      "id": 123,
      "image": {
        "url": "https://cms.example.org/wp-content/uploads/hero-desktop.jpg",
        "alt": "Accessible slide description",
        "width": 1920,
        "height": 760
      },
      "mobileImage": {
        "url": "https://cms.example.org/wp-content/uploads/hero-mobile.jpg",
        "alt": "Accessible slide description",
        "width": 760,
        "height": 960
      },
      "href": "/servicios",
      "order": 1,
      "objectPosition": "center center"
    }
  ]
}
```

Rules:

- `image` is required. Items without a valid featured image are excluded.
- `mobileImage` is `null` when no valid mobile image exists.
- `href` is `null` when no target is configured.
- `objectPosition` is `null` when no override is configured.
- the internal WordPress title is editorial-only and is not exposed.
- an explicit Hero alt override takes precedence over attachment alt text.
- without an explicit override, each image uses its own attachment alt text.

### Hero public visibility boundary

The Provider returns only:

```text
post_type = headless_hero
post_status = publish
has_password = false
valid featured image = required
```

Draft, pending, private, future, trash and password-protected Hero items are not public.

### Hero ordering

Ordering is deterministic:

```text
menu_order ASC
ID ASC
```

### Hero target URL boundary

Accepted targets:

- root-relative paths such as `/servicios`;
- absolute `https://` URLs;
- absolute `http://` URLs where intentionally configured.

Protocol-relative URLs and unsafe schemes such as `javascript:` or `data:` are rejected.

### Hero object-position boundary

The optional value accepts one or two safe tokens composed of standard position keywords or percentages from 0 through 100. Arbitrary CSS expressions are rejected.

Examples:

```text
center center
center top
50% 25%
```

### Hero Provider freshness/cache boundary

Hero queries bypass persistent result caching and `/hero` applies:

```text
Cache-Control: no-store, no-cache, must-revalidate, max-age=0
Pragma: no-cache
Expires: 0
```

The Provider therefore remains the fresh source of truth. Consumer caching/revalidation remains an integration responsibility.

### Hero Consumer boundary

The Provider does not expose or control:

- autoplay duration;
- transition animations;
- arrows or dots;
- pause behavior;
- swipe thresholds;
- frontend CSS/layout;
- skeleton/loading UX.

Those remain Consumer presentation concerns.

See `docs/HERO-CONTRACT.md` for the complete Hero candidate contract and validation gate.

---

## Planned, not implemented

- `GET /headless-core/v1/services`
- `GET /headless-core/v1/directory`
- `GET /headless-core/v1/galleries`
- `GET /headless-core/v1/settings`

These modules remain deferred until Hero v0.3.0 passes runtime validation.

## Breaking changes

Do not silently remove fields, rename fields, change field meaning or alter response shapes used by Consumers. Breaking changes must be documented and versioned, including coordinated updates in known Consumer repositories.
