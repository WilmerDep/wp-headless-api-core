# Forms Core — dynamic discovery contract

Forms Core is designed for multiple sites and must not require a Consumer to hardcode HOSGEDOPOL-specific form shapes.

## Canonical identity

Each published form has a WordPress `post_name` exposed as `slug`.

Example:

```json
{
  "slug": "solicitud-de-beca"
}
```

The slug is the canonical public identifier used by Consumers. Field identifiers are independent and are exposed through each field's exact `name` value (including camelCase when used by an existing Consumer).

## Discover available forms

```http
GET /wp-json/headless-core/v1/forms
```

The collection returns every published + enabled form using the same serializer as the single-form endpoint. A generic Consumer can therefore discover forms without knowing their slugs in advance.

Every item contains its dynamic sections, fields, validation/layout hints, capability state and canonical API URLs.

Example:

```json
{
  "items": [
    {
      "slug": "solicitud-de-beca",
      "title": "Solicitud de beca",
      "fields": [
        {
          "name": "fullName",
          "type": "text",
          "label": "Nombre completo",
          "required": true,
          "width": 12
        }
      ],
      "api": {
        "schema": "https://provider.example/wp-json/headless-core/v1/forms/solicitud-de-beca",
        "submit": "https://provider.example/wp-json/headless-core/v1/forms/solicitud-de-beca/submit"
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

## Read one form

Consumers may use the returned `api.schema` URL or the stable route:

```http
GET /wp-json/headless-core/v1/forms/{slug}
```

The response is the source of truth for the form schema. The Consumer should render from `sections[]` and `fields[]` instead of maintaining a duplicate field definition when a generic renderer is appropriate.

## Submit dynamically

Consumers may use the returned `api.submit` URL. The body is a JSON object whose keys are the exact `fields[].name` identifiers exposed by the schema.

```http
POST /wp-json/headless-core/v1/forms/{slug}/submit
Content-Type: application/json
```

Example:

```json
{
  "fullName": "Ana Pérez",
  "email": "ana@example.org"
}
```

Server-side validation uses the Provider schema, so creating another form in WordPress does not require changing Forms Core itself. A Consumer may still provide specialized widgets for selected fields through `ui` hints while preserving the same field contract.

## Multi-site rule

The Provider owns:

- form slug / canonical identity
- sections and fields
- field names/types/layout
- validation rules
- conditional visibility
- submission availability
- notification routing

The Consumer owns:

- placement/routing in the frontend
- final visual presentation
- specialized UI widgets
- optional local-profile/autofill UX

A generic Consumer can use `GET /forms` to build a registry automatically, while a curated site may map a page to one known slug. In both cases the actual field contract comes from the Provider API.
