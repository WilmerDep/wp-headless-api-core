# Directory contract — v0.4.0 candidate

## Scope

Directory is a reusable people-directory module for decoupled Consumers. It must remain generic and must not hardcode HOSGEDOPOL-specific group names, branding, domains, people or presentation behavior.

The first Consumer currently renders an institutional officials directory with these public inputs: name, role, date of joining, portrait, phone and email. The Provider should preserve those needs while allowing the same person model to be reused for other groups such as medical staff, administration, councils or teams.

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
- `order` — public deterministic order;
- `groups` — assigned public directory groups.

The WordPress editor title stores the person's public display name. The primary portrait is stored as the featured image but managed through a guided module UI.

The module must not expose WordPress user-account data. Phone/email are explicit public editorial fields owned by the Directory item, not derived from WordPress users.

## Group shape

Each public group is normalized as:

```json
{
  "id": 12,
  "slug": "directores",
  "name": "Directores"
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
          "name": "Directores"
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
      "name": "Directores"
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

## Ordering

People are ordered deterministically by:

```text
menu_order ASC, then ID ASC
```

The first version uses one global person order. Per-group ordering is deliberately deferred until a real Consumer requirement justifies the additional model complexity.

Groups are ordered deterministically by:

```text
name ASC, then term_id ASC
```

A future explicit group-order field can be added compatibly if required.

## Editorial UX

The wp-admin experience should follow the approved Hero quality level:

- friendly Spanish-facing labels through translatable strings;
- card-based sections with clear spacing;
- portrait selector with preview and recommended image guidance;
- required/optional badges;
- group selector that allows one or more groups;
- date input for joining date;
- public contact fields with concise explanations;
- simple numeric order field;
- no raw technical meta boxes exposed to normal editors;
- responsive single-column fallback on narrower admin widths.

## Validation and sanitization

- role/name/summary: text sanitization appropriate to their field type;
- email: `sanitize_email` and empty when invalid;
- phone: normalized as a public display string without executing markup;
- joinedAt: strict `YYYY-MM-DD` validation;
- image/alt: WordPress attachment metadata with optional explicit accessible-alt override;
- order: integer;
- groups: valid taxonomy terms only.

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

Lifecycle coverage should include status changes, published edits, portrait/meta changes, group changes, ordering and permanent deletion, with request-local deduplication following the established News/Hero pattern.

## Explicit exclusions for v0.4.0

- Services;
- generic Galleries;
- links-of-interest management;
- frontend card/lightbox design;
- private staff records;
- WordPress user-account synchronization;
- per-group person order;
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