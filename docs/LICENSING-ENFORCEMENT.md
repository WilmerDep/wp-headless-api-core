# Licensing enforcement policy — Contract v1

## Goal

Licensing must degrade public Headless capabilities in a controlled way without locking customers out of their WordPress data or editorial workflows.

The plugin must never delete, hide, rewrite, or make customer content inaccessible inside WordPress because a license expires.

The enforcement boundary is the public Headless capability layer, not the editorial/admin data layer.

## Principles

1. **Editorial continuity** — administrators may continue creating, editing, importing, ordering, exporting, and maintaining content after normal license expiration.
2. **Grace means full service** — `grace_period` remains operational and adds renewal notices.
3. **Public-value enforcement** — after the grace period, public content modules stop exposing licensed Headless payloads until renewal/revalidation.
4. **Critical submissions survive ordinary license interruption** — already-published Forms submissions and the Mail delivery path they depend on remain operational during normal expiration, suspension, and offline-tolerance exhaustion so real user requests are not silently lost.
5. **Revocation is different from expiration** — a revoked license is a terminal security/administrative state and blocks licensed public/transactional Headless capabilities while preserving WordPress admin access to customer data.
6. **No ambiguous failure** — restricted REST responses return an explicit licensing code instead of pretending the resource does not exist.
7. **Central policy** — modules consume `License_Gate` / `License_Policy`; they do not implement independent expiry or entitlement rules.
8. **Fail closed for trust** — invalid signatures, unknown signing keys, instance/domain mismatch, or unavailable Ed25519 verification never count as a valid license.

## Capability classes

### `administrative`

Examples: WordPress editor screens, CPT CRUD, imports, ordering/reordering, settings, and data recovery/export.

Policy: never blocked solely by ordinary license expiration.

### `public_content`

Current v0.8.0 examples:

- News REST output (`news`);
- Hero REST output (`hero`);
- Directory REST output (`directory`);
- Services REST output (`services`);
- Site Identity REST output (`site-identity`);
- Forms discovery/schema output (`forms`);
- Mail public status output (`mail`).

Policy: available while the signed state and entitlement allow it; restricted after expiration, suspension, revocation, offline tolerance exhaustion, invalid verification, or missing entitlement according to the central state matrix.

### `critical_transactional`

Current v0.8.0 example: `POST /forms/{slug}/submit` and its required Mail delivery path.

Policy: the transaction requires both `forms` and `mail` entitlements, but continues through `expired`, ordinary `suspended`, and `offline_tolerance_exceeded` states. `revoked`, `untrusted`, or a missing required entitlement blocks the transaction.

This exception applies to the transaction itself, not to every Forms/Mail capability.

### `premium_capability`

Reserved for optional premium templates, integrations, automation, or future licensed features. It requires an allowed state plus its entitlement.

## State matrix

| License state | Administrative | Public content | Critical transactional | Premium capability | Admin UX |
|---|---|---|---|---|---|
| `active` | allow | allow with entitlement | allow with entitlement | allow with entitlement | normal |
| `grace_period` | allow | allow with entitlement | allow with entitlement | allow with entitlement | renewal warning |
| `offline_tolerance_exceeded` | allow | restrict until revalidation | allow with entitlement | restrict | revalidation warning |
| `expired` | allow | restrict | allow with entitlement | restrict | strong renewal warning |
| `suspended` | allow | restrict | allow with entitlement | restrict | suspension warning |
| `revoked` | allow for recovery/data management | block | block | block | revocation error |
| `untrusted` | allow for recovery/data management | block | block | block | integrity/verification error |

`untrusted` includes missing/invalid token, invalid signature, unknown `kid`, domain mismatch, instance mismatch, and unavailable cryptographic verification.

## REST behavior

Restricted licensed public endpoints return HTTP `403`, an explicit licensing code, module identifier, effective status, and non-cacheable headers.

Example:

```json
{
  "code": "LICENSE_RENEWAL_REQUIRED",
  "message": "This Headless API license requires renewal.",
  "module": "news",
  "status": "expired"
}
```

Offline-tolerance exhaustion uses `LICENSE_REVALIDATION_REQUIRED`. Missing module access uses `ENTITLEMENT_REQUIRED`.

## Entitlements in v0.8.0

The product/license configuration used for the full advanced plugin can grant these implemented capability slugs:

- `news`
- `hero`
- `directory`
- `services`
- `site-identity`
- `forms`
- `mail`

A valid license state does not imply access to every module. Each licensed boundary still requires its corresponding entitlement.

## WordPress license administration

`Settings → Headless API Core` exposes the local license state, trust/operational status, domain, installation ID, plan/expiry when available, activation, revalidation, and deactivation.

The plaintext license key is used only for activation and is not persisted by the plugin. The stored credential is the signed token plus non-secret state metadata.

## v0.8.0 implementation status

1. Contract v1 verification and Ed25519 trust boundary — **implemented**.
2. Central policy/gate and lifecycle regression coverage — **implemented**.
3. WordPress admin notices and license management screen — **implemented**.
4. News, Hero, Directory public enforcement — **implemented**.
5. Services and Site Identity public enforcement — **implemented**.
6. Forms discovery + critical submission policy — **implemented**.
7. Mail public status + Forms delivery continuity — **implemented**.
8. Advanced 0.7.15 modules preserved while integrating licensing — **implemented on `feature/licensing-v0.8.0`**.
9. Runtime installation/activation E2E on HOSGEDOPOL — **pending after Quality Gate and production license preparation**.

## Non-goals for this phase

- no deletion or mutation of customer content;
- no blocking of WordPress login/admin/editor access;
- no module-wide hard shutdown at plugin bootstrap;
- no HOSGEDOPOL-specific logic inside the reusable plugin;
- no Consumer/frontend changes yet;
- no merge into the long-lived integration branch until runtime QA is complete.
