# Configuration

## v0.1.0

The Health-only bootstrap requires no installation-specific configuration.

## Future configuration principles

Institution-specific values must be configurable rather than hardcoded. This includes:

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
