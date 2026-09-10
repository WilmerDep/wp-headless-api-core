# Headless API Core

Reusable, modular WordPress plugin for exposing a stable, documented REST contract to decoupled frontends such as Next.js and React.

> Status: News v0.2.2 is the current lifecycle/revalidation patch candidate on `fix/news-revalidation-lifecycle`. The public News GET contract remains backward-compatible with v0.2.1 while v0.2.2 adds signed editorial revalidation delivery. `main` remains the stable release line until CMS + Consumer lifecycle QA passes.

## Goals

- Use WordPress as an editorial CMS, media library, permissions layer and structured content source.
- Expose a clean REST contract under `/wp-json/headless-core/v1/`.
- Keep frontend consumers independent from WordPress/PHP implementation details.
- Keep the core generic and reusable across institutional websites.
- Prefer WordPress Core APIs and small internal modules over unnecessary third-party dependencies.

## Roadmap

### v0.1.0 — Bootstrap + Health

Runtime-validated on the HOSGEDOPOL CMS.

- Installable/activatable plugin.
- Modular bootstrap.
- REST namespace `headless-core/v1`.
- Public read-only `GET /wp-json/headless-core/v1/health` endpoint.
- Initial architecture, installation, configuration and security documentation.

### v0.2.0 — News pilot

Implemented and merged into `develop`, but not promoted to `main` because final Consumer QA found a UTC/GMT serialization defect for late-night editorial timestamps.

### v0.2.1 — News timezone + author patch

Validated on the HOSGEDOPOL CMS and merged into `develop`.

- Same `GET /wp-json/headless-core/v1/news` route.
- Same `GET /wp-json/headless-core/v1/news/{slug}` route.
- Native WordPress `post` source.
- Published, non-password-protected content only.
- Pagination and deterministic date/modified ordering.
- `publishedAt` / `modifiedAt` preserve the WordPress site timezone in ISO 8601.
- Public author shape limited to `author.name` from WordPress `display_name`.
- Featured image and category normalization.
- Detail HTML content.
- Provider-owned SEO shape with optional Yoast-backed values and native WordPress fallback.
- Deterministic 404 contract.

### v0.2.2 — News editorial lifecycle + signed revalidation

Current candidate before stable News promotion.

- Keeps the v0.2.1 public GET response shapes unchanged.
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
- install the candidate ZIP on the HOSGEDOPOL CMS;
- configure the signed Consumer revalidation endpoint and shared secret;
- validate publish/unpublish/private/trash/future/edit/slug/delete lifecycle behavior end-to-end;
- confirm webhook failure does not block WordPress editing;
- confirm the Provider continues exposing only public News;
- complete final Consumer staging QA.

Hero, Services, Directory, Galleries and Settings remain paused until this News lifecycle gate is fully closed.

## Architecture principles

- No institution-specific domains, IDs, colors, copy, users or secrets in runtime code.
- No frontend application code in this repository.
- Public content endpoints are read-only.
- Administrative/private endpoints must use authentication, capability checks and explicit `permission_callback` functions.
- Breaking REST changes must be documented and versioned.
- Consumers depend only on the documented REST contract.
- Outbound revalidation communicates generic resource events; consumers own framework-specific cache invalidation.

## Known consumer

The first real consumer is HOSGEDOPOL. Consumer-specific URLs and integration details belong in `docs/INTEGRATIONS.md` and in the consumer repository; they must not be hardcoded into plugin runtime code.

Cross-repository validation is allowed when needed to prove a provider contract end-to-end, but implementation ownership remains separated between the provider and consumer repositories.

## Development workflow

- `main`: stable milestones/releases.
- `develop`: integration/development branch.
- `feature/*` / `fix/*`: isolated feature and patch work.

GitHub Actions validates PHP syntax, regression tests and produces a version-aware installable WordPress ZIP.

Before a release: validate PHP syntax, activation/deactivation, REST routes, permissions, errors, revalidation security/lifecycle, documentation, changelog, consumer contract compatibility and plugin version.

## License

To be defined before public distribution/release.
