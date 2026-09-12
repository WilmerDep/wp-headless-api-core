# Directory Surfaces Audit — HOSGEDOPOL Consumer

> Internal project documentation. This file must not be packaged inside the distributable WordPress plugin ZIP.

## Purpose

Document how the generic Directory model can serve the current HOSGEDOPOL `Sobre nosotros` surfaces without duplicating people into multiple WordPress CPTs.

This is an architectural audit only. Do not expand the public contract until the current Directory v0.4.0 UX/import/order gate is closed.

## Current Consumer surfaces audited

### `/directorio-de-funcionarios`

Current Consumer source: `src/app/directorio-de-funcionarios/page.tsx`.

The page currently hardcodes three institutional authorities and consumes a repeated person-card model containing:

- name;
- role;
- date/designation date;
- phone;
- email;
- image.

This maps naturally to the existing `headless_person` entity and `headless_directory_group` filtering.

### `/despacho-director-general`

Current Consumer source: `src/app/despacho-director-general/page.tsx`.

This is a richer profile for one current authority. It currently requires:

- portrait;
- name;
- role;
- designation date;
- institutional/organization metadata;
- multiple highlighted facts such as entry year and recognition;
- longer biography/content;
- link back to the directory.

The person represented on this page can also appear in the general officials directory.

## Recommendation: keep one person CPT

Do **not** create another CPT only for `Despacho del Director` or only for current officials unless a future requirement proves that the underlying entity is fundamentally different.

Reason:

- the same human can appear in multiple public surfaces;
- duplicating the person into two CPTs creates synchronization problems for name, portrait, role, dates and contact;
- annual staff changes become harder to maintain;
- imports would need duplicate rows;
- ordering and revalidation become fragmented.

The existing generic entity is the correct source of truth:

`headless_person`

## Use groups as reusable surface membership

The current taxonomy already supports one person belonging to multiple groups. That is enough for the first stage.

Example institution-specific groups may be created as **data**, not plugin code:

- `autoridades-institucionales`;
- `directorio-funcionarios`;
- `despacho-director`;
- `exdirectores-administrativos`;
- `exdirectores-medicos`;
- `administrativos`;
- `medicos`.

A person may belong simultaneously to:

- `despacho-director` and `directorio-funcionarios`;
- `administrativos` and `autoridades-institucionales`;
- any other institution-defined combination.

This means the same record can power more than one frontend page without duplication.

## Former directors

Former directors should also remain persons in the same CPT.

Recommended modeling:

- group identifies the former-director collection, for example `exdirectores-administrativos` or `exdirectores-medicos`;
- `joinedAt` may represent the start/designation date only if that semantic remains valid for the page;
- if the Consumer requires both start and end of tenure, add explicit optional tenure fields later rather than overloading one date;
- global/per-group drag-and-drop ordering already gives editorial control over the presentation.

Do not create `headless_former_director` as a separate CPT merely because the person appears in a historical section.

## Rich Director Office profile

The current v0.4.0 Directory contract has a short plain-text `summary`, which is enough for card/list surfaces but not enough to reproduce the full current Director Office page.

A later extension should evaluate optional generic profile-detail fields rather than a second CPT.

Candidate additions, only if the Consumer needs them:

- rich biography/body;
- designation/start date distinct from general joined date when necessary;
- optional structured highlights, e.g. label/value/icon-key;
- optional end date/tenure for historical authorities;
- optional profile variant or featured flag only if grouping alone cannot express the surface.

Prefer generic names and structures. Do not add `policeEntryYear`, `meritoPolicial`, `HOSGEDOPOLDirector`, etc. to the Provider model.

## Endpoint strategy

The current generic endpoints are sufficient for collection surfaces:

- `GET /wp-json/headless-core/v1/directory`
- `GET /wp-json/headless-core/v1/directory?group=<slug>`
- `GET /wp-json/headless-core/v1/directory/groups`

For a single rich profile, later evaluate one of these generic options:

1. `GET /directory/{id}` or `/directory/{slug}`; or
2. fetch a filtered collection for a group expected to contain one featured person.

A dedicated HOSGEDOPOL-specific endpoint such as `/director-general` should be avoided.

## Ordering

Current v0.4.0 already supports:

- global person order;
- per-group person order;
- group order.

This is valuable for overlapping surfaces because the same person can be first in `despacho-director` but third in `directorio-funcionarios` without duplicating the person record.

## Import implications

The Excel importer should continue importing one row per person.

The `groups` column can assign multiple memberships. This means one imported person can immediately participate in multiple Consumer surfaces.

Avoid duplicating rows just to place the same person into more than one section.

## Recommended implementation sequence

1. Finish current Directory v0.4.0 editor, ordering and Excel UX.
2. Validate `/directory`, `?group=` and `/directory/groups` against real WordPress data.
3. Connect the general directory / former-director collection in the Consumer.
4. Audit exactly which additional fields the Director Office page still needs.
5. Extend the same `headless_person` model only with fields justified by that audit.
6. Add single-profile endpoint only if needed.
7. Keep Services as the next independent module after Directory closes.

## Current architectural decision

**Default decision: one reusable person CPT, many configurable groups, no duplicate current-director CPT.**

This decision can be revisited only if a later surface requires a genuinely different lifecycle/data model rather than merely a richer presentation of the same person.
