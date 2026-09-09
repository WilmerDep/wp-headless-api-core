# Headless API Core

Reusable, modular WordPress plugin for exposing a stable, documented REST contract to decoupled frontends such as Next.js and React.

> Status: v0.1.0 Health baseline validated on the first real CMS. News is under development on `feature/news-api` as candidate v0.2.0. `main` remains the stable line and `develop` the integration line.

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

Current candidate feature.

- `GET /wp-json/headless-core/v1/news`
- `GET /wp-json/headless-core/v1/news/{slug}`
- Native WordPress `post` source.
- Published, non-password-protected content only.
- Pagination and deterministic ordering.
- Featured image and category normalization.
- Detail HTML content.
- Provider-owned SEO shape with optional Yoast-backed values.

The exact public schema is documented in `docs/REST-API.md` and will not be frozen until it passes runtime validation on the target CMS.

## Architecture principles

- No institution-specific domains, IDs, colors, copy, users or secrets in runtime code.
- No frontend application code in this repository.
- Public content endpoints are read-only.
- Administrative/private endpoints must use authentication, capability checks and explicit `permission_callback` functions.
- Breaking REST changes must be documented and versioned.
- Consumers depend only on the documented REST contract.

## Known consumer

The first real consumer is HOSGEDOPOL. Consumer-specific URLs and integration details belong in `docs/INTEGRATIONS.md` and in the consumer repository; they must not be hardcoded into plugin runtime code.

## Development workflow

- `main`: stable milestones/releases.
- `develop`: integration/development branch.
- `feature/*`: isolated feature work, including `feature/news-api`.

GitHub Actions validates PHP syntax and produces a version-aware installable WordPress ZIP for each pushed branch.

Before a release: validate PHP syntax, activation/deactivation, REST routes, permissions, errors, documentation, changelog and plugin version.

## License

To be defined before public distribution/release.
