# Directory contract — v0.4.0 candidate

## Scope

Directory is a reusable people-directory module for decoupled Consumers. It must remain generic and must not hardcode HOSGEDOPOL-specific group names, branding, domains, people or presentation behavior.

The first Consumer currently renders an institutional officials directory with these public inputs: name, role, date of joining, portrait, phone and email. The Provider should preserve those needs while allowing the same person model to be reused for other groups such as medical staff, administration, councils or teams.

Directory is expected to handle potentially large datasets. Bulk ingestion and day-to-day ordering therefore belong to the editorial product experience, not to one-off migration scripts.

## WordPress model

Editorial CPT:

```text
headless_person
```

Directory groups are modeled with a dedicated taxonomy:

```text
headless_directory_group
```

Group names and slugs are content, not code. Examples such as `directores`, `administrativos`, `medicos`, `consejo` or `equipo` belong to WordPress data and may change without a plugin release.

A person may belong to one or more groups. This allows one profile to be reused across overlapping public directories without duplicating the person record.

## Person fields

Initial public fields:

- `id` — stable WordPress post ID;
- `name` — full public display name;
- `role` — public role/title;
- `joinedAt` — optional canonical date in `YYYY-MM-DD` form;
- `image` — required public portrait object `{ url, alt, width, height }`;
- `phone` — optional public contact phone;
- `email` — optional public contact email;
- `summary` — optional short public biography/description;
- `order` — effective public order for the requested collection/group;
- `groups` — assigned public directory groups.

The WordPress editor title stores the person's public display name. The primary portrait is stored as the featured image but managed through a guided module UI.

The module must not expose WordPress user-account data. Phone/email are explicit public editorial fields owned by the Directory item, not derived from WordPress users.

An optional private editorial `externalId`/import key may be stored to support safe repeated spreadsheet imports. It is never exposed by the public REST contract.

## Group shape

Each public group is normalized as:

```json
{
  "id": 12,
  "slug": "directores",
  "name": "Directores",
  "order": 0
}
```

No HOSGEDOPOL-specific group is mandatory.

## Public collection

```text
GET /wp-json/headless-core/v1/directory
```

Response candidate:

```json
{
  "items": [
    {
      "id": 101,
      "name": "Dra. Ejemplo",
      "role": "Directora Ejecutiva",
      "joinedAt": "2025-06-01",
      "image": {
        "url": "https://cms.example.org/wp-content/uploads/persona.jpg",
        "alt": "Retrato institucional de Dra. Ejemplo",
        "width": 1200,
        "height": 1600
      },
      "phone": "+1 809 555 0000",
      "email": "directorio@example.org",
      "summary": null,
      "order": 0,
      "groups": [
        {
          "id": 12,
          "slug": "directores",
          "name": "Directores",
          "order": 0
        }
      ]
    }
  ]
}
```

## Group filter

The collection supports an optional group slug filter:

```text
GET /wp-json/headless-core/v1/directory?group=directores
```

Unknown/non-public group slugs should return an empty collection rather than leak editorial details.

## Groups endpoint

The first version should expose public groups so Consumers can build tabs/filters dynamically instead of hardcoding group labels:

```text
GET /wp-json/headless-core/v1/directory/groups
```

It returns only groups that are associated with at least one published, publicly valid Directory person.

Candidate response:

```json
{
  "items": [
    {
      "id": 12,
      "slug": "directores",
      "name": "Directores",
      "order": 0
    }
  ]
}
```

## Public visibility

A Directory person is public only when all are true:

- `post_status` is `publish`;
- the post is not password protected;
- a valid primary portrait exists.

Draft, pending, private, trash and future-before-publication items are never returned.

A published person without a valid portrait is excluded from the public contract because the first Consumer's cards/lightbox require a visual identity. WordPress may still keep the editorial record published; the Provider simply treats it as incomplete for public output.

## Ordering and drag-and-drop

Ordering is an explicit editorial feature, not a field editors should normally have to manage by typing numbers.

The Directory admin must provide a dedicated visual ordering experience with drag handles and immediate persistence feedback.

### Global person order

When the editor is viewing all people, drag-and-drop controls the global order stored through `menu_order`.

The unfiltered collection is ordered deterministically by:

```text
menu_order ASC, then ID ASC
```

### Per-group person order

A real Consumer requirement now exists for custom ordering after categorization. When the editor filters the ordering screen by a Directory group, drag-and-drop controls the order of people inside that group without forcing the same rank in every other group.

Per-group positions are stored as Provider-owned editorial metadata keyed by group term ID. The exact storage key is internal and not part of the public contract.

`GET /directory?group=<slug>` uses the group-specific order when present and falls back deterministically to the global order/ID when a relationship has no explicit position.

This allows one person to appear, for example, first in one team and later in another without duplicating the person record.

### Group order

Directory groups themselves should also support a simple drag-and-drop order so Consumers can render dynamic tabs/sections in the editorially chosen sequence.

Group order is stored as taxonomy term metadata and exposed as `order` in the groups shape.

The groups endpoint is ordered by:

```text
group order ASC, then name ASC, then term_id ASC
```

### Persistence/security

Drag-and-drop saves must:

- require appropriate edit capabilities;
- use a WordPress nonce;
- validate all post/term IDs server-side;
- ignore IDs outside the Directory module;
- persist atomically enough that a failed request does not silently present a fake saved order;
- provide visible saving/saved/error feedback in the admin UI;
- trigger Directory revalidation when the effective public order changes.

## Spreadsheet bulk import

Directory v0.4.0 must include a first-class spreadsheet importer because manual creation is not acceptable for large institutional directories.

### Supported files

Primary format:

```text
.xlsx
```

CSV may also be accepted as a compatibility format, but Excel `.xlsx` is the required editorial workflow.

The importer should expose both:

- a drag-and-drop upload area;
- a conventional file picker.

### Template

The admin must provide a downloadable spreadsheet template so editors know the supported columns and formats before importing.

Initial logical columns:

```text
external_id
name
role
joined_at
phone
email
summary
groups
status
order
image_url
image_alt
```

Rules:

- `name` is required;
- `external_id` is optional for a first import but strongly recommended for safe future updates;
- `groups` accepts one or more group names/slugs using a documented separator;
- unknown groups may be created only when the importer explicitly allows it and the current user has the required capability;
- `status` is constrained to allowed editorial states, with a safe default such as `draft` when omitted;
- `order` is optional because editors can reorder visually after import;
- `image_url` is optional; a row without a valid portrait may be imported but remains publicly incomplete until a portrait is assigned;
- embedded Excel drawing objects are not a required import format for v0.4.0.

### Import flow

The import is a guided multi-step flow:

1. **Upload** — drop/select the spreadsheet.
2. **Validate** — parse without writing to WordPress.
3. **Preview** — show row counts and clear `ready / warning / error` states.
4. **Options** — choose duplicate/update behavior and optional default group/status.
5. **Import** — create/update only validated rows.
6. **Result** — show created, updated, skipped and failed counts with row-level diagnostics.

No spreadsheet upload should immediately mutate content before the validation/preview step.

### Duplicate/update strategy

Repeated imports must avoid accidental duplicates.

Preferred update key:

```text
external_id
```

When an existing Directory person has the same private import key, the importer may update that record according to the chosen import mode.

If a row has no `external_id`, the importer must not silently guess identity from the person's name. It should create a new record or flag a possible duplicate for editor review according to the selected mode.

Email may be shown as a duplicate warning signal, but it is not a guaranteed unique identity key.

### Import modes

The UX should expose explicit choices such as:

- **Crear nuevos solamente**;
- **Crear y actualizar por ID externo**;
- **Vista previa solamente**.

Destructive deletion of WordPress records merely because they are absent from a spreadsheet is not part of the initial importer.

### Image handling

Portrait handling must be safe and predictable:

- an existing WordPress attachment can still be selected manually after import;
- an optional `image_url` may be imported only through safe HTTP handling, MIME validation and size limits if remote-image ingestion is enabled;
- failed remote image download must not abort the complete spreadsheet import;
- the row should remain imported with a warning and without public visibility until its required portrait is fixed;
- alt text from `image_alt` is sanitized as editorial accessibility text.

### Large imports

The importer must not assume a tiny spreadsheet. It should be designed so large imports do not exceed ordinary WordPress request limits.

Implementation may process validated rows in bounded batches and report progress in the UI. Import failure for one row must not corrupt already validated unrelated rows, and the result screen must clearly identify partial failures.

## Editorial UX

The wp-admin experience should follow the approved Hero quality level.

Recommended Directory workspace:

- **Personas** — searchable/filterable people list with status/group filters and bulk actions;
- **Ordenar** — dedicated drag-and-drop screen; filter by group for per-group ordering;
- **Grupos** — manage reusable groups and drag their display order;
- **Importar** — Excel/CSV importer with template download, drag-and-drop upload and preview;
- **Añadir persona** — guided individual editor.

Individual editor requirements:

- friendly Spanish-facing labels through translatable strings;
- card-based sections with clear spacing;
- portrait selector with preview and recommended image guidance;
- required/optional badges;
- group selector that allows one or more groups;
- date input for joining date;
- public contact fields with concise explanations;
- global order may remain visible as an advanced/fallback numeric field, while drag-and-drop is the primary ordering workflow;
- no raw technical meta boxes exposed to normal editors;
- responsive single-column fallback on narrower admin widths.

Bulk UX should also support selecting multiple people and assigning/removing groups without opening every profile individually when WordPress capabilities allow it.

## Validation and sanitization

- role/name/summary: text sanitization appropriate to their field type;
- email: `sanitize_email` and empty when invalid;
- phone: normalized as a public display string without executing markup;
- joinedAt: strict `YYYY-MM-DD` validation;
- image/alt: WordPress attachment metadata with optional explicit accessible-alt override;
- order: integer;
- groups: valid taxonomy terms only;
- spreadsheet cell values: normalized/sanitized before any WordPress write;
- imported URLs: safe URL validation and safe remote requests only when that optional capability is enabled;
- spreadsheet formulas are treated as data input only; the importer does not execute formulas/macros.

## Freshness

Directory follows the same Provider freshness policy as News/Hero:

- collection queries bypass persistent query-result caching;
- REST responses emit no-store/no-cache headers;
- public state is determined directly from current WordPress state.

## Revalidation

Directory should use the existing generic signed `Revalidation_Client`, not a separate transport.

Resource name:

```text
directory
```

Consumer-specific tags/paths remain outside the Provider. A future/initial Consumer may invalidate a tag such as `headless-directory` plus the pages where that directory is rendered.

Lifecycle coverage should include status changes, published edits, portrait/meta changes, group changes, global/per-group ordering, group ordering and permanent deletion, with request-local deduplication following the established News/Hero pattern.

A successful bulk import should avoid emitting one redundant Consumer webhook per internal write when the same request/batch can be collapsed safely into a smaller Directory invalidation set.

## QA additions for bulk UX

Directory QA must include:

- XLSX template download;
- valid XLSX import;
- malformed/unsupported spreadsheet rejection;
- preview performs no writes;
- required-column and per-row validation;
- create-only import;
- update-by-`external_id` import;
- duplicate warning behavior;
- group creation/assignment rules;
- optional image URL success/failure if enabled;
- partial row failure reporting;
- sufficiently large import/batched processing smoke test;
- global drag-and-drop ordering;
- per-group drag-and-drop ordering;
- group drag-and-drop ordering;
- reload preserves the saved order;
- unauthorized reorder/import attempts are rejected;
- order/import changes trigger the expected Directory freshness/revalidation behavior;
- News and Hero remain unchanged.

## Explicit exclusions for v0.4.0

- Services;
- generic Galleries;
- links-of-interest management;
- frontend card/lightbox design;
- private staff records;
- WordPress user-account synchronization;
- embedded Excel image/drawing extraction;
- spreadsheet macros;
- destructive synchronization/deleting records missing from an imported spreadsheet;
- HOSGEDOPOL-specific group names or business rules.

## First Consumer mapping

The existing HOSGEDOPOL `directorio-de-funcionarios` page currently uses the following static shape:

```ts
{
  name,
  role,
  date,
  image,
  phone?,
  email?
}
```

The Consumer adapter can map `joinedAt` to its localized `date` string and consume the remaining fields directly. This preserves the existing UI while moving source-of-truth ownership to WordPress.