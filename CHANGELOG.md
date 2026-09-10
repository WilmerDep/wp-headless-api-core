# Changelog

All notable changes to Headless API Core will be documented in this file.

The project follows Semantic Versioning.

## [Unreleased]

### Changed

- Documentation aligned with the validated v0.2.0 News state on `develop`.
- HOSGEDOPOL consumer integration boundary and end-to-end validation evidence recorded.
- News v0.2.1 patch candidate preserves WordPress site-local editorial timestamps in ISO 8601 instead of forcing GMT/UTC.
- News v0.2.1 adds a minimal public author contract: `author.name` from WordPress `display_name` only.

### Validated for v0.2.1 candidate

- Automated timezone + author regression test passes.
- CI/package is green and produces `wp-headless-api-core-v0.2.1`.
- Live `/news` collection on HOSGEDOPOL preserves the 09/09/2026 23:31 publication as `2026-09-09T23:31:20-04:00`.
- Live `modifiedAt` also carries the site offset.
- Live collection exposes only `author.name`; observed values include `HOSGEDOPOL` and `Jessica Tejada`.
- Observed descending collection ordering remains chronological after the timestamp fix.

### Pending release gate

- Confirm `/health` reports `0.2.1`.
- Revalidate News detail and 404 against the live v0.2.1 plugin.
- Revalidate the HOSGEDOPOL Consumer visual/staging flow against v0.2.1.
- Promote the corrected News milestone only after the release gate passes.

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

### Not promoted

- v0.2.0 was not promoted to `main` because final Consumer QA detected a UTC/GMT serialization defect for late-night editorial timestamps. The corrected candidate is v0.2.1.

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
