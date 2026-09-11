# Configuration

## Health / News read API

The public Health and News REST endpoints do not require installation-specific secrets.

## News v0.2.2 revalidation

Realtime editorial revalidation is optional at runtime but required for the HOSGEDOPOL News lifecycle release gate.

Provider-side private configuration:

```text
HEADLESS_REVALIDATION_URL
HEADLESS_REVALIDATION_SECRET
HEADLESS_REVALIDATION_TIMEOUT
```

### Recommended `wp-config.php`

```php
define( 'HEADLESS_REVALIDATION_URL', 'https://dev.example.org/api/headless/revalidate' );
define( 'HEADLESS_REVALIDATION_SECRET', getenv( 'HEADLESS_REVALIDATION_SECRET' ) ?: '' );
```

`HEADLESS_REVALIDATION_TIMEOUT` is optional. Default: `3` seconds, clamped by the plugin between `0.5` and `10` seconds.

The shared secret must be at least 32 characters and should be generated from strong random bytes. It must not be committed to Git, rendered in WordPress public REST output, exposed in HTML, or use a browser-visible environment prefix such as `NEXT_PUBLIC_*` on a Next.js Consumer.

The Consumer must configure the **same** private `HEADLESS_REVALIDATION_SECRET` value on its server runtime.

### Resolution order

For each Provider setting the plugin resolves:

1. PHP/WordPress constant;
2. environment variable;
3. filter override.

Available filters:

```text
headless_api_core_revalidation_url
headless_api_core_revalidation_secret
headless_api_core_revalidation_timeout
headless_api_core_revalidation_allow_insecure_http
headless_api_core_revalidation_log_errors
```

HTTPS is required by default. `headless_api_core_revalidation_allow_insecure_http` exists only for deliberate local-development exceptions.

See `docs/REVALIDATION.md` for HMAC format, payload schema, lifecycle hooks and Consumer validation requirements.

## General configuration principles

Institution-specific values must remain configurable rather than hardcoded. This includes:

- public frontend URL;
- preview target URL;
- revalidation target;
- logos and institutional media;
- contact data;
- social links;
- institutional links;
- integration secrets.

Secrets must never be exposed through public REST responses or committed to the repository.

Configuration may use WordPress options/settings, constants, environment-backed values, filters or hooks depending on the security and operational requirement.
