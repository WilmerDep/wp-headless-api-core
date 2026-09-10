# Security

## Baseline rules

- Sanitize all external input.
- Escape administrative output for its rendering context.
- Use WordPress nonces for state-changing admin actions.
- Apply capability checks to privileged operations.
- Declare a `permission_callback` for every REST route.
- Keep public content endpoints read-only.
- Do not expose secrets, password material or private user data.
- Do not add write endpoints without a concrete requirement.
- Prefer WordPress Core security primitives over custom equivalents.

## Public REST boundary

Health is intentionally public and read-only. News collection/detail are also public and read-only but expose only published, non-password-protected content.

No public endpoint exposes filesystem paths, database credentials, integration secrets or private WordPress user-account fields.

## News v0.2.2 outbound revalidation

Realtime editorial revalidation is an outbound Provider -> Consumer request, not a new public WordPress write endpoint.

Security rules:

- target URL and shared secret are configured outside Git;
- `HEADLESS_REVALIDATION_SECRET` must contain at least 32 characters;
- HTTPS is required by default;
- request body is signed with HMAC SHA-256;
- signature input is `<unix timestamp>.<exact raw JSON body>`;
- signature is sent as `X-Headless-Signature: sha256=<hex>`;
- timestamp is sent as `X-Headless-Timestamp`;
- Consumer must reject requests outside its anti-replay window (initial recommendation: 300 seconds);
- Consumer must use timing-safe signature comparison;
- secret and signature are never included in public payloads or diagnostic logs;
- HTTP redirects are not followed by the Provider revalidation client so the configured target must be exact;
- non-2xx/transport failures never roll back or prevent the WordPress editorial save.

The Consumer must validate authentication before parsing/trusting the lifecycle payload and should reject unsupported resources/events.

See `docs/REVALIDATION.md` for the full signing and lifecycle contract.

## Future private endpoints

Private or administrative routes must authenticate the caller and authorize the requested capability independently. Authentication alone is not authorization.
