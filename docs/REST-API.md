# REST API

## Namespace

`/wp-json/headless-core/v1/`

The `v1` namespace is the public compatibility boundary for the first contract generation.

## Health

### `GET /wp-json/headless-core/v1/health`

Public, read-only endpoint used to verify that the plugin is loaded and the v1 REST namespace is available.

Expected HTTP status: `200 OK`.

Response contract:

```json
{
  "ok": true,
  "service": "Headless API Core",
  "version": "0.1.0"
}
```

No authentication is required for Health. The route still declares an explicit permission callback.

## Planned, not implemented in v0.1.0

- `GET /headless-core/v1/news`
- `GET /headless-core/v1/news/{slug}`
- `GET /headless-core/v1/hero`
- `GET /headless-core/v1/services`
- `GET /headless-core/v1/directory`
- `GET /headless-core/v1/galleries`
- `GET /headless-core/v1/settings`

These routes are not contractually available until their implementation and response schemas are documented.

## Breaking changes

Do not silently remove fields, rename fields, change field meaning or alter response shapes used by consumers. Breaking changes must be documented and versioned, including coordinated updates in known consumer repositories.
