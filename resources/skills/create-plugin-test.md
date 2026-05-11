# Create Encapsulated Plugin Test

Use this skill when adding or updating tests that live with an encapsulated plugin under `zc_plugins/<PluginName>/<version>/tests/`.

## Workflow

- keep plugin-local tests under the plugin `tests/` directory
- use `tests/Unit/`, `tests/FeatureStore/`, or `tests/FeatureAdmin/` based on scope
- use `tests/bootstrap.php` and `tests/plugin-test.php` only when plugin-local bootstrap or metadata is needed
- use `Tests\Support\Traits\PluginLocalTestConcerns` when plugin-local install/bootstrap helpers are required
- choose `plugin-filesystem` and `serial` tags for tests that mutate plugin installation state
- run `composer tests-plugin -- --plugin <plugin-name>` with the smallest useful suite or filter

## Validation

- confirm the test lives under the encapsulated plugin version directory
- confirm the suite directory matches unit, storefront, or admin scope
- confirm filesystem-mutating tests use the required grouping tags
