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
- [ ] `GET /wp-json/headless-core/v1/health` returns HTTP 200.
- [ ] Health response matches the documented v0.1.0 contract.
- [ ] Plugin deactivation/reactivation smoke test completed.

## Approval rule

Bootstrap + Health is considered runtime-approved only after the remaining checks above pass on the target CMS. News development must not begin before Health is validated end-to-end.
