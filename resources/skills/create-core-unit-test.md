# Create Core Unit Test

Use this skill when adding or updating a unit test for core Zen Cart code under the central test framework.

## Workflow

- place core unit tests under `not_for_release/testFramework/Unit/`
- extend `Tests\Support\zcUnitTestCase`
- keep the test focused on isolated logic instead of full in-process storefront or admin flows
- prefer narrow fixtures and deterministic assertions over broad integration setup
- run `composer tests-unit` or the smallest relevant PHPUnit filter after the change

## Validation

- confirm the test file lives under `not_for_release/testFramework/Unit/`
- confirm it extends `zcUnitTestCase`
- confirm the test targets isolated logic rather than full feature flow
