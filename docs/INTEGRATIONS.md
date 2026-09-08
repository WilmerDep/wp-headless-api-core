# Integrations

This document records known consumers of Headless API Core. Integration records are documentation, not runtime hardcoding.

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

- v0.1.0 Health only.

**Planned first content integration**

- native WordPress posts -> Headless API Core News contract -> Next.js CMS adapter -> existing News UI.

**Minimum compatible plugin version**

- To be defined after the News pipeline is validated end-to-end.

## Cross-repository rule

When a REST contract used by a consumer changes, review and update documentation in both provider and consumer repositories. The consumer must never depend on internal PHP classes, WordPress table structure or plugin implementation details.
