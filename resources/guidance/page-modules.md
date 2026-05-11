# Page Modules

- Storefront pages are built from `includes/modules/pages/<page>/`.
- The usual starting point is `header_php.php` for page logic.
- Use `main_template_vars.php` when the template needs structured variables.
- Page-specific JavaScript can live beside the page module as `jscript_*.php`, `jscript_*.js`, or `on_load_main.js`.
- Templates usually live under `includes/templates/<template>/templates/`.
- Register new page names through `FILENAME_*` constants instead of hardcoding route names.

Typical flow:

- `index.php` includes `includes/application_top.php`
- page modules for the current page are loaded
- template variables are prepared
- the page template renders
- `includes/application_bottom.php` runs cleanup
