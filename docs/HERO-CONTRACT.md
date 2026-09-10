# Hero contract — v0.3.0 candidate

## Scope

This document defines the first contract candidate for the reusable Hero module in Headless API Core.

The module exists to expose ordered, public hero/banner slides from WordPress to decoupled consumers. It must remain generic and must not hardcode HOSGEDOPOL branding, domains, copy, media IDs or frontend behavior.

## Consumer audit

The first consumer currently models each hero slide with these fields:

- `id`
- `image`
- optional `imageMobile`
- `alt`
- optional `href`
- `order`
- `active`
- optional `objectPosition`

The current consumer owns carousel behavior such as autoplay timing, pause behavior, swipe gestures, arrows and dots. Those UI concerns do not belong in the provider contract.

## WordPress data model

Hero will use a dedicated structured post type rather than native Posts.

Candidate post type: `headless_hero`

Each published Hero item represents one public slide.

WordPress ownership:

- post title: internal editorial label only;
- featured image: required desktop/primary image;
- post status: publication state (`publish` means public, other states are not exposed);
- `menu_order`: public display order;
- mobile image attachment ID: optional post meta;
- target URL: optional post meta;
- explicit accessible alt text: optional post meta, with featured-image alt fallback;
- object position: optional post meta.

The API must never expose draft, pending, private, trashed or password-protected Hero items.

## Public endpoint

### `GET /wp-json/headless-core/v1/hero`

Public, read-only endpoint.

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

`mobileImage` is `null` when no mobile-specific attachment exists. `href` is `null` when the slide is not clickable. `objectPosition` is `null` when the consumer should use its own default positioning.

The provider returns published items ordered by `menu_order ASC`, with a deterministic secondary order by post ID.

## Deliberate exclusions from v0.3.0

The first Hero contract does not expose:

- autoplay duration;
- transition animation;
- dots/arrows configuration;
- pause behavior;
- swipe thresholds;
- layout/CSS classes;
- institution-specific text or colors;
- arbitrary HTML overlays;
- frontend component names.

Those are consumer presentation responsibilities unless a later reusable content requirement proves otherwise.

## Validation gate

Before Hero v0.3.0 can be promoted:

1. Create at least two Hero items in WordPress.
2. Confirm only `publish` items are returned.
3. Confirm draft/trash items disappear from the collection.
4. Confirm primary image metadata and alt text.
5. Confirm optional mobile image.
6. Confirm optional link and object position.
7. Confirm `menu_order` ordering.
8. Run CI/package checks.
9. Validate the first consumer end-to-end without hardcoding consumer-specific values in the plugin.
