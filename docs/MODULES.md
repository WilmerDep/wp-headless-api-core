# Modules

## Stable baseline — v0.1.0

The stable runtime baseline on `main` remains the Core bootstrap and Health REST controller.

## News — first content pilot

Status: **v0.2.1 patch candidate / collection runtime QA passed / final release gate open**.

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

The module is intentionally divided into small responsibilities:

- `News_Module`: module bootstrap;
- `News_Controller`: route registration, request validation, querying and HTTP responses;
- `News_Serializer`: WordPress object -> public API payload transformation.

The definitive public schema lives in `docs/REST-API.md`. Runtime validation evidence lives in `docs/VALIDATION.md`.

### Current validation state

- v0.2.0 original Provider runtime checks: passed, but final Consumer QA found a GMT serialization defect.
- v0.2.1 timezone + author isolated regression test: passed.
- v0.2.1 CI/package: passed.
- v0.2.1 live `/news` collection on HOSGEDOPOL: passed for site-local `publishedAt`, `modifiedAt`, `author.name` privacy boundary and observed chronological ordering.
- v0.2.1 live Health version, detail and 404 revalidation: pending.
- Consumer visual/staging QA against v0.2.1: pending in the Consumer project.

## Later modules

Hero, Services, Directory, Galleries and Settings remain intentionally deferred until the News milestone is fully closed and promoted through the release gate.

No later module should be started merely because its schema can be designed; the News pilot exists to validate the full editorial → REST → consumer lifecycle first.

## Module admission rule

For every new requirement decide whether it belongs to:

A. the generic Core;
B. generic configuration;
C. an optional reusable module; or
D. consumer-specific code outside this repository.
