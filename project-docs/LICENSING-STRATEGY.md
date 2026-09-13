# Internal Licensing Strategy — Headless API Core

> Internal project documentation. This file must not be packaged inside the distributable WordPress plugin ZIP.

## Purpose

Plan an annual commercial licensing layer for institutional deployments of Headless API Core without risking the public website or destroying editorial data when a license expires.

This is a future implementation phase. It must not be activated until the plugin feature set is stable and the licensing backend is ready.

## Product principle

The license protects **write / management capabilities**, not the public availability of already-published institutional content.

If a license expires:

- existing public REST endpoints continue serving already-published content;
- existing frontend sites continue operating;
- administrators may still sign in, inspect content, read settings, export data and review the license state;
- mutation capabilities become restricted until renewal;
- no content is deleted, hidden, corrupted or remotely modified;
- no arbitrary remote-code execution or hidden kill switch is allowed.

This produces a **read-only grace-safe mode** rather than a destructive shutdown.

## Proposed states

### `active`

Normal operation. All licensed features are available.

### `grace`

The entitlement has reached its nominal renewal date but remains within a configured grace window. The plugin remains writable while showing a clear renewal warning.

Recommended default grace window: 14–30 days for institutional deployments.

### `expired`

Public read behavior remains available, but protected write operations are blocked.

Expected restrictions include:

- creating new Provider-managed records;
- publishing or changing publication state;
- editing Provider-managed content/meta;
- bulk imports;
- drag-and-drop ordering writes;
- taxonomy/group creation or mutation;
- settings mutations tied to licensed modules;
- future premium features.

Allowed actions should include:

- viewing existing content;
- reading API responses;
- exporting/backup operations;
- viewing diagnostics;
- viewing license information;
- entering or refreshing a license;
- deactivating/uninstalling the plugin.

### `invalid` / `revoked`

Reserved for clearly invalid entitlements. Do not use as a general remote-control mechanism. Behavior should still preserve public read access and data integrity.

## License identity

Suggested installation identity:

- `product`: `wp-headless-api-core`;
- `license_key`: customer-facing license identifier;
- `installation_id`: random UUID generated once per WordPress installation;
- normalized `home_url` or site identifier;
- edition/features when applicable;
- `issued_at`;
- `valid_until`;
- `grace_until`;
- entitlement/version metadata.

Avoid using WordPress user IDs or private editorial content as licensing identifiers.

## Cryptographic model

Prefer **asymmetric signatures**.

The licensing service keeps the private signing key. The plugin ships only the public verification key.

Recommended options:

- Ed25519 signed entitlement token; or
- another modern asymmetric signature available reliably in the supported PHP environment.

Do not embed a reusable server-side HMAC secret in the distributed plugin because it can be extracted from the PHP source.

The cached entitlement should be verifiable locally so temporary licensing-server outages do not break the institution.

## Renewal modes

### Automatic renewal / refresh

A scheduled WordPress task can periodically contact the licensing service over HTTPS.

Suggested cadence:

- normal active license: once every 24 hours;
- near expiration: more frequent but bounded refresh;
- failures: exponential backoff;
- never block a normal public request while waiting for the license server.

If the customer subscription/annual maintenance is current, the server returns a newly signed entitlement with a later `valid_until`.

### Manual renewal

Provide at least two manual paths:

1. **Online refresh** — administrator enters/keeps the license key and clicks `Actualizar licencia`.
2. **Offline signed token** — owner generates a signed entitlement from the licensing dashboard and the administrator pastes/uploads it into WordPress.

The offline path is important for government/institutional environments with restrictive outbound networking.

## Proposed wp-admin UX

Future top-level or Settings screen:

`Headless API Core → Licencia`

Show:

- status badge (`Activa`, `Período de gracia`, `Vencida`);
- licensed organization/site label;
- expiration date;
- grace date when applicable;
- last successful verification;
- `Actualizar licencia` action;
- masked license key;
- safe diagnostic messages.

When expired, module screens should remain visible but write controls should be disabled with a clear explanation and renewal action. Avoid deceptive UI and avoid letting the user fill a large form only to fail at the final save.

## Enforcement architecture

Do not scatter expiration checks manually across every module.

Create a central future licensing service, for example:

- `includes/Licensing/License_Manager.php`
- `includes/Licensing/License_Entitlement.php`
- `includes/Licensing/License_Client.php`
- `includes/Licensing/License_Gate.php`

The gate should expose simple decisions such as:

- `can_read()`
- `can_mutate()`
- `can_use_feature( $feature )`
- `status()`

Each mutation surface must use the same gate:

- post save handlers;
- AJAX ordering endpoints;
- imports;
- REST write endpoints if any are added in the future;
- settings writes;
- taxonomy/group writes.

Public read-only REST routes should not depend on a live licensing-server call.

## Fail-safe behavior

### Licensing server unavailable

Use the last locally cached, cryptographically verified entitlement.

If it remains inside `valid_until` or `grace_until`, continue normally.

Do not convert a temporary network failure into an immediate expired lock.

### Clock / replay considerations

Store signed dates and compare against WordPress/server time. Consider a small clock-skew tolerance. License refresh responses should include signed issuance and expiration fields.

### Data safety

Expiration must never:

- delete posts/media/meta;
- remove groups;
- modify publication states automatically;
- stop the public REST API from serving already-valid content;
- inject remote code;
- prevent backup/export/deactivation.

## Licensing service / Pholio integration

The future Pholio licensing backend should own:

- customer/institution record;
- license creation;
- annual renewal status;
- site activation limits;
- entitlement signing;
- manual renewal/extension;
- optional automatic subscription integration;
- audit history.

Manual owner override should be possible from the licensing backend by issuing a new signed entitlement. The plugin itself should not contain a hidden master password.

## Suggested API shape

Example activation/refresh request (conceptual only):

```json
{
  "product": "wp-headless-api-core",
  "licenseKey": "...",
  "installationId": "uuid",
  "site": "https://example.gob.do",
  "pluginVersion": "0.x.y"
}
```

Example response:

```json
{
  "entitlement": "<signed-token>"
}
```

The signed token may contain:

```json
{
  "product": "wp-headless-api-core",
  "installationId": "uuid",
  "site": "https://example.gob.do",
  "status": "active",
  "features": ["core"],
  "issuedAt": "2026-09-12T00:00:00Z",
  "validUntil": "2027-09-12T23:59:59Z",
  "graceUntil": "2027-10-12T23:59:59Z"
}
```

Exact token format and cryptographic implementation remain a later design decision.

## Public-institution deployment considerations

Licensing should be backed by the commercial/support agreement, not relied on as the only business protection. Government environments can change personnel, networking or procurement processes, so the technical design should preserve continuity of the public website while clearly enforcing renewal for continued editorial/management use.

Before production rollout, verify contractual, procurement and open-source licensing obligations for each deployment model.

## Implementation gate

Do not implement licensing while Directory/Services/Galleries/Settings are still changing quickly.

Recommended sequence:

1. finish Provider feature roadmap;
2. stabilize APIs and admin UX;
3. freeze mutation surfaces;
4. build licensing backend / Pholio service;
5. implement central License Manager/Gate;
6. add integration tests for active/grace/expired/network-offline states;
7. pilot on a non-production installation;
8. document renewal operations;
9. deploy institution by institution.

## Required QA when implemented

- active license allows all existing writes;
- grace state warns but remains operational;
- expired state blocks every Provider mutation path consistently;
- public REST keeps serving published data while expired;
- frontend remains operational;
- manual online renewal restores writes immediately;
- offline signed renewal restores writes immediately;
- automatic refresh extends entitlement when backend says active;
- licensing backend outage does not cause premature lock;
- no license secret/private signing key ships in the plugin;
- News/Hero/Directory/Services regressions stay green;
- uninstall/deactivation/export remain available.
