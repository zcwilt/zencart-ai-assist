# Security And Output

- Escape user-facing output with `zen_output_string_protected()` when rendering user-controlled content.
- Do not weaken the request sanitization rules in `includes/application_top.php` or admin sanitization without a specific reason.
- New admin fields that need relaxed sanitization must be explicitly whitelisted using the documented admin sanitization rules.
- Preserve bootstrap-level checks for suspicious query strings, parameter pollution, and crawler `buy_now` attempts.
- Prefer using existing Zen Cart helpers for URLs and output instead of hand-building HTML or query strings.

Practical defaults:

- treat docs as guidance, but inspect local code when behavior matters
- avoid direct edits to `includes/application_top.php` and `admin/includes/application_top.php`
- add new constants via `extra_configures` or plugin files instead of editing `defined_paths.php`
