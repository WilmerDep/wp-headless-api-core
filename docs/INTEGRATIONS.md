# Integrations

This document records known consumers of Headless API Core. Integration records are documentation, not runtime hardcoding.

## Workstream ownership

`wp-headless-api-core` is the **Provider** and is developed/versioned independently from its consumers.

For the current HOSGEDOPOL project:

- this repository owns the WordPress plugin, REST contracts, provider serialization, provider security, provider validation and plugin releases;
- `WilmerDep/hosgedopol-web` is a separate Consumer application and has its own development workstream;
- implementation work inside the Consumer is not considered plugin/backend implementation for this repository;
- cross-repository changes are allowed when they are required to prove or wire a Provider contract end-to-end, but they must be documented in both repositories;
- after a cross-repository validation is complete, normal feature development returns to the repository that owns that responsibility.

This distinction is important so that Provider evolution does not become coupled to a specific frontend or application repository.

## HOSGEDOPOL

**Relationship**

- PROVIDER: `WilmerDep/wp-headless-api-core`
- CONSUMER: `WilmerDep/hosgedopol-web`

**CMS**

- `https://cms.hosgedopol.gob.do`

**Frontend staging**

- `https://dev.hosgedopol.gob.do`

**Frontend production target**

- `https://hosgedopol.gob.do`

**REST namespace**

- `headless-core/v1`

**Current provider contract**

- v0.1.0: Health baseline.
- v0.2.0: News collection/detail contract merged into `develop`.

**News consumer integration status**

The first real Consumer wiring has been implemented in the separate `hosgedopol-web` repository on branch:

```text
feature/news-headless-consumer
```

Consumer PR:

```text
WilmerDep/hosgedopol-web#2
feat: connect public News to Headless API Core
```

The Consumer integration currently covers:

- `/noticias` using `GET /headless-core/v1/news`;
- `/noticias/[slug]` using `GET /headless-core/v1/news/{slug}`;
- Home News using the latest Provider items;
- `/buscar` including Provider-backed News results;
- desktop/mobile header autocomplete through an internal Consumer search route;
- a temporary local fallback for one manually created News article until it is migrated into WordPress;
- deduplication by slug so the Provider version wins automatically after that migration.

No HOSGEDOPOL-specific Consumer logic is added to the plugin runtime because of this integration.

**End-to-end validation checkpoint**

Consumer workflow `Frontend Build #30` (`run 34424685876`) completed successfully and included:

1. live Provider contract smoke test against the deployed WordPress CMS;
2. Next.js production build;
3. runtime smoke test of the built Consumer routes.

The live Provider smoke verified Health, News collection, News detail and the expected unknown-slug 404 contract. The runtime Consumer smoke then verified the technical path:

```text
WordPress CMS
  -> Headless API Core
  -> Consumer adapter
  -> Next.js production build
  -> public News/search routes
```

Visual QA and staging approval remain Consumer-side release gates and do not change the Provider contract.

**Temporary local-only News migration**

One News article still exists only in the Consumer source during transition:

```text
visita-del-director-al-hospital-general-docente-de-la-policia-nacional
```

It must be migrated into the WordPress CMS before the Consumer removes its local fallback. The preferred transport under evaluation is Zippy from a WordPress origin; WXR remains an alternative. This migration concern must not become permanent HOSGEDOPOL-specific runtime code in Headless API Core.

**Minimum compatible plugin version**

- News integration requires the v0.2.0 News contract or later compatible version.

## Cross-repository rule

When a REST contract used by a consumer changes, review and update documentation in both provider and consumer repositories. The consumer must never depend on internal PHP classes, WordPress table structure or plugin implementation details.

When this repository performs a necessary cross-repository validation or wiring step, record at minimum:

- Provider version/branch;
- Consumer repository/branch or PR;
- contract endpoints involved;
- automated validation evidence;
- remaining release gates;
- whether any temporary compatibility/fallback behavior exists.
