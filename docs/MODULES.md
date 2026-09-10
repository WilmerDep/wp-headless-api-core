# Modules

## Stable baseline — v0.1.0

The stable runtime baseline on `main` remains the Core bootstrap and Health REST controller.

## News — first content pilot

Status: **implemented / v0.2.1 patch candidate under final release validation**.

News wraps the native WordPress `post` type in a provider-owned REST contract. No News CPT is introduced because native posts already model the editorial domain required by the first consumer.

Responsibilities:

- expose a paginated public collection;
- expose a single published post by slug;
- exclude unpublished and password-protected content;
- normalize ID, slug, title, excerpt and rendered content;
- serialize publication and modification timestamps in the WordPress site timezone as ISO 8601 with offset;
- normalize featured image metadata and alt text;
- normalize categories;
- expose a minimal public author object containing only `display_name` as `author.name`;
- expose a small provider-owned SEO shape with optional Yoast-backed values and native WordPress fallback;
- return deterministic 404 errors;
- keep WordPress/Yoast/user-account implementation details behind the REST contract.

### Internal split

The module is intentionally divided into small responsibilities:

- `News_Module`: module bootstrap;
- `News_Controller`: route registration, request validation, querying and HTTP responses;
- `News_Serializer`: WordPress object -> public API payload transformation.

The definitive public schema lives in `docs/REST-API.md`. Runtime validation evidence lives in `docs/VALIDATION.md`.

### v0.2.1 patch boundary

Final HOSGEDOPOL QA identified that v0.2.0 serialized `publishedAt` and `modifiedAt` with GMT forced. v0.2.1 corrects only News and keeps the existing routes and query contract intact.

The patch also adds `author.name` from WordPress `display_name`. It does not expose email, login/username, roles, capabilities, credentials or other account fields.

### Current validation state

- v0.2.0 Provider runtime checks on the HOSGEDOPOL CMS: passed, including deactivation/reactivation smoke.
- Yoast-unavailable serializer fallback regression test: passed.
- Provider → HOSGEDOPOL Next.js technical integration smoke against v0.2.0: passed.
- v0.2.1 isolated timezone + author regression: required in CI before packaging.
- v0.2.1 live CMS validation: pending candidate ZIP installation.
- Consumer visual/staging QA must be repeated against v0.2.1 after the Provider is updated.

## Later modules

Hero, Services, Directory, Galleries and Settings remain intentionally deferred until the News milestone is fully closed and promoted through the release gate.

No later module should be started merely because its schema can be designed; the News pilot exists to validate the full editorial → REST → consumer lifecycle first.

## Module admission rule

For every new requirement decide whether it belongs to:

A. the generic Core;
B. generic configuration;
C. an optional reusable module; or
D. consumer-specific code outside this repository.
