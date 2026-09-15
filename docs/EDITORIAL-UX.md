# Editorial UX principle

Headless API Core can keep technical contracts internally without forcing editors to understand implementation jargon.

## Rule

Admin-facing labels should describe **what a person is changing and what effect it has**. Internal keys, REST contracts, metadata names and developer terminology stay documented in code and repository docs, but they should not be the primary language of everyday editorial screens.

Examples in Forms Core:

| Internal/developer term | Editorial label |
| --- | --- |
| eyebrow | Subtítulo |
| schema | Versión de estructura (hidden from normal editing when read-only) |
| name | Nombre interno |
| autocomplete | Autocompletar en navegador |
| placeholder | Texto de ejemplo |
| consumer component | Componente especial del sitio |
| Reply-To | Responder al correo de |
| BCC | Copia oculta |
| honeypot | Filtro antispam invisible |
| rate limit | Limitar envíos repetidos |
| payload | Tamaño máximo del envío |
| safeText | Bloquear contenido riesgoso |

## Progressive disclosure

Technical settings that an everyday editor normally should not change are either hidden when they are informational/read-only, explained in plain language, or placed under advanced options. The underlying values remain unchanged for API compatibility.

## Compatibility

This is an editorial presentation layer only. It must not rename REST fields, post meta keys, notification tokens, public form field names or schema properties. Tests must protect that separation.
