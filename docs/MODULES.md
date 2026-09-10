# Modules

## Stable baseline

`main` remains the stable release line. Development of new modules continues from `develop` through isolated feature branches.

## News — first content pilot

Status: **v0.2.1 Provider validated / Consumer integrated / release promotion tracked separately**.

News wraps the native WordPress `post` type in a provider-owned REST contract. No News CPT is introduced because native posts already model the editorial domain required by the first consumer.

Responsibilities:

- expose a paginated public collection;
- expose a single published post by slug;
- exclude unpublished and password-protected content;
- normalize ID, slug, title, excerpt and rendered content;
- preserve publication and modification timestamps using the WordPress site timezone in ISO 8601;
- expose a minimal public `author.name` from WordPress `display_name` only;
- normalize featured image metadata and alt text;
- normalize categories;
- expose a small provider-owned SEO shape with optional Yoast-backed values and native WordPress fallback;
- return deterministic 404 errors;
- keep WordPress/Yoast implementation details behind the REST contract.

### Internal split

- `News_Module`: module bootstrap;
- `News_Controller`: route registration, request validation, querying and HTTP responses;
- `News_Serializer`: WordPress object -> public API payload transformation.

The definitive public schema lives in `docs/REST-API.md`. Runtime validation evidence lives in `docs/VALIDATION.md`.

### Validation state

- v0.2.1 timezone + author regression coverage: passed.
- v0.2.1 CI/package: passed.
- live `/health`, `/news`, News detail and deterministic 404 behavior: passed.
- first Consumer integration on HOSGEDOPOL staging: passed.
- local-only HOSGEDOPOL article migrated into WordPress and visible through the Provider: passed.
- Consumer cache invalidation/freshness for unpublished content is tracked in the Consumer repository and does not change the Provider rule: Headless API Core exposes only `publish` content.

## Hero — second content module

Status: **v0.3.0 contract design in progress on `feature/hero-api`**.

Hero is the next admitted module after the News lifecycle was validated end-to-end. The first Consumer currently needs an ordered image-based carousel with desktop image, optional mobile image, accessible alt text, optional link and optional object position.

The first Hero contract intentionally keeps carousel behavior in the Consumer. Autoplay timing, animation, arrows, dots, swipe behavior and CSS are not content-domain responsibilities.

Candidate WordPress model:

- dedicated structured post type `headless_hero`;
- featured image = required desktop/primary image;
- `post_status=publish` = publicly visible slide;
- `menu_order` = display order;
- optional mobile-image attachment ID;
- optional target URL;
- optional explicit accessible alt text with attachment-alt fallback;
- optional object-position value.

Candidate public endpoint:

- `GET /wp-json/headless-core/v1/hero`

The detailed candidate contract and validation gate live in `docs/HERO-CONTRACT.md`.

## Planned sequence after Hero

The current module sequence is:

1. Hero
2. Services
3. Directory
4. Galleries
5. Settings

Each module is admitted separately. Do not begin the next module until the current module has a documented contract, Provider QA and the required Consumer integration evidence.

## Module admission rule

For every new requirement decide whether it belongs to:

A. the generic Core;
B. generic configuration;
C. an optional reusable module; or
D. consumer-specific code outside this repository.
