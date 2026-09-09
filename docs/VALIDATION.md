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
- [x] Runtime pagination reports 83 published public items across 7 pages at the default page size.
- [x] News items expose IDs, slugs, titles, excerpts, publication/modification dates, featured images and categories.
- [x] Featured image metadata is being resolved from the WordPress Media Library.
- [x] Native WordPress category data is being normalized into the provider contract.
- [x] JSON Unicode escaping observed in the browser is valid JSON behavior and not a character-encoding failure.
- [x] Runtime observation identified encoded excerpt entities such as `&hellip;`; serializer normalization was corrected on the feature branch after this test.
- [ ] Reinstall/retest the candidate package containing the excerpt normalization fix.
- [ ] `GET /wp-json/headless-core/v1/news/{slug}` validated with a real published slug.
- [ ] News detail `content` validated with real rendered WordPress HTML.
- [ ] News detail `seo` payload validated with Yoast active and fallback behavior confirmed.
- [ ] Unknown slug returns HTTP 404 with `headless_core_news_not_found`.
- [ ] Pagination parameter behavior validated (`page`, `per_page`).
- [ ] Ordering parameter behavior validated (`order`, `orderby`).

### Approval rule

The News contract remains a v0.2.0 candidate until collection and detail routes, 404 behavior, pagination/order controls, text normalization and SEO fallback behavior are validated on the target CMS. Only after those checks pass should the feature be merged into `develop` and used by the Next.js consumer.
