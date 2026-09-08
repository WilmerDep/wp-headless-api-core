# Installation

## Development installation

1. Obtain the plugin from the repository development branch or a generated ZIP.
2. Ensure the plugin directory is named `wp-headless-api-core`.
3. Place it under `wp-content/plugins/` or upload the ZIP through WordPress administration.
4. Activate **Headless API Core**.
5. Verify that WordPress reports no PHP warnings or fatal errors.
6. Open `/wp-json/headless-core/v1/health` on the CMS host.
7. Confirm HTTP 200 and the documented JSON response.

## Validation checklist for v0.1.0

- Plugin activates successfully.
- Plugin deactivates successfully.
- WordPress admin remains usable.
- No PHP warning/fatal is produced by the plugin.
- REST namespace is registered.
- Health returns 200.
- Health response matches `docs/REST-API.md`.

## Deployment rule

Do not develop through the WordPress plugin editor. Source of truth is GitHub. Validate builds on a staging CMS before considering a stable release.
