# Admin Page Workflow

Use this skill when adding or reviewing a Zen Cart admin page in an encapsulated plugin.

## Steps

- create the admin entrypoint under `admin/`
- add `admin/includes/languages/english/lang.<page>.php`
- add `admin/includes/languages/english/extra_definitions/lang.<page>_menu.php`
- register the admin page from the installer if it should appear in the admin menu
- keep admin-side extra configures, classes, and observers inside the encapsulated plugin version directory
