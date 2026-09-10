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

## News — v0.2.1

News wraps the native WordPress `post` type. The provider exposes published, non-password-protected posts only.

v0.2.1 keeps the same endpoint URLs introduced in v0.2.0 while correcting editorial timestamp semantics and adding a minimal public author representation.

### `GET /wp-json/headless-core/v1/news`

Public, read-only collection endpoint.

Supported query parameters:

| Parameter | Default | Rules |
| --- | --- | --- |
| `page` | `1` | Integer >= 1 |
| `per_page` | `12` | Integer from 1 to 50 |
| `order` | `desc` | `asc` or `desc` |
| `orderby` | `date` | `date` or `modified` |

`orderby=title` is intentionally not part of the contract. Legacy WordPress titles can contain source entities, punctuation or decorative Unicode that sort according to the database source value rather than the normalized public title returned by this provider. News consumers require stable chronological ordering, so ordering is limited to publication and modification dates.

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

### Editorial date/time boundary

`publishedAt` and `modifiedAt` are serialized as ISO 8601 using the timezone configured for the WordPress site. The provider preserves the editorial wall-clock date/time and includes the site offset.

For example, a post entered in a UTC-04:00 WordPress site as `09/09/2026 23:31` is exposed as:

```text
2026-09-09T23:31:00-04:00
```

The provider must not force that value to UTC for this contract because doing so can move a late-night editorial publication to the next calendar day when a consumer formats it naively.

WordPress query ordering remains source-of-truth ordering by the native `post_date` / `post_modified` semantics selected through `orderby=date|modified`; the timezone fix changes only the serialized public representation, not the query sort field.

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

`slug` is the native published WordPress `post_name`. Headless API Core does not silently rewrite source slugs because doing so would create a second permalink identity that WordPress cannot resolve natively.

If legacy content contains percent-encoded or otherwise undesirable slugs, correct the permalink in WordPress editorial data before the frontend treats that URL as canonical.

### `GET /wp-json/headless-core/v1/news/{slug}`

Public, read-only detail endpoint.

A successful response contains the same summary fields, including site-local timestamps and `author.name`, plus `content` and `seo`:

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

### SEO boundary

The News detail endpoint exposes a small provider-owned SEO shape rather than leaking Yoast's raw contract to consumers.

When Yoast SEO is available, Headless API Core may use its supported Surfaces API to obtain SEO title/description/Open Graph text. Native WordPress title/excerpt/image values are the fallback.

Yoast-generated title values may append the WordPress CMS site name. The provider removes that trailing CMS site-name composition before returning `seo.title` and `seo.openGraph.title`; the public frontend owns final site-name composition.

The provider intentionally does **not** forward CMS canonical URLs, robots directives or Schema data in this first News contract. Those values may contain CMS-domain assumptions and require the future configurable public-frontend URL strategy before they can be safely exposed.

Consumers must not call Yoast APIs directly as a contractual dependency of Headless API Core.

### 404 behavior

Unknown, unpublished or password-protected slugs return HTTP `404` with WordPress REST error code:

`headless_core_news_not_found`

---

## Hero — v0.3.0 contract candidate

Hero is the next structured content module. It exposes an ordered public collection of image-based hero slides while leaving carousel presentation behavior to each Consumer.

### `GET /wp-json/headless-core/v1/hero`

Public, read-only collection endpoint.

Candidate response:

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

Contract rules:

- only `publish` Hero items are exposed;
- primary image is required for a public item;
- `mobileImage` is `null` when no dedicated mobile attachment exists;
- `href` is `null` when the slide is not clickable;
- `objectPosition` is `null` when the Consumer should use its default positioning;
- items are ordered by `menu_order ASC` with deterministic secondary ordering;
- image objects expose provider-normalized URL, alt, width and height;
- the endpoint does not expose drafts, pending, private, trash or password-protected items.

Carousel behavior such as autoplay duration, transitions, arrows, dots, swipe thresholds, pause logic and CSS remains Consumer-owned in v0.3.0.

The detailed design and validation gate are documented in `docs/HERO-CONTRACT.md`.

## Planned, not implemented

- `GET /headless-core/v1/services`
- `GET /headless-core/v1/directory`
- `GET /headless-core/v1/galleries`
- `GET /headless-core/v1/settings`

These routes are not contractually available until their implementation and response schemas are documented.

## Breaking changes

Do not silently remove fields, rename fields, change field meaning or alter response shapes used by consumers. Breaking changes must be documented and versioned, including coordinated updates in known consumer repositories.
