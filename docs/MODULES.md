# Modules

## Stable baseline — v0.1.0

The stable runtime baseline on `main` remains the Core bootstrap and Health REST controller until the complete News milestone is promoted.

## News — first content pilot

Status: **v0.2.2 lifecycle/revalidation candidate / Provider GET contract validated / final lifecycle gate open**.

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
- emit signed, generic editorial lifecycle revalidation events in v0.2.2;
- keep Consumer/framework implementation details behind the integration contract.

### Internal split

- `News_Module`: module bootstrap;
- `News_Controller`: route registration, request validation, querying and HTTP responses;
- `News_Serializer`: WordPress object -> public API payload transformation;
- `News_Revalidation`: WordPress lifecycle observation, request-local deduplication and News event construction;
- `Revalidation_Client`: generic HMAC-signed outbound transport reusable by future modules.

The definitive public schema lives in `docs/REST-API.md`. Revalidation semantics live in `docs/REVALIDATION.md`. Runtime validation evidence lives in `docs/VALIDATION.md`.

### Current validation state

- v0.2.0 Provider contract: functionally validated, superseded because GMT serialization moved late-night dates.
- v0.2.1 timezone + public author: validated on HOSGEDOPOL and merged into `develop`.
- v0.2.2: implements realtime editorial lifecycle signaling with HMAC signing while preserving the v0.2.1 GET contract.
- v0.2.2 automated regression/CI: pending final candidate run.
- v0.2.2 HOSGEDOPOL CMS + Consumer lifecycle QA: pending.

## Later modules — paused

Hero, Services, Directory, Galleries and Settings remain intentionally deferred until the News v0.2.2 lifecycle/revalidation gate is fully closed and the News milestone is promoted.

A preliminary Hero branch may exist for contract exploration, but no Hero implementation is eligible to continue or merge during this News gate.

## Module admission rule

For every new requirement decide whether it belongs to:

A. the generic Core;
B. generic configuration;
C. an optional reusable module; or
D. consumer-specific code outside this repository.
