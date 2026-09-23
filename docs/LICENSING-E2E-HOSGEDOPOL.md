# Licensing E2E — HOSGEDOPOL

Validation date: 2026-09-23

This document records the production end-to-end validation of the Headless API Core licensing flow against the real HOSGEDOPOL WordPress installation.

## Environment

- WordPress Provider: `cms.hosgedopol.gob.do`
- Plugin candidate validated: `0.8.0`
- Licensing service: `https://pholiodev-licensing.ai.studio`
- Product key: `wp-headless-api-core`
- Plan: `institutional`
- Activation limit: `1`
- License secrets and full instance identifiers are intentionally omitted from this document.

## Validated flow

1. The production licensing service exposed the expected Ed25519 public key through `/api/v1/public-keys` with `kid=pholio-2026-01`.
2. HOSGEDOPOL was registered as a customer and a production license was issued for `cms.hosgedopol.gob.do`.
3. WordPress activated the license successfully and reported:
   - state `active`;
   - trusted token `yes`;
   - operational `yes`;
   - plan `institutional`.
4. The licensing console registered one active installation and the activation heartbeat/audit records appeared in Firestore.
5. Manual revalidation succeeded and produced a new `activation.refreshed` audit event.

## Public capability validation

With the license active, the following Provider routes returned their normal production contracts instead of a licensing restriction:

- `/wp-json/headless-core/v1/news`
- `/wp-json/headless-core/v1/hero`
- `/wp-json/headless-core/v1/directory`
- `/wp-json/headless-core/v1/services`
- `/wp-json/headless-core/v1/site`
- `/wp-json/headless-core/v1/forms`
- `/wp-json/headless-core/v1/mail/status`

The health route also confirmed plugin version `0.8.0` during the validation.

## Forms and Mail validation

A controlled `POST /forms/contacto/submit` request completed successfully before the configured form rate limit was exceeded. Subsequent repeated requests returned HTTP `429` with `rate_limited`, confirming that the anti-abuse limiter was active.

Mail capability status reported the transport as enabled, configured and ready before the suspension test.

## Suspension behavior

The production license was temporarily suspended from the licensing console.

Before WordPress revalidated, the existing signed token continued to authorize public content. This is expected because the local installation had not yet learned the new server lifecycle state.

After `Revalidar ahora`:

- WordPress reported state `suspended`;
- the token remained cryptographically trusted;
- operational state changed to `no`;
- public routes such as News, Services, Site Identity and Mail status returned HTTP `403` with `LICENSE_SUSPENDED`;
- WordPress administration remained available.

A Forms submission request while suspended reached the critical transactional pipeline and was stopped by the already-active form rate limiter instead of by licensing. This confirms the approved policy that trusted critical Forms/Mail transactions remain available during ordinary suspension while public headless capability is restricted.

## Reactivation behavior

The same license was reactivated from the licensing console and WordPress was revalidated again.

After revalidation:

- state returned to `active`;
- token trusted returned/stayed `yes`;
- operational returned to `yes`;
- `/news` resumed returning the normal production payload.

## Findings

The licensing lifecycle behaved as designed in production for activation, refresh, suspension and reactivation.

One UX issue was identified during the suspension test: the action-result notice originally exposed the wrapper code `licensing_http_error` even though the authoritative server response was `LICENSE_SUSPENDED`. The `0.8.1` follow-up improves administrator-facing lifecycle messages and prefers the specific server lifecycle code over the generic HTTP wrapper.

Revocation was deliberately not executed against the real HOSGEDOPOL license because revocation is irreversible.
