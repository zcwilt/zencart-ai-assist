# Create Core Feature Test

Use this skill when adding or updating a core storefront or admin in-process feature test in the central Zen Cart test framework.

## Workflow

- place storefront tests under `not_for_release/testFramework/FeatureStore/`
- place admin tests under `not_for_release/testFramework/FeatureAdmin/`
- extend the appropriate in-process base class
- choose the right grouping tags such as `parallel-candidate`, `serial`, or `plugin-filesystem`
- keep the scenario tied to observable storefront or admin behavior
- run the smallest relevant feature command or filter after the change

## Validation

- confirm the test file is in the correct FeatureStore or FeatureAdmin directory
- confirm the base class matches storefront or admin scope
- confirm explicit grouping tags are present where the framework expects them
