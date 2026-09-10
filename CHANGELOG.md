# Changelog

All notable changes to Headless API Core will be documented in this file.

The project follows Semantic Versioning.

## [Unreleased]

### News v0.2.1 candidate

#### Added

- Public `author` object on News collection and detail payloads.
- `author.name` is sourced exclusively from the post author's WordPress `display_name`.
- Isolated regression coverage for News site-timezone timestamps and the public-author data boundary.
- Reusable live News smoke test covering health, collection, chronological ordering, detail, public author, timestamp offsets and deterministic 404 behavior.

#### Fixed

- `publishedAt` no longer forces GMT/UTC. It now preserves the date/time in the timezone configured for the WordPress site and returns ISO 8601 with the corresponding offset.
- `modifiedAt` now follows the same WordPress site-timezone semantics.
- Late-night editorial publications no longer move to the next calendar day merely because the Provider serialized them in UTC.

#### Security / privacy

- News author serialization exposes only `display_name` as `author.name`.
- Email, login/username, roles, capabilities, credentials and other internal WordPress user fields are not exposed.

#### Validation pending

- Install the v0.2.1 candidate ZIP on the HOSGEDOPOL CMS.
- Confirm the known `09/09/2026 23:31` editorial case returns `2026-09-09T23:31:00-04:00`.
- Confirm `modifiedAt` carries the configured WordPress site offset.
- Confirm `author.name` matches the editorial author in collection and detail.
- Revalidate collection chronological ordering, detail and 404 on the live CMS.
- Re-run HOSGEDOPOL Consumer QA against the corrected Provider before stable promotion.

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
- Plugin deactivation/reactivation preflight passed with `/health` and `/news` remaining available after reactivation.

### Superseded before stable promotion

- Final Consumer QA found that forcing GMT in `publishedAt` / `modifiedAt` could move late-night WordPress publications to the next public calendar day. Stable promotion therefore moved to the backward-compatible v0.2.1 News patch candidate instead of promoting v0.2.0 to `main`.

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
