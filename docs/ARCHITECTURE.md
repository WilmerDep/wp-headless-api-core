# Architecture

## Purpose

Headless API Core is a provider plugin that turns WordPress into a stable content API for decoupled frontends. Runtime code must remain generic; institution-specific integration data belongs in configuration and integration documentation.

## v0.1.0 runtime flow

```text
WordPress
  -> wp-headless-api-core.php
  -> HeadlessApiCore\Core\Plugin
  -> HeadlessApiCore\Rest\Health_Controller
  -> /wp-json/headless-core/v1/health
```

## Contract boundary

Consumers may depend on the documented REST contract only. They must not depend on PHP classes, WordPress database tables, plugin directory structure or internal implementation details.

A provider refactor is non-breaking when the documented REST contract remains compatible. A breaking contract change requires explicit documentation and versioning.

## Planned module boundaries

- `includes/Core/`: lifecycle and plugin orchestration.
- `includes/Rest/`: shared REST infrastructure and cross-cutting controllers.
- `includes/Admin/`: generic administrative UI when needed.
- `includes/Settings/`: global configurable institutional data.
- `includes/Preview/`: configurable frontend preview integration.
- `includes/Revalidation/`: secure frontend cache invalidation events.
- `includes/Security/`: shared security helpers if they become necessary.
- `modules/`: independent content-domain modules such as News, Hero, Services, Directory and Galleries.

Directories are added when their first real responsibility is implemented; empty architecture is not created merely for appearance.

## Design rules

1. Prefer WordPress Core APIs.
2. Keep public content endpoints read-only.
3. Require explicit permission callbacks on every REST route.
4. Do not expose secrets or private user data.
5. Do not hardcode consumer domains, IDs or branding into runtime code.
6. Add modules only when a real consumer requires them.
7. Freeze and document contracts before consumers depend on them.
