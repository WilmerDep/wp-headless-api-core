# Changelog

All notable changes to Headless API Core will be documented in this file.

The project follows Semantic Versioning.

## [Unreleased]

### Hero v0.3.3 candidate

#### Added

- Editorial-only `headless_hero` custom post type.
- Public read-only `GET /wp-json/headless-core/v1/hero` endpoint.
- Required primary/desktop image via WordPress featured image.
- Optional mobile image selected through the WordPress Media Library.
- Optional safe target URL supporting root-relative and absolute HTTP(S) targets.
- Optional accessible alt override with attachment-alt fallback.
- Optional constrained object-position field.
- Hero admin meta box with nonce/capability checks and sanitized persistence.
- Isolated Hero regression coverage for sanitization, serialization, public query rules, ordering and Provider freshness.

#### Changed

- Plugin candidate version bumped to `0.3.3`.
- Core bootstrap loads the Hero module after News.
- Hero ordering is deterministic: `menu_order ASC`, then `ID ASC`.
- Hero queries bypass persistent query-result caching and the REST endpoint emits explicit no-store/no-cache headers.
- Hero editor uses a guided, card-based UI for images, link behavior, image focus, order and accessibility.
- Desktop layout places **Comportamiento** and **Accesibilidad** side by side while preserving **Imágenes** at full width.
- The editor automatically collapses to a single column on narrower admin widths.
- Behavior fields keep a vertical flow inside their card so controls remain readable at half-width.
- Link sanitization now repairs the common `/https://...` / `/http://...` editor mismatch into a valid absolute URL instead of exposing a malformed root-relative target.
- The Hero editor automatically aligns the internal/external link mode when an editor pastes a root-relative or absolute HTTP(S) destination.

#### Deliberately excluded

- Carousel autoplay, transitions, arrows, dots, swipe behavior, layout/CSS and skeleton/loading UX remain Consumer responsibilities.
- Services, Directory, Galleries and Settings remain deferred.

#### Pending validation

- Install latest Hero candidate ZIP on the WordPress QA target.
- Complete final runtime checks for link normalization, trash/future visibility and Consumer integration.
- Validate the first Consumer end-to-end.

## [0.2.2] - 2026-09-10

### Added

- Generic outbound revalidation client with configurable Consumer URL and shared secret.
- HMAC SHA-256 signing using `X-Headless-Timestamp` and `X-Headless-Signature`.
- News editorial lifecycle observer covering status transitions, published edits, slug changes, categories, featured image, Yoast SEO meta and permanent deletion.
- Request-local event aggregation to avoid redundant lifecycle deliveries.
- Regression coverage for HMAC signing, public-only visibility, Provider freshness and lifecycle semantics.
- Reusable live smoke options for public/absent slug assertions.

### Changed

- News collection/detail queries bypass persistent `WP_Query` result caching.
- News detail lookup uses an explicit fresh published-only query.
- News REST responses emit `Cache-Control: no-store, no-cache, must-revalidate, max-age=0` plus compatibility no-cache headers.
- Public GET response shapes remain backward-compatible with v0.2.1.

### Security

- Revalidation target requires HTTPS by default.
- Shared secret must contain at least 32 characters.
- Secret/signature are not exposed through public REST or diagnostic logs.
- Consumer contract requires raw-body HMAC verification and a replay window.
- Webhook delivery failures never roll back or block WordPress editorial saves.

### Validated

- Automated CI/package passed.
- v0.2.2 installed on the HOSGEDOPOL CMS.
- Provider `/news` and `/news/{slug}` reflect editorial visibility changes on a normal refresh after freshness hardening.
- Provider-side News lifecycle/freshness gate closed and PR #6 squash-merged into `develop` at `20b134052beffcded254ac5b41384f15ec3dcda7`.
- Remaining skeleton/on-focus behavior belongs to the Consumer and does not block Provider development.

## [0.2.1] - 2026-09-10

### Changed

- `publishedAt` and `modifiedAt` preserve WordPress site-local editorial time and offset.
- News exposes only public `author.name` from WordPress `display_name`.

### Validated

- Late-night HOSGEDOPOL publication remained on the correct local calendar date.
- Collection/detail/404 and Consumer rendering were validated before the later cache-lifecycle hardening in v0.2.2.

## [0.2.0] - 2026-09-09

### Added

- News module wrapping native WordPress posts in a provider-owned REST contract.
- Public read-only collection/detail endpoints.
- Pagination and deterministic chronological ordering.
- Featured image/category normalization.
- Provider-owned SEO payload with Yoast-backed values and WordPress fallback.
- Deterministic 404 behavior.
- Version-aware installable ZIP builds in CI.

### Not promoted

- Final QA found a GMT serialization defect for late-night editorial timestamps; corrected in v0.2.1.

## [0.1.0] - 2026-09-08

### Added

- Initial plugin bootstrap.
- `headless-core/v1` REST namespace.
- Public read-only health endpoint.
- Initial architecture, installation, configuration, security, preview, revalidation and integrations documentation.

### Validated

- Installable ZIP uploaded and activated on the HOSGEDOPOL CMS.
- Health endpoint returned the documented v0.1.0 response.
