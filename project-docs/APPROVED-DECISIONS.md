# Approved Architecture Decisions — Headless API Core

> Internal project documentation. This file is excluded from the distributable WordPress plugin ZIP through the existing `project-docs` packaging exclusion.

## 2026-09-12 — Annual licensing baseline approved

The annual licensing strategy is approved as the future baseline for institutional deployments.

### Commercial model

- license term: annual;
- renewal may be manual or automatic;
- future licensing backend / Pholio service owns issuance, renewal, entitlement signing and audit history;
- the plugin must never contain a hidden master password or private signing key.

### Expiration behavior

Expiration must **limit management/write capabilities, not break the public website**.

When a license is expired:

- existing published content remains intact;
- public read-only REST endpoints continue serving valid published content;
- frontends that consume the Provider continue operating;
- administrators can still sign in, inspect content, export/back up data, view diagnostics and review/refresh the license;
- create/edit/publish/import/reorder/group/settings mutations protected by the license are blocked until renewal;
- no posts, media, terms or metadata are deleted or silently modified;
- no remote kill switch or arbitrary remote-code execution is allowed.

Use the lifecycle already documented in `LICENSING-STRATEGY.md`:

`active -> grace -> expired`

The `grace` window remains writable with a clear renewal warning. The `expired` state becomes read-only for licensed management surfaces.

### Renewal and cryptography

Approved direction:

- asymmetric signed entitlement;
- licensing service keeps the private signing key;
- plugin ships only the verification/public key;
- locally cached signed entitlement keeps temporary licensing-service outages from immediately locking the institution;
- online refresh plus offline signed-token renewal should both be supported;
- public REST requests must never wait on a live licensing-server call.

### Implementation timing

Licensing is **documented and approved but intentionally deferred**.

Do not implement the enforcement layer until the main Provider roadmap and mutation surfaces are stable. The expected later implementation remains centralized through a License Manager / Entitlement / Client / Gate architecture rather than scattered checks in each module.

---

## 2026-09-12 — Directory: one person CPT, many groups

The Directory architecture is approved around a single reusable person source of truth:

`headless_person`

Do **not** create separate CPTs for current officials, former directors, the Director Office page, medical staff or administrative staff merely because they render in different Consumer sections.

### Groups define placement

Use configurable `headless_directory_group` terms to decide where a person appears.

Examples are institution data, not hardcoded plugin behavior:

- `directorio-funcionarios`;
- `despacho-director`;
- `autoridades-institucionales`;
- `exdirectores-administrativos`;
- `exdirectores-medicos`;
- `administrativos`;
- `medicos`.

A single person may belong to several groups at the same time.

This allows the Consumer to request the appropriate collection with the existing generic filter pattern:

`GET /wp-json/headless-core/v1/directory?group=<slug>`

### Ordering

Keep:

- global person order;
- per-group person order;
- group order.

The same person may therefore appear first in one surface and later in another without duplicating the WordPress record.

### Rich Director Office profile

`/despacho-director-general` should eventually consume the **same person record** used by the general directory.

If that surface needs richer data, extend the generic person model later with only justified optional fields such as richer biography, designation/tenure dates or generic structured highlights.

Do not introduce HOSGEDOPOL-specific Provider fields and do not create a second director CPT unless a future audit proves a genuinely separate lifecycle/data model.

---

## Current execution order

1. finish Directory v0.4.0 admin UX;
2. validate Excel import with real small and larger samples;
3. validate global/per-group drag-and-drop ordering;
4. validate `/directory`, `/directory?group=` and `/directory/groups` with real WordPress data;
5. connect the general directory / former-director surfaces in the Consumer;
6. audit any additional fields needed by the richer Director Office surface and extend the same person model only if justified;
7. continue to Services as the next independent Provider module;
8. implement licensing only after the core Provider feature/mutation surface is stable.
