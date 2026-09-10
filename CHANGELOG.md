# Changelog

All notable changes to Headless API Core will be documented in this file.

The project follows Semantic Versioning.

## [Unreleased]

### News v0.2.2 candidate

#### Added

- Generic outbound revalidation client with configurable Consumer URL and shared secret.
- HMAC SHA-256 signing using `X-Headless-Timestamp` and `X-Headless-Signature`.
- News editorial lifecycle observer covering public status transitions, published edits, slug changes, category changes, featured-image changes, Yoast SEO meta changes and permanent deletion.
- Request-local event aggregation so overlapping WordPress hooks normally produce one revalidation delivery per post/save.
- Isolated regression coverage for HMAC signing, public-only visibility and News lifecycle event semantics.
- Reusable live smoke options for asserting that a known slug is public or absent after editorial transitions.

#### Changed

- Plugin candidate version bumped to `0.2.2`.
- Revalidation configuration is now documented through `HEADLESS_REVALIDATION_URL`, `HEADLESS_REVALIDATION_SECRET` and optional `HEADLESS_REVALIDATION_TIMEOUT`.
- News public GET contracts remain backward-compatible with v0.2.1; v0.2.2 adds an outbound lifecycle channel rather than changing `/news` response shapes.
- Hero and later modules remain paused until this News release gate closes.

#### Security

- Revalidation target requires HTTPS by default.
- Shared secret must contain at least 32 characters.
- The secret is never included in the payload, REST output or diagnostic logs.
- Consumer verification contract requires HMAC validation against the raw request body and a recommended 300-second timestamp window.
- Webhook failures are converted to safe delivery failures and must never roll back or prevent WordPress saves/publications.

#### Pending validation

- CI/package for the complete v0.2.2 candidate.
- Real CMS lifecycle transitions: draft/publish/private/trash/future/edit/slug/delete.
- Real signed delivery against the HOSGEDOPOL Consumer endpoint.
- Failure-path test with Consumer endpoint unavailable/invalid.
- Final HOSGEDOPOL staging lifecycle QA before News promotion to `main`.

### News v0.2.1 validation history

- Documentation aligned with the validated v0.2.0 News state on `develop`.
- HOSGEDOPOL consumer integration boundary and end-to-end validation evidence recorded.
- News v0.2.1 preserves WordPress site-local editorial timestamps in ISO 8601 instead of forcing GMT/UTC.
- News v0.2.1 adds a minimal public author contract: `author.name` from WordPress `display_name` only.
- Automated timezone + author regression test passed.
- CI/package passed and produced `wp-headless-api-core-v0.2.1`.
- Live `/news` collection on HOSGEDOPOL preserved the 09/09/2026 23:31 publication as `2026-09-09T23:31:20-04:00`.
- Live `modifiedAt` also carried the site offset.
- Live collection exposed only `author.name`; observed values included `HOSGEDOPOL` and `Jessica Tejada`.
- Observed descending collection ordering remained chronological after the timestamp fix.
- Health/detail/404 and real Consumer rendering were subsequently validated before the lifecycle cache finding opened v0.2.2.

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

- v0.2.0 was not promoted to `main` because final Consumer QA detected a UTC/GMT serialization defect for late-night editorial timestamps. The corrected line continued through v0.2.1 and then v0.2.2 lifecycle work.

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
