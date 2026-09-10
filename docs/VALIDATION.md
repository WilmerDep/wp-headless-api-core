# Validation

This document records runtime validation evidence for Headless API Core.

## HOSGEDOPOL CMS — v0.1.0

Target CMS: `https://cms.hosgedopol.gob.do`

### 2026-09-08

- [x] Installable ZIP uploaded successfully through WordPress administration.
- [x] Plugin recognized as **Headless API Core**.
- [x] Plugin version reported as `0.1.0`.
- [x] Plugin activated successfully.
- [x] WordPress Plugins administration remained usable after activation.
- [x] `GET /wp-json/headless-core/v1/health` returns successfully from the target CMS.
- [x] Health response matches the documented v0.1.0 contract: `ok=true`, `service="Headless API Core"`, `version="0.1.0"`.
- [x] Plugin deactivation/reactivation smoke test completed later during the v0.2.0 release preflight; the plugin reactivated successfully and the public REST namespace remained healthy.

### Runtime approval

Bootstrap + Health is runtime-approved on the HOSGEDOPOL CMS as of 2026-09-08. News development proceeded from this validated provider baseline.

The deactivation/reactivation preflight was completed successfully during the v0.2.0 release gate.

---

## HOSGEDOPOL CMS — News v0.2.0 candidate

Target CMS: `https://cms.hosgedopol.gob.do`

### 2026-09-08

- [x] Candidate ZIP installed over v0.1.0 without blocking WordPress administration.
- [x] `GET /wp-json/headless-core/v1/news` returns real native WordPress posts.
- [x] Collection response contains normalized `items` and `pagination` objects.
- [x] Default collection page returns 12 items.
- [x] Initial runtime snapshot reported 83 published public items across 7 pages; a later content snapshot reported 88 items across 8 pages. This confirms pagination is based on the live published inventory rather than a fixed count.
- [x] News items expose IDs, slugs, titles, excerpts, publication/modification dates, featured images and categories.
- [x] Featured image metadata is being resolved from the WordPress Media Library.
- [x] Native WordPress category data is being normalized into the provider contract.
- [x] JSON Unicode escaping observed in the browser is valid JSON behavior and not a character-encoding failure.
- [x] Encoded excerpt entities such as `&hellip;` were normalized; the retested collection now returns decoded plain text such as `[…]`.
- [x] `GET /wp-json/headless-core/v1/news/{slug}` validated with published post ID `23910`.
- [x] News detail `content` returns rendered WordPress block HTML.
- [x] News detail `seo` returns the provider-owned SEO object with Yoast detected as the source.
- [x] Runtime SEO validation identified the CMS branding suffix `CMS API HOSGEDOPOL` in Yoast-generated title values.
- [x] First SEO cleanup attempt based on `get_bloginfo( 'name' )` was retested and did not remove the runtime suffix, proving Yoast's generated branding is not reliably identical to the WordPress blog name in this installation.
- [x] Feature branch now uses a more robust provider rule: when Yoast returns the native post title followed by a separator and additional CMS branding, expose the native title instead. This avoids hardcoding institution-specific CMS names while preserving genuinely custom SEO titles.
- [x] Robust SEO-title cleanup retested successfully on post ID `23910`: both `seo.title` and `seo.openGraph.title` now match the native public post title and no longer expose the internal `CMS API HOSGEDOPOL` suffix.
- [x] Legacy content observation: post ID `23916` has a percent-encoded WordPress source slug and decorative mathematical Unicode text. The provider intentionally preserves the native source slug; editorial permalink cleanup belongs in WordPress rather than silent API rewriting.
- [x] Unknown slug runtime test returns `code="headless_core_news_not_found"`, `message="News item not found."` and HTTP status `404`.
- [x] Pagination runtime test with `page=2&per_page=5` returns exactly five items and reports `page=2`, `perPage=5`, `totalItems=88`, `totalPages=18`.
- [x] A runtime experiment with `orderby=title&order=asc` reached WordPress title ordering, but the visible normalized titles exposed legacy source-title collation artifacts. Because the current News consumer does not require alphabetical ordering, `title` ordering was removed from the v0.2.0 candidate instead of freezing surprising semantics.
- [x] Narrowed ordering revalidated with `orderby=date&order=asc&per_page=5`: the CMS returned five items in strictly ascending publication order from `2024-03-06` through `2024-06-27`.
- [x] Yoast-unavailable fallback behavior is covered by an isolated CI regression test. With no global `YoastSEO()` function, the serializer returns `source="wordpress"`, the native title/excerpt, and the native featured image in Open Graph. The CI job executes this test before packaging and passed on the News feature branch.

### 2026-09-09 — deactivation/reactivation release preflight

- [x] Headless API Core v0.2.0 was manually deactivated and reactivated from WordPress administration.
- [x] The plugin returned to the active state without blocking WordPress administration.
- [x] After reactivation, `GET /wp-json/headless-core/v1/health` returned `ok=true`, `service="Headless API Core"`, `version="0.2.0"`.
- [x] After reactivation, `GET /wp-json/headless-core/v1/news` returned the live paginated News collection successfully.
- [x] No REST namespace regression was observed after the activation cycle.

### Approval

The News v0.2.0 candidate has passed the required runtime checks on the HOSGEDOPOL CMS, the isolated Yoast-unavailable fallback regression test, and the deactivation/reactivation release preflight. The Provider side is ready for final release gating once the Consumer visual/staging QA is confirmed.

---

## Cross-repository validation — HOSGEDOPOL Consumer

Consumer repository: `WilmerDep/hosgedopol-web`

Consumer branch: `feature/news-headless-consumer`

Consumer PR: `#2 — feat: connect public News to Headless API Core`

Provider baseline: News v0.2.0 contract from `wp-headless-api-core/develop`.

### 2026-09-09

The Provider was exercised by a real Consumer integration without adding Consumer-specific runtime behavior to this plugin.

Consumer workflow `Frontend Build #30` (`run 34424685876`) completed with `success` and included the following independent checkpoints:

- [x] `Validate live Headless News contract`.
- [x] `Build production bundle`.
- [x] `Smoke test public News consumer`.

The live contract smoke validated against the deployed WordPress CMS:

- [x] `/headless-core/v1/health` returns a healthy Provider response.
- [x] `/headless-core/v1/news` returns a valid paginated collection.
- [x] a collection item can be resolved through `/headless-core/v1/news/{slug}`.
- [x] detail content and SEO objects satisfy the Consumer's expected contract.
- [x] an unknown News slug returns HTTP 404 with `headless_core_news_not_found`.

The Consumer runtime smoke then exercised the built Next.js application and confirmed the technical path:

```text
WordPress CMS
  -> Headless API Core
  -> Consumer adapter
  -> Next.js production runtime
  -> public News/search routes
```

### Scope boundary

This cross-repository wiring is recorded here because it validates the plugin contract end-to-end. The Consumer application has its own development workstream and is not owned by this plugin repository.

Future Consumer-side UI/backend work should remain in its own project unless another cross-repository change is specifically required to validate a Provider contract. Any such exception must be documented in both repositories.

### Remaining gates outside the Provider

The following remain Consumer/release concerns rather than failures of the v0.2.0 Provider contract:

- [ ] visual QA of News surfaces;
- [ ] staging smoke test at `dev.hosgedopol.gob.do`;
- [ ] migration of the one local-only News article into WordPress before removing Consumer fallbacks;
- [ ] final Consumer cutover and fallback removal.

The local-only article is currently identified by slug:

```text
visita-del-director-al-hospital-general-docente-de-la-policia-nacional
```

Its transport into WordPress is expected to use Zippy or WXR outside the permanent Headless API Core runtime.
