# Create Storefront Page

Use this skill when a task needs a new storefront page inside an encapsulated plugin or when an encapsulated plugin storefront page is only partially wired.

## Workflow

- add the `FILENAME_*` constant before linking to the page
- create `header_php.php` under `catalog/includes/modules/pages/<page>/`
- add the matching storefront language file
- add the matching template file
- protect user-supplied output with `zen_output_string_protected()`

## Validation

- confirm the page has a `header_php.php`
- confirm the language file exists
- confirm the template file exists
