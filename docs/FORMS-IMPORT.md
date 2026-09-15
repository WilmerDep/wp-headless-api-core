# Forms / Mail Templates package import

Forms Core can import reusable JSON packages from **Forms → Importar paquete**.

The importer is Provider-agnostic: institution-specific packages stay outside the distributable plugin and are uploaded explicitly by an administrator.

## Package contract

```json
{
  "schemaVersion": 1,
  "package": {
    "name": "Institutional forms",
    "version": "1.0.0"
  },
  "templates": [],
  "forms": []
}
```

A package may contain templates, forms, or both. Templates are always imported first so form notifications can reference template slugs.

## Workflow

1. Open **Forms → Importar paquete**.
2. Upload a `.json` file (maximum 2 MB).
3. Validate it. Preview never writes to WordPress.
4. Review entity type, title, slug, status, warnings, and errors.
5. Choose one mode:
   - **Solo crear**: existing slugs are left untouched.
   - **Crear o actualizar por slug**: existing matching entities are updated.
6. Import.

Packages with validation errors cannot be imported. Warnings are visible but do not block import.

## Security and consistency

- Import requires the `edit_posts` capability and a WordPress nonce.
- JSON is normalized through the same Forms Core schema used by the editor and REST contract.
- Supported WordPress editorial states are `draft`, `publish`, `pending`, and `private`.
- Template HTML passes through the same administrator-safe HTML allowlist used by Mail Templates.
- Form field casing such as `fullName` and `appointmentDate` is preserved.
- SMTP credentials are never part of a Forms package.
- Media is not duplicated automatically; institutional logos should be selected from the WordPress Media Library after import when needed.

## Portability

The runtime importer contains no HOSGEDOPOL-specific logic. Project-specific migration fixtures live under `project-docs/`, which the build pipeline excludes from the installable plugin ZIP.
