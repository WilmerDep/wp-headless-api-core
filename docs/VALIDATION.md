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
- [ ] Plugin deactivation/reactivation smoke test completed.

### Runtime approval

Bootstrap + Health is runtime-approved on the HOSGEDOPOL CMS as of 2026-09-08. News development may begin from this validated provider baseline.

The deactivation/reactivation smoke test remains part of the release preflight before promoting the milestone to the stable release line.

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
- [ ] Yoast-unavailable fallback behavior confirmed by isolated automated test.

### Approval rule

The News contract remains a v0.2.0 candidate until the Yoast-unavailable fallback behavior is validated. Only after that check passes should the feature be merged into `develop` and used by the Next.js consumer.
