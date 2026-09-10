# Architecture

## Purpose

Headless API Core is a provider plugin that turns WordPress into a stable content API for decoupled frontends. Runtime code must remain generic; institution-specific integration data belongs in configuration and integration documentation.

## Runtime flow

```text
WordPress
  -> wp-headless-api-core.php
  -> HeadlessApiCore\Core\Plugin
  -> Health REST controller
  -> News module
       -> News_Controller / News_Serializer
       -> News_Revalidation
            -> Revalidation_Client
            -> signed outbound lifecycle event
```

Public REST namespace:

```text
/wp-json/headless-core/v1/
```

## Contract boundary

Consumers may depend on documented REST and integration contracts only. They must not depend on PHP classes, WordPress database tables, plugin directory structure or internal implementation details.

A provider refactor is non-breaking when documented contracts remain compatible. A breaking contract change requires explicit documentation, versioning and coordinated Consumer migration.

## Module boundaries

- `includes/Core/`: lifecycle and plugin orchestration.
- `includes/Rest/`: shared REST infrastructure and cross-cutting controllers.
- `includes/Revalidation/`: generic signed outbound revalidation transport.
- `includes/Admin/`: generic administrative UI when a concrete module requires it.
- `includes/Settings/`: global configurable institutional data when implemented.
- `includes/Preview/`: configurable frontend preview integration when implemented.
- `includes/Security/`: shared security helpers if/when they become necessary.
- `modules/`: independent content-domain modules such as News, Hero, Services, Directory and Galleries.

Directories are added when their first real responsibility is implemented; empty architecture is not created merely for appearance.

## News v0.2.2 lifecycle boundary

News remains the only content module active in the current release gate.

The public read path is:

```text
WordPress post
  -> News_Controller
  -> News_Serializer
  -> /news or /news/{slug}
```

The cache-freshness path is intentionally separate:

```text
WordPress editorial hook
  -> News_Revalidation
  -> aggregate one event per post/request
  -> shutdown
  -> Revalidation_Client
  -> HMAC-signed JSON POST
  -> configured Consumer endpoint
```

`News_Revalidation` owns WordPress-specific lifecycle interpretation. `Revalidation_Client` owns generic delivery/signing and contains no News or Next.js assumptions. A Consumer owns its own framework-specific actions such as tag/path invalidation.

This separation allows future modules to reuse the same authenticated transport while defining their own resource events.

## Failure semantics

Revalidation is an acceleration channel, not the source of truth. A failed webhook must not roll back or prevent a WordPress save/publication. Public REST visibility continues to be determined by WordPress state, and Consumers should retain a short TTL fallback to converge if delivery is temporarily unavailable.

## Design rules

1. Prefer WordPress Core APIs.
2. Keep public content endpoints read-only.
3. Require explicit permission callbacks on every REST route.
4. Do not expose secrets or private user data.
5. Do not hardcode consumer domains, IDs or branding into runtime code.
6. Keep integration targets/secrets external to the repository.
7. Sign outbound lifecycle events and require Consumer-side freshness/replay validation.
8. Add modules only when a real consumer requires them.
9. Freeze and document contracts before consumers depend on them.
10. Do not couple Provider runtime to Next.js/React cache APIs.
