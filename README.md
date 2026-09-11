# Headless API Core

Reusable, modular WordPress plugin for exposing a stable, documented REST contract to decoupled frontends such as Next.js and React.

> Status: News v0.2.2 is merged into `develop` and its Provider-side lifecycle/freshness gate is closed. Hero v0.3.0 is the active candidate on `feature/hero-v0.3.0`, with automated tests and package CI green. Runtime CMS + Consumer QA remains before Hero promotion.

## Goals

- Keep WordPress as the editorial source of truth.
- Expose a small, explicit and reusable REST contract.
- Avoid coupling Consumers to WordPress implementation details.
- Keep public content endpoints read-only.
- Preserve a modular structure so future content domains can be added without turning the plugin into one monolith.
- Document integration, preview, revalidation and security decisions before Consumers depend on them.

## Current modules

### v0.1.0 — Health

Runtime-validated on the HOSGEDOPOL CMS.

- `GET /wp-json/headless-core/v1/health`

### v0.2.x — News

News wraps native WordPress posts and exposes:

- `GET /wp-json/headless-core/v1/news`
- `GET /wp-json/headless-core/v1/news/{slug}`
- published/non-password-protected content only;
- site-local ISO 8601 publication/modification timestamps;
- minimal public `author.name`;
- normalized featured images/categories;
- detail HTML and provider-owned SEO;
- deterministic 404s;
- fresh Provider queries and explicit REST `no-store` semantics;
- signed HMAC editorial revalidation events for decoupled Consumers.

News v0.2.2 is merged into `develop`. Remaining skeleton/on-focus behavior belongs to the Consumer and does not block Provider development.

### v0.3.0 — Hero candidate

Current active candidate.

- Editorial-only CPT `headless_hero`.
- Featured image = required primary/desktop image.
- Optional mobile image from WordPress Media Library.
- Optional safe target URL.
- Optional accessible alt override.
- Optional sanitized object position.
- `menu_order` controls public order with deterministic ID tie-breaker.
- Public endpoint: `GET /wp-json/headless-core/v1/hero`.
- Only published, non-password-protected items with a valid primary image are returned.
- Provider query cache bypass + explicit `no-store` response policy.
- Raw CPT is not exposed through native WP REST.
- Consumer owns autoplay, arrows, dots, swipe, transitions, skeletons and layout.

The Hero contract lives in `docs/HERO-CONTRACT.md`.

### Current Hero release gate

Before merging/promoting v0.3.0:

- install the v0.3.0 candidate ZIP on the WordPress QA target;
- create at least two Hero items;
- verify public-only visibility and required primary image behavior;
- verify optional mobile image, href, alt and object-position fields;
- verify deterministic order and Provider freshness;
- validate the first Consumer end-to-end without introducing Consumer-specific runtime code into the plugin.

Services, Directory, Galleries and Settings remain deferred until Hero is validated.

## Architecture principles

- Runtime code is generic. Consumer-specific domains, branding, content IDs and secrets do not belong in plugin code.
- Public content endpoints are read-only.
- Administrative/private mutations require WordPress capabilities and nonce/auth controls where applicable.
- Breaking REST changes must be documented and versioned.
- Consumers depend only on the documented REST contract.
- Framework-specific cache invalidation remains a Consumer concern.
- Provider responses are the source-of-truth boundary and should not intentionally serve stale editorial state.

## Known consumer

HOSGEDOPOL is the first validation Consumer, but it is not hardcoded into runtime code.

Consumer repository:

```text
WilmerDep/hosgedopol-web
```

Cross-repository validation is allowed when needed to prove a provider contract, but product-specific frontend implementation remains owned by the Consumer repository.

## Development

GitHub Actions validates PHP syntax, regression tests and produces a version-aware installable WordPress ZIP.

Before a release: validate PHP syntax, activation/deactivation, REST routes, permissions, errors, Provider freshness, documentation, changelog, consumer contract compatibility and plugin version.

## License

License pending project decision.
