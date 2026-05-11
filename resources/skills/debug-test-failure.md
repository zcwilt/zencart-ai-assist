# Debug Test Failure

Use this skill when a core or plugin-local Zen Cart test is failing and the next step is to isolate the failure mode instead of changing production code blindly.

## Workflow

- identify whether the failure is unit, storefront feature, admin feature, or plugin-local
- rerun the smallest relevant test command with a focused filter
- check whether the failure is caused by runtime bootstrap, DB config, grouping/isolation, or the assertion itself
- inspect recent test artifacts and logs when the failure is in-process or runtime-sensitive
- separate flaky environment/setup failures from real behavior regressions before editing application code

## Validation

- confirm the failure is reproducible with the smallest relevant command
- confirm the suspected cause is classified as code behavior, runtime setup, or test-isolation issue
