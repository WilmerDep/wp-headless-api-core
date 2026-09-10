# Headless API Core

Reusable, modular WordPress plugin for exposing a stable, documented REST contract to decoupled frontends such as Next.js and React.

> Status: News v0.2.2 is the current lifecycle/revalidation patch candidate on `fix/news-revalidation-lifecycle`. The public News GET contract remains backward-compatible with v0.2.1 while v0.2.2 adds Provider freshness hardening plus signed editorial revalidation delivery. `main` remains the stable release line until CMS + Consumer lifecycle QA passes.

## Goals

- Keep WordPress as the editorial source of truth.
- Expose a small, explicit and reusable REST contract.
- Avoid coupling Consumers to WordPress implementation details.
- Keep public content endpoints read-only.
- Preserve a modular structure so future content domains can be added without turning the plugin into one monolith.
- Document integration, preview, revalidation and security decisions before Consumers depend on them.

## Current modules

### v0.1.0 — Health

Runtime-validated on the HOSGEDOPOL CMS.

- `GET /wp-json/headless-core/v1/health`

### v0.2.0 — News pilot

Implemented and merged into `develop`, but not promoted to `main` because final Consumer QA found a UTC/GMT serialization defect for late-night editorial timestamps.

### v0.2.1 — News timezone + author patch

Validated on the HOSGEDOPOL CMS and merged into `develop`.

- Same `GET /wp-json/headless-core/v1/news` route.
- Same `GET /wp-json/headless-core/v1/news/{slug}` route.
- Site-local ISO 8601 editorial timestamps.
- Minimal public `author.name` contract.
- Pagination and stable `date|modified` ordering.
- Featured image metadata and categories.
- Detail HTML content.
- Provider-owned SEO shape with optional Yoast-backed values and native WordPress fallback.
- Deterministic 404 contract.

### v0.2.2 — News editorial lifecycle + signed revalidation

Current candidate before stable News promotion.

- Keeps the v0.2.1 public GET response shapes unchanged.
- Makes the Provider authoritative/fresh by bypassing persistent News query-result caching and emitting explicit REST `no-store` headers.
- Emits generic News lifecycle events after WordPress editorial changes.
- Signs outbound JSON with HMAC SHA-256.
- Uses configurable Consumer target URL and shared secret outside Git.
- Covers `draft/future/publish/private/trash`, published edits, slug changes and permanent deletion.
- Aggregates overlapping WordPress hooks into one event per post/request.
- Does not block or roll back WordPress saves when webhook delivery fails.
- Keeps `/news` and `/news/{slug}` public-only regardless of webhook state.
- Supports a short Consumer TTL (about 60 seconds) as failure fallback.

The public schema is documented in `docs/REST-API.md`. Revalidation signing/lifecycle details live in `docs/REVALIDATION.md`. Runtime evidence lives in `docs/VALIDATION.md` and consumer relationship details in `docs/INTEGRATIONS.md`.

### Current release gate

Before promoting News to `main`:

- validate all v0.2.2 regression tests and package CI;
- install the refreshed candidate ZIP on the HOSGEDOPOL CMS;
- verify Provider `/news` and `/news/{slug}` reflect editorial state on the first normal refresh;
- configure the signed Consumer revalidation endpoint and shared secret;
- validate publish/unpublish/private/trash/future/edit/slug/delete lifecycle behavior end-to-end;
- confirm webhook failure does not block WordPress editing;
- confirm the Provider continues exposing only public News;
- complete final Consumer staging QA.

Hero, Services, Directory, Galleries and Settings remain paused until this News lifecycle gate is fully closed.

## Architecture principles

- Runtime code is generic. Consumer-specific domains, branding, content IDs and secrets do not belong in plugin code.
- Public content endpoints are read-only.
- Administrative/private endpoints must use authentication, capability checks and explicit `permission_callback` functions.
- Breaking REST changes must be documented and versioned.
- Consumers depend only on the documented REST contract.
- Outbound revalidation communicates generic resource events; consumers own framework-specific cache invalidation.
- Provider News responses are source-of-truth responses; framework/application caching belongs at the Consumer boundary.

## Known consumer

HOSGEDOPOL is the first validation Consumer, but it is not hardcoded into runtime code.

Consumer repository:

```text
WilmerDep/hosgedopol-web
```

Cross-repository validation is allowed when needed to prove a provider contract, but product-specific frontend implementation remains owned by the Consumer repository.

## Development

GitHub Actions validates PHP syntax, regression tests and produces a version-aware installable WordPress ZIP.

Before a release: validate PHP syntax, activation/deactivation, REST routes, permissions, errors, Provider freshness, revalidation security/lifecycle, documentation, changelog, consumer contract compatibility and plugin version.

## License

License pending project decision.
