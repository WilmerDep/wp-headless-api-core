# Licensing enforcement policy — Contract v1

## Goal

Licensing must degrade public Headless capabilities in a controlled way without locking customers out of their WordPress data or editorial workflows.

The plugin must never delete, hide, rewrite, or make customer content inaccessible inside WordPress because a license expires.

The enforcement boundary is the public Headless capability layer, not the editorial/admin data layer.

## Principles

1. **Editorial continuity** — administrators may continue creating, editing, importing, ordering, exporting, and maintaining content after normal license expiration.
2. **Grace means full service** — `grace_period` remains operational. It only adds renewal notices.
3. **Public-value enforcement** — after the grace period, public content modules may stop exposing their Headless API payloads until renewal.
4. **Critical submissions survive expiration** — already-published Forms submissions and the Mail delivery path they depend on remain operational after normal expiration so real user requests are not silently lost.
5. **Revocation is different from expiration** — a revoked license is a security/administrative terminal state and may block public/transactional Headless capabilities while still preserving WordPress admin access to customer data.
6. **No ambiguous failure** — restricted REST responses must return an explicit licensing code instead of pretending the resource does not exist.
7. **No per-module licensing logic** — modules consume a central policy/gate API. They must not duplicate expiry, entitlement, or status rules.
8. **Fail closed for trust** — invalid signatures, unknown signing keys, instance/domain mismatch, or unavailable Ed25519 verification never count as a valid license.

## Capability classes

### `administrative`

Examples:

- WordPress editor screens;
- CPT CRUD;
- imports;
- ordering/reordering;
- settings that do not expose licensed public functionality;
- data export/recovery.

Policy: never blocked solely by ordinary license expiration.

### `public_content`

Examples:

- News REST output;
- Hero REST output;
- Directory REST output;
- Services REST output;
- Galleries REST output;
- Site Identity public Headless output.

Policy: available while the license is trusted and operational; restricted after grace expiration, suspension, revocation, invalid verification, or missing entitlement.

### `critical_transactional`

Examples:

- published Form submissions;
- Mail delivery required by those submissions.

Policy: continue during `expired` and ordinary `suspended` states to avoid dropping user requests. A true `revoked` or cryptographically untrusted state may block the Headless transaction path.

This exception applies to the transaction itself, not to every premium/editorial Forms capability.

### `premium_capability`

Examples:

- future premium templates;
- future advanced integrations;
- future optional licensed automation/features.

Policy: requires both an operational trusted license and the corresponding entitlement.

## State matrix

| License state | Administrative | Public content | Critical transactional | Premium capability | Admin UX |
|---|---|---|---|---|---|
| `active` | allow | allow with entitlement | allow | allow with entitlement | normal |
| `grace_period` | allow | allow with entitlement | allow | allow with entitlement | renewal warning |
| `expired` | allow | restrict | allow | restrict | strong renewal warning |
| `suspended` | allow | restrict | allow by default | restrict | suspension warning |
| `revoked` | allow for recovery/data management | block | block | block | revocation warning |
| `untrusted` | allow for recovery/data management | block | block | block | integrity/verification error |

`untrusted` includes missing/invalid token, invalid signature, unknown `kid`, domain mismatch, instance mismatch, and unavailable cryptographic verification when a licensed public capability is being evaluated.

## REST behavior for restricted public modules

Restricted modules should not return fake `404` responses. The Consumer must be able to distinguish licensing from missing content.

Recommended response body:

```json
{
  "code": "LICENSE_RENEWAL_REQUIRED",
  "message": "This Headless API license requires renewal.",
  "module": "news",
  "status": "expired"
}
```

The response must be non-cacheable (`no-store`). Exact HTTP status is intentionally deferred until the REST enforcement gate is implemented and tested consistently across modules.

## Entitlements

A valid license state does not imply access to every module.

A module is publicly available only when:

1. the policy allows its capability class for the current state; and
2. the token contains the module entitlement when that capability requires one.

Examples:

- `news` -> entitlement `news`;
- `hero` -> entitlement `hero`;
- `directory` -> entitlement `directory`;
- `services` -> entitlement `services`;
- `forms` -> entitlement `forms` for premium/editorial Forms capabilities, while critical published submissions follow the continuity exception above;
- `mail` -> entitlement `mail` for independently exposed/licensed Mail capabilities, while delivery required by an allowed critical Form submission follows the continuity exception.

## Implementation sequence

1. Contract v1 verification infrastructure. **Done.**
2. Central pure policy object + regression tests. **Next.**
3. Admin notice/status UX.
4. REST gate for one low-risk module (News) behind tests.
5. Runtime validation on a staging WordPress installation.
6. Expand the same gate to Hero, Directory, Services, Site Identity, and Galleries as applicable.
7. Integrate Forms/Mail using the critical-transaction continuity policy.
8. Only after plugin-side validation, test the real Consumer behavior and first production license flow.

## Non-goals for this phase

- no deletion or mutation of customer content;
- no blocking of WordPress login/admin/editor access;
- no module-wide hard shutdown at plugin bootstrap;
- no HOSGEDOPOL-specific licensing logic;
- no Consumer changes yet;
- no automatic merge into `develop` until runtime QA is complete.
