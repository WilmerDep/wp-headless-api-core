# Validation

This document records release-gate and runtime evidence for Headless API Core.

## HOSGEDOPOL CMS baseline

Target CMS: `https://cms.hosgedopol.gob.do`

### v0.1.0 — Bootstrap + Health

- [x] Installable ZIP recognized by WordPress.
- [x] Activation succeeded without blocking wp-admin.
- [x] `GET /wp-json/headless-core/v1/health` returned the documented contract.
- [x] A later deactivation/reactivation smoke confirmed the REST namespace survives the activation cycle.

### v0.2.0 — initial News pilot

- [x] Native WordPress posts exposed through `/news`.
- [x] Pagination and chronological `date|modified` ordering validated.
- [x] Detail HTML, featured images, categories and provider-owned SEO validated.
- [x] Unknown/unpublished News detail returns `headless_core_news_not_found` with HTTP 404.
- [x] Yoast-unavailable SEO fallback covered by isolated regression test.
- [x] Technical Provider → HOSGEDOPOL Consumer integration passed build/runtime smoke.
- [ ] Promotion to `main` intentionally stopped after final QA found GMT serialization could move late-night editorial dates to the following day.

### v0.2.1 — site timezone + public author

- [x] `publishedAt` preserves WordPress site-local editorial time and offset.
- [x] `modifiedAt` uses the same timezone semantics.
- [x] Concrete late-night case remained `2026-09-09T23:31:20-04:00` instead of becoming September 10 UTC.
- [x] Collection/detail expose only `author.name` from WordPress `display_name`.
- [x] No email, login, username, roles, capabilities or credentials exposed.
- [x] Health reported `0.2.1` on the real CMS.
- [x] Collection/detail/404 and real Consumer rendering were revalidated.
- [x] v0.2.1 merged into `develop`.

Final Consumer QA then exposed a separate cache-lifecycle problem. Follow-up runtime testing also showed that the public Provider `/news` response itself could remain stale for several refreshes after an editorial transition, so v0.2.2 now hardens both Provider freshness and Consumer revalidation rather than treating the issue as Consumer-only.

---

## News v0.2.2 — editorial lifecycle + signed revalidation

Branch:

```text
fix/news-revalidation-lifecycle
```

Issue:

```text
#5 — News v0.2.2: lifecycle editorial y revalidación HMAC
```

### Scope

v0.2.2 keeps the v0.2.1 public GET contract compatible, hardens Provider freshness, and adds an authenticated outbound lifecycle channel so decoupled Consumers can invalidate cache as WordPress editorial state changes.

Hero, Services, Directory, Galleries and Settings remain paused until this gate closes.

### Provider public-only invariant

Automated regression coverage confirms:

- [x] collection queries `post_status=publish`;
- [x] collection excludes password-protected posts;
- [x] draft detail returns News 404;
- [x] pending detail returns News 404;
- [x] private detail returns News 404;
- [x] trash detail returns News 404;
- [x] future detail returns News 404 before WordPress actually publishes it;
- [x] password-protected publish detail returns News 404;
- [x] normal published public detail remains available.

Revalidation does not make private editorial states public. It only accelerates Consumer cache convergence.

### Provider freshness hardening

Runtime finding on HOSGEDOPOL before the hardening patch:

- [x] a `publish -> draft` transition eventually disappeared from the Provider and Consumer;
- [x] the Provider itself could require multiple refreshes before `/news` reflected the new state;
- [x] this proved the stale behavior was not exclusively a Next.js/browser cache concern.

Candidate hardening now enforces:

- [x] collection `WP_Query` uses `cache_results=false`;
- [x] detail uses an explicit published-only `WP_Query` with `cache_results=false` instead of a path lookup that may participate in persistent object caching;
- [x] News REST success responses receive `Cache-Control: no-store, no-cache, must-revalidate, max-age=0`;
- [x] News REST errors/404s receive the same policy through `rest_post_dispatch`;
- [x] compatibility headers `Pragma: no-cache` and `Expires: 0` are included;
- [x] unrelated REST routes are not modified by the News cache policy.

A one-time host/CDN cache purge may be required when deploying this hardening if an upstream layer already cached the old Provider response. After rollout, one normal refresh of `/news` and `/news/{slug}` must reflect the current WordPress state without waiting for a TTL.

### Lifecycle implementation

Registered hooks:

```text
transition_post_status
post_updated
save_post_post
set_object_terms
added_post_meta
updated_post_meta
deleted_post_meta
before_delete_post
shutdown
```

Coverage/rationale:

- `transition_post_status`: actual visibility transitions including `draft/future -> publish` and `publish -> draft/private/trash/future`;
- `post_updated`: previous slug/status plus `publish -> publish` edits;
- `save_post_post`: published-save safety net after normal editorial persistence;
- `set_object_terms`: category changes;
- post-meta hooks: featured image (`_thumbnail_id`) and Yoast (`_yoast_wpseo_*`) changes;
- `before_delete_post`: permanent deletion;
- `shutdown`: deduplicated delivery after request-local editing hooks finish.

Separate trash/untrash delivery hooks are intentionally not registered because transitions across the public `publish` boundary are already represented by `transition_post_status` and duplicate hook families would cause redundant events.

### Request-local deduplication

- [x] overlapping transition/post-update/save hooks aggregate by post ID;
- [x] event priority is `deleted > status_changed > slug_changed > content_updated`;
- [x] latest current slug/status wins;
- [x] a real previous slug/status is preserved when available;
- [x] draft-only saves that never cross the public boundary emit no public revalidation event.

### Payload contract

```json
{
  "resource": "news",
  "postId": 123,
  "slug": "noticia-actual",
  "previousSlug": "noticia-anterior",
  "status": "draft",
  "previousStatus": "publish",
  "event": "status_changed"
}
```

Supported event values:

```text
status_changed
slug_changed
content_updated
deleted
```

Permanent deletion uses terminal `status="deleted"` because no WordPress post status remains afterward.

### HMAC/security regression

Provider headers:

```text
X-Headless-Timestamp: <unix-seconds>
X-Headless-Signature: sha256=<lowercase-hmac-sha256-hex>
```

Signature input:

```text
<timestamp>.<exact raw JSON request body>
```

- [x] HMAC SHA-256 output regression-tested.
- [x] delivered raw body is exactly the body used for signing.
- [x] shared secret is not present in the payload.
- [x] target requires HTTPS by default.
- [x] redirects are disabled for outbound delivery.
- [x] secret requires at least 32 characters.
- [x] transport failure returns a safe failure instead of throwing into the editorial operation.
- [x] failure logging does not expose secret/signature.
- [x] delivery result can be observed through `headless_api_core_revalidation_delivery`.

Consumer requirements are documented in `docs/REVALIDATION.md`, including timing-safe comparison and a recommended 300-second replay window.

### Automated CI checkpoint

Previous workflow checkpoint:

```text
Validate and Build #98
Run ID: 34532197216
Commit: 7f0eb0c213c8b03fed232bf7a8fada078f6cf8cd
Result: SUCCESS
```

That checkpoint passed:

- [x] PHP lint;
- [x] News SEO fallback test;
- [x] News timezone + author test;
- [x] News public visibility test;
- [x] News revalidation HMAC/signature + safe failure test;
- [x] News lifecycle hook registration test;
- [x] News lifecycle event/deduplication test;
- [x] plugin version resolution;
- [x] v0.2.2 ZIP build;
- [x] artifact upload.

The Provider freshness hardening above was added after that checkpoint, so the **latest candidate-head CI must pass again** before the refreshed ZIP is installed on the CMS.

### Reusable live smoke

`tests/news-live-smoke.php` can validate the normal public contract plus optional lifecycle state using:

```text
HEADLESS_CORE_API_URL
HEADLESS_EXPECTED_VERSION
HEADLESS_EXPECTED_TIMEZONE_OFFSET
HEADLESS_EXPECT_PRESENT_SLUG
HEADLESS_EXPECT_ABSENT_SLUG
```

This test is intentionally not part of default CI because CI must not depend on one external WordPress installation.

### HOSGEDOPOL runtime lifecycle gate — pending

Provider target: `https://cms.hosgedopol.gob.do`
Consumer staging: `https://dev.hosgedopol.gob.do`

The following checks remain required on the real CMS + Consumer before v0.2.2 can merge/promote:

- [ ] After deploying Provider freshness hardening, `publish -> draft` is reflected by `/news` on the first normal refresh without clearing browser cache manually.
- [ ] Create a News item as draft → absent from Provider and Consumer.
- [ ] draft → publish → appears practically immediately.
- [ ] publish → draft → disappears practically immediately and detail is 404.
- [ ] republish → reappears.
- [ ] publish → private → disappears.
- [ ] publish → trash → disappears.
- [ ] restore and publish → reappears.
- [ ] publish → publish edit updates title/excerpt/content/featured image/internal images/categories/author/dates/SEO.
- [ ] published slug change invalidates old slug and exposes new slug.
- [ ] permanent delete removes catalog/search/detail presence.
- [ ] scheduled `future` post is absent before its time and appears only after WordPress/WP-Cron performs the real `future -> publish` transition.
- [ ] deliberately failing/unreachable Consumer revalidation target does not prevent WordPress save/publication.
- [ ] Provider still returns only public News throughout the complete lifecycle.
- [ ] Consumer keeps a short fallback TTL (recommended ~60 seconds) so a missed webhook still converges.

### Release status

v0.2.2 is **implementation/automated-test ready but not runtime-approved yet**.

Do not merge the lifecycle candidate into `develop`, promote News to `main`, or resume Hero until Provider freshness + signed HOSGEDOPOL Consumer revalidation both pass the real lifecycle checklist above.
