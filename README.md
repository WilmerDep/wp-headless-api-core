# Headless API Core

Reusable, modular WordPress plugin for exposing a stable, documented REST contract to decoupled frontends such as Next.js and React.

> Status: initial project bootstrap. Development happens on `develop`; `main` is reserved for stable milestones/releases.

## Goals

- Use WordPress as an editorial CMS, media library, permissions layer and structured content source.
- Expose a clean REST contract under `/wp-json/headless-core/v1/`.
- Keep frontend consumers independent from WordPress/PHP implementation details.
- Keep the core generic and reusable across institutional websites.
- Prefer WordPress Core APIs and small internal modules over unnecessary third-party dependencies.

## Initial roadmap

### v0.1.0 — Bootstrap + Health

- Installable/activatable plugin.
- Modular bootstrap.
- REST namespace `headless-core/v1`.
- Public read-only `GET /wp-json/headless-core/v1/health` endpoint.
- Initial architecture, installation, configuration and security documentation.

### v0.1.x — News pilot

After the Health endpoint is validated in WordPress, the first content module will normalize native WordPress posts through:

- `GET /wp-json/headless-core/v1/news`
- `GET /wp-json/headless-core/v1/news/{slug}`

The News contract will be documented before it is treated as stable.

## Architecture principles

- No institution-specific domains, IDs, colors, copy, users or secrets in the core.
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
- Feature branches may be introduced when they improve isolation (`feature/news-api`, `feature/hero`, etc.).

Before a release: validate PHP syntax, activation/deactivation, REST routes, permissions, errors, documentation, changelog and plugin version.

## License

To be defined before public distribution/release.
