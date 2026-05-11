# Add Observer Hook

Use this skill when encapsulated plugin behavior should attach to existing Zen Cart runtime flow through observer or notifier patterns.

## Workflow

- create the observer file under the encapsulated plugin observer directory
- follow the expected `auto_` naming where auto-loading depends on it
- prefer observer hooks over direct edits to bootstrap files
- keep catalog-side and admin-side observers in their respective plugin trees

## Validation

- confirm at least one observer file exists under the plugin
- confirm the observer path matches the intended catalog or admin side
