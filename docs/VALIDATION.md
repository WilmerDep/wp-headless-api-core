# Validation

This document records release-gate and runtime evidence for Headless API Core.

## HOSGEDOPOL CMS baseline

Target CMS:

```text
https://cms.hosgedopol.gob.do
```

Consumer staging:

```text
https://dev.hosgedopol.gob.do
```

## v0.1.0 — Bootstrap + Health

- [x] Installable ZIP recognized by WordPress.
- [x] Activation succeeded without blocking wp-admin.
- [x] `GET /wp-json/headless-core/v1/health` returned the documented contract.
- [x] Deactivation/reactivation smoke passed.

## v0.2.x — News

### v0.2.0

- [x] Native WordPress posts exposed through `/news`.
- [x] Pagination and chronological ordering validated.
- [x] Detail HTML, featured images, categories and provider-owned SEO validated.
- [x] Unknown/unpublished News detail returns deterministic 404.
- [x] Technical Provider → Consumer integration passed.
- [x] Late-night timezone defect identified before stable promotion.

### v0.2.1

- [x] `publishedAt` preserves WordPress site-local editorial time and offset.
- [x] `modifiedAt` uses the same timezone semantics.
- [x] Public author contract limited to `author.name`.
- [x] No email/login/roles/capabilities exposed.
- [x] Live CMS and Consumer rendering validated.
- [x] Merged into `develop`.

### v0.2.2 — lifecycle + Provider freshness

Automated coverage:

- [x] public-only collection/detail visibility;
- [x] draft/pending/private/trash/future hidden;
- [x] password-protected posts hidden;
- [x] HMAC SHA-256 signing;
- [x] safe failure behavior;
- [x] lifecycle hook registration;
- [x] lifecycle event/deduplication semantics;
- [x] Provider collection/detail query freshness;
- [x] explicit News REST no-store/no-cache policy;
- [x] version-aware package build.

Runtime findings/validation:

- [x] v0.2.2 installed on the real HOSGEDOPOL CMS.
- [x] `/health` reported `0.2.2`.
- [x] `publish -> draft` stopped being exposed by the Provider.
- [x] initial runtime QA showed the Provider itself could remain stale for multiple refreshes.
- [x] Provider freshness hardening added with `cache_results=false` and explicit REST `no-store` headers.
- [x] after deploying the hardening, `/news` and `/news/{slug}` reflected editorial state on a normal refresh.
- [x] remaining skeleton/on-focus refresh behavior isolated to the Consumer UX layer.
- [x] Provider-side News gate accepted as closed.
- [x] PR #6 squash-merged into `develop`.

Merged commit:

```text
20b134052beffcded254ac5b41384f15ec3dcda7
```

News Issue #5 is closed. Future Consumer UX refinements do not block Provider module development.

---

## v0.3.0 — Hero candidate

Branch:

```text
feature/hero-v0.3.0
```

PR:

```text
#7 — feat: Hero v0.3.0 provider module
```

Issue:

```text
#4 — Hero v0.3.0: implementar contrato y módulo reusable
```

### Implemented

- [x] editorial-only CPT `headless_hero`;
- [x] raw CPT hidden from native WordPress REST;
- [x] featured image as required primary/desktop image;
- [x] optional mobile image attachment;
- [x] optional safe relative/absolute HTTP(S) href;
- [x] optional explicit accessible alt override;
- [x] optional constrained object-position;
- [x] `menu_order` public ordering with ID tie-breaker;
- [x] public read-only `GET /wp-json/headless-core/v1/hero`;
- [x] published/non-password-protected query boundary;
- [x] invalid/missing primary image exclusion;
- [x] fresh Provider query using `cache_results=false`;
- [x] explicit Hero REST no-store/no-cache headers;
- [x] WordPress admin meta box with nonce and capability checks;
- [x] media picker limited to Hero editing screens;
- [x] plugin candidate bumped to `0.3.0`;
- [x] Hero wired into the shared plugin bootstrap after News.

### Automated regression coverage

The v0.3.0 candidate CI includes:

- [x] PHP lint;
- [x] all News regression tests;
- [x] Hero href sanitizer;
- [x] Hero object-position sanitizer;
- [x] Hero serializer normalized primary/mobile images;
- [x] explicit-alt and attachment-alt fallback behavior;
- [x] exclusion when primary image is invalid;
- [x] Hero public query contract;
- [x] `menu_order ASC`, then `ID ASC` ordering;
- [x] `cache_results=false` freshness boundary;
- [x] no-store policy limited to the `/hero` route;
- [x] version-aware v0.3.0 package build.

Known green checkpoint:

```text
Validate and Build #134
Run ID: 34547567399
Commit: f2fd399abf02d33bf348dbe4fefb9b905e8fd535
Result: SUCCESS
```

Documentation commits after that checkpoint trigger later CI runs; the latest candidate HEAD must also be green before installation.

### Runtime CMS gate — pending

Before v0.3.0 can merge/promote:

- [ ] install the current v0.3.0 candidate ZIP;
- [ ] confirm `/health` reports `0.3.0`;
- [ ] confirm a Hero Slides editorial menu appears in WordPress;
- [ ] create at least two Hero items;
- [ ] verify only `publish` items are returned;
- [ ] verify draft/private/future/trash items do not appear;
- [ ] verify an item without a valid featured image is not returned;
- [ ] verify primary image URL/alt/width/height;
- [ ] verify optional mobile image payload;
- [ ] verify relative and absolute HTTP(S) href values;
- [ ] verify unsafe href schemes are not persisted/exposed;
- [ ] verify object-position sanitization;
- [ ] verify deterministic `menu_order`, then ID ordering;
- [ ] verify one normal `/hero` refresh reflects editorial changes;
- [ ] validate Consumer mapping without hardcoding HOSGEDOPOL-specific runtime values in the plugin.

### Consumer boundary

Hero Provider validation does not require moving carousel presentation logic into WordPress. The Consumer remains responsible for:

- autoplay;
- arrows/dots;
- swipe gestures;
- transitions;
- responsive layout;
- skeleton/loading UX.

Services, Directory, Galleries and Settings remain deferred until Hero passes the runtime gate.
