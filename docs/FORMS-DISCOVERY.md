# Forms Core — dynamic discovery contract

Forms Core is designed for multiple sites and Consumers. A Consumer must not need to hardcode an installation-specific WordPress slug in order to identify the purpose of a form.

## Identity model

A public form exposes three distinct identity values:

```json
{
  "key": "appointment-request",
  "slug": "cita-medica",
  "title": "Citas en Línea"
}
```

- `title` is editorial and visible. Editors may change it freely.
- `slug` is the canonical resource identifier for the current WordPress installation. It remains the path used by `api.schema` and `api.submit`.
- `key` is the stable semantic identifier of the form's purpose across Consumers and installations.

The semantic key is persisted as its own form metadata. It is not recalculated on every request and does not change when `title` or `slug` changes. New/legacy forms that do not yet have a key receive a one-time initial key based on their already-persisted slug; editors can then explicitly replace that initial value with a cross-site semantic key.

Keys are normalized to lowercase kebab-case, for example:

```text
contact
appointment-request
support-request
newsletter-signup
```

Published + enabled forms must not expose the same `key`. The editor prevents an active duplicate identity from becoming public.

## Backward compatibility

`schemaVersion` remains `1`.

Adding `key` is an additive field: existing Consumers that continue to use `slug` and `api.submit` remain valid. No existing route is removed or renamed.

Consumers can migrate gradually from slug-based mapping to semantic-key discovery.

## Discover available forms

```http
GET /wp-json/headless-core/v1/forms
```

The collection returns every published + enabled form using the same serializer as the single-form endpoint. Each item includes `key`, `slug`, editorial data, fields, validation/layout hints, capability state and canonical API URLs.

Example:

```json
{
  "items": [
    {
      "key": "appointment-request",
      "slug": "cita-medica",
      "title": "Citas en Línea",
      "fields": [
        {
          "name": "firstName",
          "type": "text",
          "label": "Nombre(s)",
          "required": true,
          "width": 6
        }
      ],
      "api": {
        "schema": "https://provider.example/wp-json/headless-core/v1/forms/cita-medica",
        "submit": "https://provider.example/wp-json/headless-core/v1/forms/cita-medica/submit"
      },
      "submission": {
        "enabled": true,
        "available": true,
        "method": "POST",
        "contentType": "application/json"
      }
    }
  ]
}
```

## Resolve by semantic key

Consumers may filter discovery directly:

```http
GET /wp-json/headless-core/v1/forms?key=appointment-request
```

The response keeps the normal collection shape:

```json
{
  "items": [
    {
      "key": "appointment-request",
      "slug": "cita-medica",
      "api": {
        "schema": "https://provider.example/wp-json/headless-core/v1/forms/cita-medica",
        "submit": "https://provider.example/wp-json/headless-core/v1/forms/cita-medica/submit"
      }
    }
  ]
}
```

The Consumer should identify the form by `key`, then use the returned `slug`/`api` URLs rather than rebuilding a resource URL from the semantic key.

## Read one form

The single-resource endpoint remains slug-based:

```http
GET /wp-json/headless-core/v1/forms/{slug}
```

Consumers may use the `api.schema` URL returned by discovery instead of constructing this route themselves.

## Submit dynamically

Consumers should prefer the returned `api.submit` URL:

```http
POST /wp-json/headless-core/v1/forms/{slug}/submit
Content-Type: application/json
```

The body is a JSON object whose keys are the exact `fields[].name` identifiers exposed by the schema.

```json
{
  "fullName": "Ana Pérez",
  "email": "ana@example.org"
}
```

Server-side validation uses the Provider schema. A Consumer may still provide specialized widgets through `ui` hints while preserving the same field contract.

## HOSGEDOPOL project mapping

These values are project configuration examples, not Forms Core runtime defaults:

```json
[
  {
    "key": "contact",
    "slug": "contacto",
    "title": "Contacto"
  },
  {
    "key": "appointment-request",
    "slug": "cita-medica",
    "title": "Citas en Línea"
  }
]
```

The Core plugin contains no HOSGEDOPOL-specific semantic mapping.

## Multi-site rule

The Provider owns:

- stable semantic `key`
- installation-specific form `slug`
- sections and fields
- field names/types/layout
- validation rules
- conditional visibility
- submission availability
- notification routing

The Consumer owns:

- mapping a page/feature to a semantic `key`
- placement/routing in the frontend
- final visual presentation
- specialized UI widgets
- optional local-profile/autofill UX

Recommended Consumer flow:

```text
semantic key
→ GET /forms?key={key}
→ take items[0]
→ use item.api.schema / item.api.submit
→ render item.sections + item.fields
```

This lets different WordPress installations use different slugs for the same semantic purpose without changing the Consumer integration.
