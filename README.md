# Headless API Core

Reusable, modular WordPress plugin for exposing a stable, documented REST contract to decoupled frontends such as Next.js and React.

> Status: News v0.2.0 was runtime-validated and integrated with the first real Consumer, but final QA exposed a WordPress timezone serialization defect before stable promotion. The current release candidate is **v0.2.1**, scoped exclusively to News: site-timezone timestamps plus a minimal public author object. `main` remains the stable release line.

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

### v0.2.x — News pilot

Implemented on the development line and undergoing final release validation.

- `GET /wp-json/headless-core/v1/news`
- `GET /wp-json/headless-core/v1/news/{slug}`
- Native WordPress `post` source.
- Published, non-password-protected content only.
- Pagination and deterministic date/modified ordering.
- Featured image and category normalization.
- Detail HTML content.
- Provider-owned SEO shape with optional Yoast-backed values and native WordPress fallback.
- Deterministic 404 contract.
- Editorial `publishedAt` / `modifiedAt` serialized as ISO 8601 in the WordPress site timezone.
- Minimal public author shape: `author.name` from WordPress `display_name` only.
- Runtime validation on the HOSGEDOPOL CMS.
- Technical Provider → Consumer validation against the HOSGEDOPOL Next.js integration.

The public schema is documented in `docs/REST-API.md`. Runtime evidence lives in `docs/VALIDATION.md` and consumer relationship details in `docs/INTEGRATIONS.md`.

### Current release gate — v0.2.1

Before promoting News to `main`:

- CI/package for v0.2.1 must pass;
- install the v0.2.1 candidate ZIP on the target CMS;
- verify site-timezone `publishedAt` / `modifiedAt` on the real late-night QA case;
- verify `author.name` in collection and detail;
- revalidate chronological ordering, detail and 404;
- revalidate the HOSGEDOPOL Consumer visual/staging QA against v0.2.1.

Hero, Services, Directory, Galleries and Settings remain deferred until the News pipeline is fully closed.

## Architecture principles

- No institution-specific domains, IDs, colors, copy, users or secrets in runtime code.
- No frontend application code in this repository.
- Public content endpoints are read-only.
- Administrative/private endpoints must use authentication, capability checks and explicit `permission_callback` functions.
- Breaking REST changes must be documented and versioned.
- Consumers depend only on the documented REST contract.

## Known consumer

The first real consumer is HOSGEDOPOL. Consumer-specific URLs and integration details belong in `docs/INTEGRATIONS.md` and in the consumer repository; they must not be hardcoded into plugin runtime code.

Cross-repository validation is allowed when needed to prove a provider contract end-to-end, but implementation ownership remains separated between the provider and consumer repositories.

## Development workflow

- `main`: stable milestones/releases.
- `develop`: integration/development branch.
- `feature/*` / `fix/*`: isolated work.

GitHub Actions validates PHP syntax, regression tests and produces a version-aware installable WordPress ZIP.

Before a release: validate PHP syntax, activation/deactivation, REST routes, permissions, errors, documentation, changelog, consumer contract compatibility and plugin version.

## License

To be defined before public distribution/release.
