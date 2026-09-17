# Site Identity / Branding contract

Headless API Core exposes site identity from native WordPress data. The module does not require a specific theme and does not introduce a separate branding database.

## Endpoint

```http
GET /wp-json/headless-core/v1/site
```

Example with native images configured:

```json
{
  "schemaVersion": 1,
  "site": {
    "name": "Example Site",
    "description": "Institutional website",
    "url": "https://example.org/",
    "language": "es-DO"
  },
  "branding": {
    "logo": {
      "kind": "image",
      "source": "site_logo",
      "image": {
        "id": 123,
        "url": "https://cms.example.org/wp-content/uploads/logo.png",
        "alt": "Example Site",
        "width": 420,
        "height": 110,
        "mimeType": "image/png",
        "modifiedAt": "2026-09-17T00:00:00+00:00"
      },
      "fallback": {
        "kind": "text",
        "text": "Example Site"
      }
    },
    "favicon": {
      "kind": "image",
      "source": "site_icon",
      "image": {
        "id": 456,
        "url": "https://cms.example.org/wp-content/uploads/icon.png",
        "alt": "Example Site",
        "width": 512,
        "height": 512,
        "mimeType": "image/png",
        "modifiedAt": "2026-09-17T00:00:00+00:00"
      },
      "sizes": {
        "32": "https://cms.example.org/...",
        "180": "https://cms.example.org/...",
        "192": "https://cms.example.org/...",
        "512": "https://cms.example.org/..."
      },
      "fallback": {
        "kind": "initial",
        "initial": "E"
      }
    }
  }
}
```

## Native WordPress sources

Logo resolution order:

1. global `site_logo` option used by the native Site Logo block when available;
2. `custom_logo` theme modification used by the WordPress Custom Logo API;
3. site-title text fallback.

Favicon resolution:

1. native WordPress `site_icon` option / Media Library attachment;
2. first alphanumeric character of the site title as the semantic fallback.

The Consumer decides how a text-logo or initial fallback is visually rendered. Core does not hardcode institutional colors or a HOSGEDOPOL-specific asset.

## Consumer behavior

A Consumer should use:

- `branding.logo.kind === "image"` -> render `branding.logo.image`;
- `branding.logo.kind === "text"` -> render `branding.logo.fallback.text`;
- `branding.favicon.kind === "image"` -> use native icon URLs;
- `branding.favicon.kind === "initial"` -> generate/render an icon using `branding.favicon.fallback.initial`.

The endpoint remains generic across themes and sites because it reads WordPress core identity state instead of theme template markup.

## Revalidation

Changes to native WordPress identity data trigger the existing signed revalidation client. Watched values include:

- site name;
- site description;
- home/site URL;
- language;
- Site Icon;
- Site Logo;
- classic Custom Logo.

The outbound payload uses:

```json
{
  "resource": "site_identity",
  "event": "identity_updated",
  "changes": ["logo", "favicon"]
}
```

Consumers may use this event to invalidate cached header/metadata/site-identity data without requiring a deployment.

## Licensing boundary

Site Identity belongs to Headless API Core as a module. A future activation/license system should gate module availability at the plugin/module layer; it should not replace WordPress native logo/favicon storage or make Consumers depend on proprietary asset formats.
