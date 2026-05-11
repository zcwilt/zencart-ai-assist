# Plugin Structure

- Use encapsulated plugin layout under `zc_plugins/<unique_key>/<version>/`.
- Keep `manifest.php` at the plugin root and return an array.
- Put install logic in `Installer/ScriptedInstaller.php` and make it idempotent.
- Add plugin filename constants in the plugin root `filenames.php`.
- Put catalog-side code under `catalog/` and admin-side code under `admin/`.
- Use `catalog/includes/extra_configures/` or `admin/includes/extra_configures/` for early constants instead of editing core path files.
- Prefer adding new functionality through plugin files, not direct edits to core bootstrap files.

Key files:

- `manifest.php`
- `Installer/ScriptedInstaller.php`
- `filenames.php`
- `catalog/includes/classes/`
- `catalog/includes/modules/pages/`
- `catalog/includes/templates/template_default/`
- `admin/includes/classes/`
- `admin/includes/languages/`
