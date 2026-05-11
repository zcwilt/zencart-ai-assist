# Admin Pages

- Prefer implementing new admin pages inside a plugin, not directly in core admin files.
- Register admin pages through installer hooks such as `zen_register_admin_page`.
- Admin language files belong under `admin/includes/languages/`.
- Keep admin-specific helpers and observers under `admin/includes/classes/`.
- Follow the same plugin versioning rules as catalog-side code.

Admin integration points:

- `admin/includes/application_top.php`
- `admin/includes/application_bootstrap.php`
- plugin `admin/includes/extra_configures/`
- plugin `admin/includes/extra_datafiles/`

When adding new admin UI, preserve existing Zen Cart admin structure and sanitization behavior.
