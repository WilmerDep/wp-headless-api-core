# Modules

## v0.1.0

No content-domain module is active yet. v0.1.0 contains only the Core bootstrap and Health REST controller.

## Planned sequence

### News — first pilot

Wrap native WordPress `post` content in a clean Headless API Core contract. Do not create a News CPT unless a future requirement proves native posts insufficient.

Minimum planned capabilities:

- collection endpoint;
- single-by-slug endpoint;
- public published content only;
- pagination and reasonable limits;
- ID, slug, title, excerpt and content;
- published/modified dates;
- featured image metadata and alt text;
- categories;
- SEO metadata when justified and mapped;
- deterministic 404 behavior.

The definitive News schema must be documented before it is treated as frozen.

### Later modules

Hero, Services, Directory, Galleries and Settings are intentionally deferred until the News pipeline is validated end-to-end.

## Module admission rule

For every new requirement decide whether it belongs to:

A. the generic Core;
B. generic configuration;
C. an optional reusable module; or
D. consumer-specific code outside this repository.
