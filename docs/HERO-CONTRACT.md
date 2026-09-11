# Hero contract — v0.3.0 candidate

## Scope

Hero is the next reusable content module after News. It exposes ordered public hero/banner slides from WordPress to decoupled consumers.

This module must remain generic. Runtime code must not hardcode HOSGEDOPOL branding, domains, copy, media IDs, frontend component names or carousel behavior.

## Consumer boundary

The first consumer currently needs these presentation inputs:

- stable item ID;
- primary/desktop image;
- optional mobile image;
- accessible alt text;
- optional target URL;
- public order;
- optional object position.

The consumer continues to own autoplay, pause behavior, swipe gestures, arrows, dots, transitions, responsive layout and animation.

`active` is intentionally not exposed as a separate public field. Publication state is represented by WordPress editorial status: only `publish` items are returned.

## WordPress data model

Post type:

```text
headless_hero
```

Each published item represents one public slide.

WordPress ownership:

- post title: internal editorial label only; not part of the public payload;
- featured image: required primary/desktop image;
- post status: `publish` means public; all other statuses are excluded;
- `menu_order`: public display order;
- `_headless_hero_mobile_image_id`: optional mobile attachment ID;
- `_headless_hero_href`: optional relative or absolute HTTP(S) target;
- `_headless_hero_alt`: optional explicit accessible alt text;
- `_headless_hero_object_position`: optional CSS-like object-position value restricted to safe keywords/percentages.

The post type is editorial/admin-only and does not expose an additional raw WordPress REST contract. Consumers depend on Headless API Core only.

## Public endpoint

### `GET /wp-json/headless-core/v1/hero`

Public, read-only endpoint.

Response:

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

- `mobileImage` is `null` when no valid mobile attachment exists;
- `href` is `null` when the slide is not clickable;
- `objectPosition` is `null` when the consumer should use its own default;
- the primary `image` is required for a slide to be returned;
- explicit Hero alt text takes precedence over attachment alt text;
- no private/internal title is exposed.

## Visibility and ordering

The Provider returns only:

```text
post_type = headless_hero
post_status = publish
post_password = empty
valid featured image = required
```

Draft, pending, private, future, trash and password-protected items are never public.

Ordering is deterministic:

```text
menu_order ASC
ID ASC
```

## Provider freshness

The Provider is the fresh source of truth. Hero queries must not rely on stale persistent query-result caches, and the Hero REST response must advertise a no-store/no-cache policy at the Provider boundary.

Consumer caching/revalidation is a separate integration responsibility. A future generic lifecycle hook may reuse the shared revalidation transport introduced by News, but Hero v0.3.0 must not be coupled to Next.js internals.

## Deliberate exclusions from v0.3.0

The first Hero contract does not expose:

- autoplay duration;
- transition animation;
- dots/arrows configuration;
- pause behavior;
- swipe thresholds;
- layout/CSS classes;
- arbitrary HTML overlays;
- institution-specific colors or copy;
- frontend component names.

## Validation gate

Before Hero v0.3.0 can be promoted:

1. Create at least two Hero items in WordPress.
2. Confirm only `publish` items are returned.
3. Confirm draft/private/future/trash items do not appear.
4. Confirm an item without a valid primary image is not exposed.
5. Confirm primary image URL/alt/width/height.
6. Confirm optional mobile image.
7. Confirm relative and absolute HTTP(S) links and rejection of unsafe schemes.
8. Confirm object-position sanitization.
9. Confirm deterministic `menu_order`, then ID ordering.
10. Confirm one normal Provider refresh reflects editorial state changes.
11. Run automated tests, CI and installable package checks.
12. Validate the first consumer end-to-end without hardcoding consumer-specific values in the plugin.
