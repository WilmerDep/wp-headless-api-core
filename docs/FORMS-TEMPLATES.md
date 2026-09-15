# Forms Core + Mail Templates — v0.7.0 design

## Goal

Add a provider-owned, reusable forms system to Headless API Core without coupling the plugin to HOSGEDOPOL, Next.js, WPForms, a specific SMTP provider, or one fixed form shape.

Mail transport remains delegated to Mail Core (`wp_mail()` + configured SMTP). Forms Core owns form schema, server-side validation, submission policy and notification routing. Mail Templates owns reusable message rendering.

## Reference behavior extracted from the current HOSGEDOPOL Consumer

The first real migration targets are the existing Contact and Appointment forms.

### Contact

Current fields:

- `fullName`
- `email`
- `subject`
- `message`
- hidden honeypot `website`

Current behavior includes client + server validation, anti-URL / unsafe-content checks, honeypot handling, `Reply-To` from the submitter email, recipient routing, technical BCC and branded HTML email.

### Appointment

Current form is intentionally more complex and proves that the generic model must support more than a flat field list.

Observed capabilities:

- grouped sections / steps
- 2-column layout
- 3-column layout
- full-width fields
- text, email, radio, select, date, textarea, international phone, hidden and rating controls
- conditional specialty subtype / doctor fields
- dependent select data
- field-level validation and contextual helper text
- client-side saved profiles
- optional geolocation-assisted address fields
- honeypot anti-spam
- branded HTML email
- text fallback
- `Reply-To` from submitter email
- optional BCC
- generated XLSX attachment

The provider schema must represent the generic parts of these behaviors while allowing the Consumer to preserve specialized UX where appropriate.

## Architecture

```text
Consumer UI
   |
   | GET form schema
   v
Forms Core REST API
   |
   | POST submission
   v
Validation + anti-spam + rate policy
   |
   v
Notification routing
   |
   v
Mail Templates
   |
   v
Mail Core / wp_mail()
   |
   v
Configured SMTP transport
```

The Consumer never receives SMTP credentials.

## Data model

### Form

Editorial entity: `headless_form`.

Core attributes:

- `id`
- `slug`
- `title`
- `description`
- `status`
- `enabled`
- `schemaVersion`
- `sections[]`
- `fields[]`
- `notifications[]`
- `successMessage`
- `errorMessage`
- `submitLabel`
- `antiSpam`
- `rateLimit`

### Section

A form may contain zero or more sections.

```json
{
  "id": "personal",
  "eyebrow": "PASO 1",
  "title": "Datos generales",
  "description": "",
  "order": 0
}
```

Fields may reference a section by ID.

### Field

Internal layout uses a 12-column grid so the editor can expose simple presets while keeping the contract flexible.

Recommended width presets:

- `12` = one column / full width
- `6` = two columns
- `4` = three columns

Core field shape:

```json
{
  "id": "email",
  "name": "email",
  "type": "email",
  "label": "Correo electrónico",
  "placeholder": "correo@ejemplo.com",
  "required": true,
  "section": "personal",
  "order": 10,
  "width": 6,
  "hidden": false,
  "helper": "",
  "defaultValue": "",
  "autocomplete": "email",
  "options": [],
  "validation": {},
  "visibility": null,
  "ui": {}
}
```

Initial field types:

- `text`
- `email`
- `tel`
- `number`
- `textarea`
- `select`
- `radio`
- `checkbox`
- `date`
- `time`
- `hidden`
- `rating`

Extensible field types may be registered later through WordPress hooks.

### Field visibility / conditional rules

Forms Core must support conditional fields such as the current Appointment specialty subtype and doctor selectors.

Initial rule format:

```json
{
  "visibility": {
    "mode": "show",
    "all": [
      {
        "field": "service",
        "operator": "equals",
        "value": "cardiologia"
      }
    ]
  }
}
```

Initial operators:

- `equals`
- `not_equals`
- `in`
- `not_in`
- `not_empty`
- `empty`

Server validation must enforce the same conditional requirements as the public schema.

### Options

Select/radio/checkbox options may initially use inline manual options:

```json
{
  "options": [
    { "value": "morning", "label": "Mañana" },
    { "value": "afternoon", "label": "Tarde" }
  ]
}
```

The contract reserves `optionsSource` for registered provider-side sources. Arbitrary remote URLs are deliberately excluded from the first version.

Future examples may include `services`, `directory` or site-specific sources registered through hooks.

### Validation

Validation belongs to the Provider even when Consumers mirror it for immediate UX.

Supported initial rules should include:

- required
- min/max string length
- min/max number
- email
- regex/pattern from approved rule types
- allowed values
- date min/max / future / past policy
- word count
- safe free text

Raw executable validation code is never stored in the form definition.

## Notifications

A form may have multiple notifications.

```json
{
  "id": "admin",
  "enabled": true,
  "template": "institutional-data-table",
  "to": ["formularios@example.org"],
  "cc": [],
  "bcc": [],
  "replyToField": "email",
  "subject": "Nueva solicitud — {{field.fullName}}"
}
```

Recipients may be static addresses or approved field references where the notification type requires it.

Forms Core uses Mail Core for readiness and delivery. It does not read SMTP options directly.

## Mail Templates

Editorial entity: `headless_mail_template`.

A template is reusable across multiple forms and notifications.

Core attributes:

- `id`
- `slug`
- `name`
- `description`
- `status`
- `subject`
- `preheader`
- `mode`
- `visualSchema`
- `html`
- `textFallback`

### Template modes

`visual`

- canonical source is `visualSchema`
- Provider generates compatible email HTML
- visual editor uses controlled blocks

`html`

- canonical source is sanitized administrator-authored HTML
- intended for advanced/custom templates

The admin may preview generated HTML in both modes. Two independent editable sources must not silently diverge.

### Initial visual blocks

- logo
- eyebrow
- heading
- text
- submission fields table
- button
- separator
- footer
- custom HTML block (sanitized)

### Variables

Global variables:

- `{{site.name}}`
- `{{site.url}}`
- `{{form.name}}`
- `{{form.slug}}`
- `{{submission.id}}`
- `{{submission.date}}`

Field variables:

- `{{field.email}}`
- `{{field.fullName}}`
- arbitrary valid form field names

Aggregate variables:

- `{{form.fields}}` — rendered table/list of submitted visible fields in form order

Variable output is escaped by default. Explicit safe HTML placeholders are provider-owned only.

## Seed template from HOSGEDOPOL

The existing Appointment and Contact routes share a reusable institutional pattern:

- Arial/Helvetica safe font stack
- outer background `#f5f8fb`
- centered white card
- subtle border
- rounded container
- primary header `#06477f`
- white heading text
- logo on the right
- submission data presented as a two-column table
- label color around `#123f68`
- value color around `#344f67`
- row separators `#e8eef3`

v0.7.0 should ship a generic visual seed matching this structure without hardcoding HOSGEDOPOL branding. Site-specific logo, colors, eyebrow, heading and footer remain editable.

## REST contract

### `GET /wp-json/headless-core/v1/forms/{slug}`

Public read-only form schema.

Only published + enabled forms are public.

Response must include submission capability so Consumers do not need to understand SMTP:

```json
{
  "item": {
    "slug": "contact",
    "title": "Contacto",
    "schemaVersion": 1,
    "sections": [],
    "fields": [],
    "submission": {
      "enabled": true,
      "available": true
    }
  }
}
```

`submission.available` depends on Forms Core policy and Mail Core readiness when mail notifications are required.

### `POST /wp-json/headless-core/v1/forms/{slug}/submit`

Public submission endpoint.

Responsibilities:

- published/enabled form check
- payload size limit
- honeypot
- rate limiting
- conditional field evaluation
- server-side validation
- sanitization
- notification rendering
- `Reply-To` validation
- call `wp_mail()` through Mail Core
- generic success/error contract
- no sensitive mail transport details in responses

## Security boundary

Initial requirements:

- nonce is not required for anonymous headless forms, so abuse controls cannot depend on WordPress login state
- honeypot support
- per-form/IP rate policy with privacy-conscious hashing/storage
- strict payload size
- strict field allowlist from schema
- reject unknown fields by default, except provider-owned anti-spam fields
- sanitize by field type
- safe HTML escaping in templates
- no header injection
- validate all recipients
- attachment MIME/size allowlist before file fields are introduced
- do not expose SMTP credentials, internal transport errors or administrator-only template source through public endpoints

## Consumer responsibilities

Consumers own final visual rendering, animations, responsive behavior and specialized widgets.

The public schema provides layout hints, not framework-specific CSS classes.

Examples:

- `width: 12|6|4`
- `ui.variant`
- `ui.component`
- `autocomplete`

A Consumer may preserve a specialized component (international phone, geolocation, searchable select) while binding it to the same provider field contract.

Client-side saved profiles remain a Consumer capability. Forms Core may expose a future `ui.profileEligible` hint, but does not store personal profile data in WordPress merely to support autocomplete.

## Migration strategy

1. Build Forms Core + Mail Templates without changing the current HOSGEDOPOL Consumer.
2. Create the Contact form in WordPress and match the current field/validation contract.
3. Create the Appointment form and match sections, widths and conditional specialty behavior.
4. Import/create the institutional email template from the existing Consumer HTML.
5. Validate template preview/test email through Mail Core.
6. Migrate Contact submit path first.
7. Migrate Appointment submit path second.
8. Remove duplicate Consumer SMTP/Nodemailer only after both forms pass local + staging end-to-end gates.

## Deliberately deferred from first v0.7.0 implementation

- arbitrary remote option URLs
- unrestricted PHP/JS expressions in conditions
- payment fields
- signatures
- multi-page persistence on the server
- submission database/inbox unless explicitly approved
- unrestricted file uploads
- third-party CAPTCHA provider coupling
- workflow automation coupling (n8n/webhooks can be added through hooks or a later notifications extension)
