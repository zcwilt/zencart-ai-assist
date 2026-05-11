# Plugin Doctor Workflow

Use this skill when an encapsulated plugin is not loading, is partially wired, or behaves differently than the docs suggest.

## Steps

- inspect `manifest.php` for baseline fields and version identity
- inspect `Installer/` for install, uninstall, and upgrade hooks
- verify `filenames.php` constants match the intended pages
- check catalog pages for `header_php.php`, language files, and templates
- check admin pages for language files and `extra_definitions` menu wiring
- review observers, auto-loaders, and extra configure/data files when bootstrap behavior is involved
- confirm installed-plugin state from Plugin Manager before assuming encapsulated plugin bootstrap discovery is broken
