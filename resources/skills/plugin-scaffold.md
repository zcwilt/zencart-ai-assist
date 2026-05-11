# Plugin Scaffold Workflow

Use this skill when creating or expanding an encapsulated Zen Cart plugin.

## Steps

- start with `manifest.php`, `filenames.php`, and `Installer/ScriptedInstaller.php`
- decide whether the feature belongs in `catalog/`, `admin/`, or both
- add `FILENAME_*` constants for new entrypoints before wiring links or admin pages
- keep catalog pages aligned across `header_php.php`, `main_template_vars.php`, language files, and templates
- add admin language and menu-definition files for every admin entrypoint
- install the plugin through Plugin Manager before trusting runtime behavior
