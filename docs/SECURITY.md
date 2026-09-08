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

## v0.1.0 Health endpoint

Health is intentionally public and read-only. It exposes only:

- a boolean service state;
- the generic service name;
- the plugin contract version.

It does not expose filesystem paths, server versions, database data, user information, environment values or secrets.

## Future private endpoints

Private or administrative routes must authenticate the caller and authorize the requested capability independently. Authentication alone is not authorization.
