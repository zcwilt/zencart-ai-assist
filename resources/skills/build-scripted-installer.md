# Build Scripted Installer

Use this skill when an encapsulated plugin needs install, upgrade, uninstall, or schema behavior that should stay inside the normal Plugin Manager lifecycle.

## Workflow

- create or update `Installer/ScriptedInstaller.php`
- keep install and uninstall behavior idempotent
- keep schema changes in the installer instead of unrelated runtime code
- add installer language files when the installer exposes strings

## Validation

- confirm `Installer/ScriptedInstaller.php` exists
- confirm `Installer/languages/english/main.php` exists when needed
