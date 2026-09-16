# Forms Editorial UX

The Forms admin is designed for everyday editors first. Internal schema keys remain stable for API compatibility, but the WordPress UI should describe what a setting does in plain language.

## Contextual advanced rules

Advanced controls are shown according to the selected field type. This is a presentation rule only: hiding an irrelevant control must never delete or rewrite saved schema data.

- Text, email, telephone and long text show character limits when applicable.
- Long text may also show word limits.
- Select, radio and checkbox fields show available options.
- Number and rating fields show minimum and maximum numeric values.
- Date fields show future/past date restrictions.
- Text and telephone fields may expose an approved format rule.
- Conditional visibility remains available as “Mostrar u ocultar según otra respuesta”.

Existing imported forms keep their pre-adjusted validation and option values. This is especially important for the HOSGEDOPOL Appointment compatibility fixture, which exercises date restrictions, rating ranges, word limits, choice options and specialized Consumer hints.

## Technical settings

Fields such as the internal name, browser autocomplete, Consumer component and Consumer variant are grouped under **Configuración técnica opcional**. Editors can reach them when integration work requires it, but they are not mixed with routine editorial controls.

## Human labels

Examples:

- `select` → “Lista desplegable”
- `radio` → “Una sola opción”
- `checkbox` → “Casillas de selección”
- `pattern` → “Formato permitido”
- `digits` → “Solo números”
- conditional visibility → “Mostrar u ocultar según otra respuesta”

The underlying values (`select`, `radio`, `checkbox`, `pattern`, etc.) do not change.
