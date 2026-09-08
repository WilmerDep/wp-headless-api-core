# Revalidation

Revalidation is not implemented in v0.1.0.

## Planned behavior

```text
Editor publishes/updates content
  -> WordPress event
  -> authenticated revalidation request
  -> decoupled frontend
  -> cache/content revalidation
```

## Design constraints

- No public secrets.
- No secret values committed to Git.
- Requests must be authenticated and integrity-protected.
- The target frontend must be configurable.
- Failures must not prevent WordPress content from being saved.
- Retry/logging behavior should be designed before production use.
- Revalidation should target the minimum necessary paths/tags when the consumer supports it.
