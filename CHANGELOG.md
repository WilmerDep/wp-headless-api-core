# Changelog

All notable changes to Headless API Core will be documented in this file.

The project follows Semantic Versioning.

## [Unreleased]

### Added

- Candidate News module for v0.2.0.
- Public read-only `GET /wp-json/headless-core/v1/news` collection endpoint.
- Public read-only `GET /wp-json/headless-core/v1/news/{slug}` detail endpoint.
- Normalized News payloads for native WordPress posts.
- Pagination, ordering, featured image metadata and native category normalization.
- Provider-owned SEO payload with optional Yoast Surfaces API integration and native WordPress fallback.
- Deterministic `headless_core_news_not_found` 404 behavior.
- Feature-branch CI validation and version-aware installable ZIP builds.

### Changed

- Health runtime validation is approved on the HOSGEDOPOL CMS for v0.1.0.
- Full article HTML is intentionally returned only by the News detail endpoint to avoid collection over-fetching.

### Pending validation

- Install v0.2.0 candidate on the target CMS.
- Validate News collection against real WordPress posts.
- Validate single News responses, featured images, categories and Yoast-backed SEO values.
- Validate invalid pagination and 404 behavior.
- Connect the validated News contract to the HOSGEDOPOL Next.js CMS adapter.

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
