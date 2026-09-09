# Modules

## Stable baseline — v0.1.0

The stable runtime baseline contains the Core bootstrap and Health REST controller.

## News — first content pilot

Status: **feature development / candidate v0.2.0**.

News wraps the native WordPress `post` type in a provider-owned REST contract. No News CPT is introduced because native posts already model the editorial domain required by the first consumer.

Responsibilities:

- expose a paginated public collection;
- expose a single published post by slug;
- exclude unpublished and password-protected content;
- normalize ID, slug, title, excerpt and rendered content;
- normalize publication and modification dates;
- normalize featured image metadata and alt text;
- normalize categories;
- expose a small provider-owned SEO shape with optional Yoast-backed values;
- return deterministic 404 errors;
- keep WordPress/Yoast implementation details behind the REST contract.

### Internal split

The module is intentionally divided into small responsibilities:

- `News_Module`: module bootstrap;
- `News_Controller`: route registration, request validation, querying and HTTP responses;
- `News_Serializer`: WordPress object -> public API payload transformation.

The definitive public schema lives in `docs/REST-API.md` and must be validated on a real CMS before it is frozen.

## Later modules

Hero, Services, Directory, Galleries and Settings remain intentionally deferred until the News pipeline is validated end-to-end.

## Module admission rule

For every new requirement decide whether it belongs to:

A. the generic Core;
B. generic configuration;
C. an optional reusable module; or
D. consumer-specific code outside this repository.
