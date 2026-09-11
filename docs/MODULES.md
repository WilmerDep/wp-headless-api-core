# Modules

## Core baseline

The plugin exposes a stable REST namespace through `headless-core/v1` and boots modules independently from the Core orchestrator.

## News — v0.2.2

Status: **Provider lifecycle/freshness gate closed and merged into `develop`**.

News wraps the native WordPress `post` type in a provider-owned REST contract. No News CPT is introduced because native posts already model the required editorial domain.

Responsibilities:

- expose a paginated public collection;
- expose a single published post by slug;
- exclude unpublished and password-protected content;
- normalize ID, slug, title, excerpt and rendered content;
- preserve publication and modification timestamps using the WordPress site timezone in ISO 8601;
- expose minimal public `author.name` only;
- normalize featured image metadata and categories;
- expose a provider-owned SEO shape;
- return deterministic 404 errors;
- keep Provider queries fresh and send explicit no-store headers;
- emit signed HMAC lifecycle revalidation events for Consumers.

Internal split:

- `News_Module` — module bootstrap;
- `News_Controller` — public REST routes/query boundary;
- `News_Serializer` — public payload normalization;
- `News_Revalidation` — editorial lifecycle observation/deduplication;
- `Revalidation_Client` — generic signed outbound transport.

Consumer skeletons, focus refresh and framework cache internals remain outside this repository.

## Hero — v0.3.0 candidate

Status: **implementation + automated tests ready; runtime CMS/Consumer QA pending**.

Hero uses a dedicated editorial-only CPT:

```text
headless_hero
```

Responsibilities:

- expose `GET /wp-json/headless-core/v1/hero`;
- return only published, non-password-protected Hero items;
- require a valid featured image as the primary/desktop image;
- support an optional mobile image attachment;
- support an optional relative or absolute HTTP(S) target;
- support an optional explicit accessible alt override;
- support a constrained object-position value;
- order by `menu_order ASC`, then `ID ASC`;
- bypass persistent query-result cache at the Provider boundary;
- send explicit no-store/no-cache REST headers;
- keep the raw CPT out of native WordPress REST;
- provide a small WordPress admin UI with media selection and sanitized meta persistence.

Internal split:

- `Hero_Module` — module bootstrap;
- `Hero_Post_Type` — CPT, structured meta and sanitizers;
- `Hero_Admin` — editorial meta box/media picker;
- `Hero_Controller` — public `/hero` route and Provider freshness policy;
- `Hero_Serializer` — normalized slide/image payload.

The Consumer continues to own carousel autoplay, arrows, dots, swipe, transitions, responsive layout and skeleton/loading UX.

The definitive Hero candidate contract lives in `docs/HERO-CONTRACT.md`.

## Later modules

Services, Directory, Galleries and Settings remain deferred until Hero v0.3.0 passes runtime validation.

## Module admission rule

For every new requirement decide whether it belongs to:

A. the generic Core;
B. generic configuration;
C. an optional reusable module; or
D. consumer-specific code outside this repository.
