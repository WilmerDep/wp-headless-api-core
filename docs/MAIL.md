# Mail / SMTP Core

Mail Core is the provider-owned delivery capability used by Headless API Core modules.

It configures WordPress' bundled PHPMailer through `wp_mail()` and keeps SMTP credentials inside WordPress. Consumer applications must never require or receive the SMTP password, username, host or sender identity in order to use this capability.

## Admin configuration

WordPress administrators configure SMTP at:

```text
Settings > Headless SMTP
```

Supported settings:

- enable/disable SMTP;
- host and port;
- `TLS`, `SSL` or no encryption;
- optional SMTP authentication;
- username/password;
- From Email / From Name;
- optional forced sender identity;
- test email delivery.

The password input is intentionally empty after save. When a password is already stored, leaving the field empty preserves it. The password is never rendered back into the admin HTML.

Settings may also be supplied by `wp-config.php` constants:

```text
HEADLESS_SMTP_ENABLED
HEADLESS_SMTP_HOST
HEADLESS_SMTP_PORT
HEADLESS_SMTP_ENCRYPTION
HEADLESS_SMTP_AUTH
HEADLESS_SMTP_USER
HEADLESS_SMTP_PASSWORD
HEADLESS_SMTP_FROM_EMAIL
HEADLESS_SMTP_FROM_NAME
HEADLESS_SMTP_FORCE_FROM
```

Constants override stored admin values.

## Internal capability API

Other plugin modules must depend on `Mail_Settings` capability methods rather than reading the SMTP option directly:

```php
$mail = Mail_Module::settings();

$mail->is_enabled();
$mail->is_configured();
$mail->is_ready();
$mail->get_last_test();
$mail->public_status();
```

Semantics:

- `is_enabled()` — SMTP transport is enabled.
- `is_configured()` — minimum provider-agnostic settings required by the selected authentication/sender mode are present.
- `is_ready()` — both enabled and configured. It does not perform a network request.
- `get_last_test()` — last persisted safe `wp_mail()` test result, if one exists.

Forms Core and future notification modules should use `is_ready()` before depending on mail delivery.

## Public Consumer status endpoint

### `GET /wp-json/headless-core/v1/mail/status`

Public, read-only, no-store endpoint. It exposes capability state only.

Example:

```json
{
  "schemaVersion": 1,
  "transport": "smtp",
  "enabled": true,
  "configured": true,
  "ready": true,
  "status": "ready",
  "lastTest": {
    "success": true,
    "testedAt": "2026-09-15T02:20:00+00:00"
  },
  "capabilities": {
    "send": true
  }
}
```

`status` values:

- `disabled` — transport is intentionally disabled;
- `incomplete` — enabled but missing required configuration;
- `ready` — enabled and minimally configured.

`lastTest` is `null` until a real admin test is attempted. A successful test means `wp_mail()` accepted the SMTP delivery request at that time. It is historical evidence, not a guarantee that the remote transport is currently online.

The endpoint emits:

```text
Cache-Control: no-store, no-cache, must-revalidate, max-age=0
Pragma: no-cache
Expires: 0
```

## Security boundary

The public contract deliberately never exposes:

- SMTP host;
- SMTP port;
- username;
- password;
- From Email;
- From Name;
- test recipient;
- raw PHPMailer/SMTP errors.

A Consumer should only use the status endpoint for capability/health decisions. It must not recreate SMTP transport from the status response.

## Consumer architecture

Preferred architecture:

```text
Consumer UI
    -> Headless API Core Forms endpoint
    -> validation / sanitization
    -> template rendering
    -> wp_mail()
    -> Mail Core
    -> configured SMTP provider
```

Consumer frameworks such as Next.js may read `/mail/status` for diagnostics or capability checks, but SMTP credentials remain server-side in WordPress.
