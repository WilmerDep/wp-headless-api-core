# Modules

## Stable baseline — v0.1.0

The stable runtime baseline on `main` remains the Core bootstrap and Health REST controller.

## News — first content pilot

Status: **implemented / runtime-validated / merged into `develop` as v0.2.0**.

News wraps the native WordPress `post` type in a provider-owned REST contract. No News CPT is introduced because native posts already model the editorial domain required by the first consumer.

Responsibilities:

- expose a paginated public collection;
- expose a single published post by slug;
- exclude unpublished and password-protected content;
- normalize ID, slug, title, excerpt and rendered content;
- normalize publication and modification dates;
- normalize featured image metadata and alt text;
- normalize categories;
- expose a small provider-owned SEO shape with optional Yoast-backed values and native WordPress fallback;
- return deterministic 404 errors;
- keep WordPress/Yoast implementation details behind the REST contract.

### Internal split

The module is intentionally divided into small responsibilities:

- `News_Module`: module bootstrap;
- `News_Controller`: route registration, request validation, querying and HTTP responses;
- `News_Serializer`: WordPress object -> public API payload transformation.

The definitive public schema lives in `docs/REST-API.md`. Runtime validation evidence lives in `docs/VALIDATION.md`.

### Current validation state

- Provider runtime checks on the HOSGEDOPOL CMS: passed.
- Yoast-unavailable serializer fallback regression test: passed.
- Provider → HOSGEDOPOL Next.js technical integration smoke: passed.
- Consumer visual/staging QA: pending in the consumer project.
- Plugin deactivation/reactivation smoke before stable promotion: pending.

## Later modules

Hero, Services, Directory, Galleries and Settings remain intentionally deferred until the News milestone is fully closed and promoted through the release gate.

No later module should be started merely because its schema can be designed; the News pilot exists to validate the full editorial → REST → consumer lifecycle first.

## Module admission rule

For every new requirement decide whether it belongs to:

A. the generic Core;
B. generic configuration;
C. an optional reusable module; or
D. consumer-specific code outside this repository.
