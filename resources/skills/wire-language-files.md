# Wire Language Files

Use this skill when a new encapsulated plugin storefront page, admin page, or installer flow needs language definitions in the expected Zen Cart locations.

## Workflow

- put storefront page definitions under `catalog/includes/languages/english/`
- put admin page definitions under `admin/includes/languages/english/`
- put admin menu labels under `admin/includes/languages/english/extra_definitions/`
- put installer strings under `Installer/languages/english/`

## Validation

- confirm the changed area has a corresponding language file
- confirm admin menu pages also have an `extra_definitions` file
