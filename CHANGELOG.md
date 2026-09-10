# Changelog

All notable changes to Headless API Core will be documented in this file.

The project follows Semantic Versioning.

## [Unreleased]

### Changed

- Documentation aligned with the validated v0.2.0 News state on `develop`.
- HOSGEDOPOL consumer integration boundary and end-to-end validation evidence recorded.

### Pending release gate

- Complete plugin deactivation/reactivation smoke test on the target CMS.
- Complete consumer visual/staging QA for News.
- Perform final CI/package artifact verification.
- Promote v0.2.0 from `develop` to `main` after the release gate passes.

## [0.2.0] - 2026-09-09

### Added

- News module wrapping native WordPress posts in a provider-owned REST contract.
- Public read-only `GET /wp-json/headless-core/v1/news` collection endpoint.
- Public read-only `GET /wp-json/headless-core/v1/news/{slug}` detail endpoint.
- Normalized News payloads for native WordPress posts.
- Pagination and deterministic date/modified ordering.
- Featured image metadata and native category normalization.
- Provider-owned SEO payload with optional Yoast Surfaces API integration and native WordPress fallback.
- Deterministic `headless_core_news_not_found` 404 behavior.
- Isolated regression coverage for the Yoast-unavailable SEO fallback.
- Version-aware installable ZIP builds in CI.

### Changed

- Full article HTML is intentionally returned only by the News detail endpoint to avoid collection over-fetching.
- News text fields normalize encoded entities and whitespace for consumer-safe output.
- SEO title cleanup avoids exposing CMS branding when Yoast returns the native post title plus an installation-specific suffix, without hardcoding institution-specific values.
- Supported collection ordering was narrowed to stable `date` and `modified` fields.

### Validated

- v0.2.0 candidate installed successfully on the HOSGEDOPOL CMS over v0.1.0.
- Collection validated against live native WordPress posts.
- Detail, rendered block HTML, featured images, categories and Yoast-backed SEO validated.
- Unknown slugs return the documented 404 contract.
- Pagination and ascending/descending chronological ordering validated.
- Yoast-unavailable fallback regression test passed in CI.
- News feature merged into `develop`.
- Technical Provider → Consumer integration passed through the HOSGEDOPOL Next.js CI smoke path.

### Not yet promoted

- `main` has not yet been promoted to v0.2.0. Stable promotion remains gated by deactivation/reactivation smoke plus final consumer visual/staging QA.

## [0.1.0] - 2026-09-08

### Added

- Initial plugin bootstrap.
- `headless-core/v1` REST namespace.
- Public read-only `GET /wp-json/headless-core/v1/health` endpoint.
- Initial architecture, REST, modules, installation, configuration, security, preview, revalidation and integrations documentation.

### Validated

- Installable ZIP uploaded to the HOSGEDOPOL CMS.
- Plugin activated successfully.
- Health endpoint returned the documented v0.1.0 response on the target CMS.
