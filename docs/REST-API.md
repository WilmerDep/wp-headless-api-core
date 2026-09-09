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

The `version` field reports the active plugin version. It was `0.1.0` for the validated Health baseline and is `0.2.0` on the current News candidate line.

No authentication is required for Health. The route still declares an explicit permission callback.

---

## News — v0.2.0 candidate contract

News wraps the native WordPress `post` type. The provider exposes published, non-password-protected posts only.

The contract below is implemented on the News feature line and is not considered frozen until runtime validation on the target CMS is completed.

### `GET /wp-json/headless-core/v1/news`

Public, read-only collection endpoint.

Supported query parameters:

| Parameter | Default | Rules |
| --- | --- | --- |
| `page` | `1` | Integer >= 1 |
| `per_page` | `12` | Integer from 1 to 50 |
| `order` | `desc` | `asc` or `desc` |
| `orderby` | `date` | `date` or `modified` |

`orderby=title` is intentionally not part of the v0.2.0 contract. Legacy WordPress titles can contain source entities, punctuation or decorative Unicode that sort according to the database source value rather than the normalized public title returned by this provider. News consumers currently require stable chronological ordering, so v0.2.0 limits ordering to publication and modification dates.

Collection response:

```json
{
  "items": [
    {
      "id": 123,
      "slug": "noticia-ejemplo",
      "title": "Noticia ejemplo",
      "excerpt": "Resumen público de la noticia.",
      "publishedAt": "2026-09-08T18:30:00+00:00",
      "modifiedAt": "2026-09-08T19:10:00+00:00",
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
      ]
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

Full article HTML is intentionally omitted from the collection response to avoid over-fetching.

### Source slug boundary

`slug` is the native published WordPress `post_name`. Headless API Core does not silently rewrite source slugs because doing so would create a second permalink identity that WordPress cannot resolve natively.

If legacy content contains percent-encoded or otherwise undesirable slugs, correct the permalink in WordPress editorial data before the frontend treats that URL as canonical.

### `GET /wp-json/headless-core/v1/news/{slug}`

Public, read-only detail endpoint.

A successful response contains the collection fields plus `content` and `seo`:

```json
{
  "id": 123,
  "slug": "noticia-ejemplo",
  "title": "Noticia ejemplo",
  "excerpt": "Resumen público de la noticia.",
  "content": "<p>Contenido HTML renderizado por WordPress.</p>",
  "publishedAt": "2026-09-08T18:30:00+00:00",
  "modifiedAt": "2026-09-08T19:10:00+00:00",
  "featuredImage": null,
  "categories": [],
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

The provider intentionally does **not** forward CMS canonical URLs, robots directives, or Schema data in this first News contract. Those values may contain CMS-domain assumptions and require the future configurable public-frontend URL strategy before they can be safely exposed.

Consumers must not call Yoast APIs directly as a contractual dependency of Headless API Core.

### 404 behavior

Unknown, unpublished, or password-protected slugs return HTTP `404` with WordPress REST error code:

`headless_core_news_not_found`

## Planned, not implemented in the current stable version

- `GET /headless-core/v1/hero`
- `GET /headless-core/v1/services`
- `GET /headless-core/v1/directory`
- `GET /headless-core/v1/galleries`
- `GET /headless-core/v1/settings`

These routes are not contractually available until their implementation and response schemas are documented.

## Breaking changes

Do not silently remove fields, rename fields, change field meaning or alter response shapes used by consumers. Breaking changes must be documented and versioned, including coordinated updates in known consumer repositories.
