# Integrations

This document records known consumers of Headless API Core. Integration records are documentation, not runtime hardcoding.

## Workstream ownership

`wp-headless-api-core` is the **Provider** and is developed/versioned independently from its consumers.

For the current HOSGEDOPOL project:

- this repository owns the WordPress plugin, REST contracts, provider serialization, provider security, provider lifecycle signaling, provider validation and plugin releases;
- `WilmerDep/hosgedopol-web` is a separate Consumer application and has its own development workstream;
- framework-specific cache invalidation remains Consumer-owned;
- cross-repository changes are allowed only when needed to prove or wire a Provider contract end-to-end and must be documented in both repositories.

## HOSGEDOPOL

**Relationship**

- PROVIDER: `WilmerDep/wp-headless-api-core`
- CONSUMER: `WilmerDep/hosgedopol-web`

**CMS**

- `https://cms.hosgedopol.gob.do`

**Frontend staging**

- `https://dev.hosgedopol.gob.do`

**Frontend production target**

- `https://hosgedopol.gob.do`

**REST namespace**

- `headless-core/v1`

## News integration history

- v0.2.0: initial News collection/detail integration.
- v0.2.1: WordPress site-timezone timestamp correction + `author.name`.
- v0.2.2: current candidate adding signed editorial lifecycle revalidation while keeping the v0.2.1 public GET contract backward-compatible.

The Consumer already uses:

- `/noticias` from `GET /headless-core/v1/news`;
- `/noticias/[slug]` from `GET /headless-core/v1/news/{slug}`;
- Provider-backed Home News;
- Provider-backed News results in search/autocomplete.

The Consumer currently uses cache/revalidation tags including:

```text
headless-news
headless-news:{slug}
```

Final Consumer implementation may also invalidate relevant paths such as Home, `/noticias`, detail paths and `/buscar`. Those Next.js mechanics are not part of plugin runtime.

## v0.2.2 HOSGEDOPOL revalidation target

Expected Consumer route:

```text
https://dev.hosgedopol.gob.do/api/headless/revalidate
```

This exact URL belongs in HOSGEDOPOL deployment configuration, **not** in generic plugin source.

Provider private configuration should resolve to:

```text
HEADLESS_REVALIDATION_URL=https://dev.hosgedopol.gob.do/api/headless/revalidate
HEADLESS_REVALIDATION_SECRET=<same private secret configured on Consumer>
```

The Consumer must configure the same private `HEADLESS_REVALIDATION_SECRET` in its server environment. It must not use `NEXT_PUBLIC_*` or expose the secret to browser code.

Signing contract:

```text
X-Headless-Timestamp: <unix-seconds>
X-Headless-Signature: sha256=<HMAC-SHA256(timestamp + "." + raw_body)>
```

Consumer requirements:

- verify signature against the exact raw body;
- reject stale timestamps outside the configured replay window (initial recommendation: 300 seconds);
- use timing-safe signature comparison;
- accept `resource="news"` only for this first phase;
- invalidate current slug, previous slug when changed, general News data and affected public surfaces;
- return non-2xx for invalid signature/payload;
- retain a short News TTL around 60 seconds as convergence fallback if webhook delivery fails.

Full contract: `docs/REVALIDATION.md`.

## Lifecycle QA opened from staging

HOSGEDOPOL staging exposed a real cache-lifecycle discrepancy: WordPress correctly stopped returning a draft/trashed News detail while a cached Consumer collection could retain its card temporarily.

Provider visibility was already correct (`post_status=publish`, non-password-protected only). v0.2.2 adds the signed invalidation signal needed for Consumer cache freshness without coupling WordPress to Next.js implementation details.

Required end-to-end transitions before release:

- draft → publish;
- future → publish after WordPress executes the real scheduled transition;
- publish → draft/private/trash/future;
- publish → publish edit;
- published slug change invalidating old and new keys;
- permanent deletion;
- failed webhook without blocking WordPress save/publication.

## Temporary Consumer fallback boundary

v0.2.2 does not modify or remove any Consumer-local fallback content. Any temporary `news-manual.ts` retirement remains a separate Consumer deployment decision after that Consumer confirms its own migration/cutover state.

The Provider lifecycle implementation must work whether a Consumer currently has zero, one or many local fallback slugs.

## Cross-repository rule

When a Provider contract used by a Consumer changes, record at minimum:

- Provider version/branch;
- Consumer repository/branch or deployment;
- endpoint/payload involved;
- automated validation evidence;
- runtime/staging validation evidence;
- remaining release gates;
- any temporary compatibility/fallback behavior.

Consumers must never depend on internal PHP classes, WordPress table structure or plugin directory layout.
