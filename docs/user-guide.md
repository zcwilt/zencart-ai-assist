# Zen AI Assist User Guide

This guide is for people using Zen AI Assist in a Zen Cart checkout.

It focuses on normal day-to-day usage:

- what Zen AI Assist is for
- how to install and verify it
- how to build its local data
- how to use the CLI and MCP server
- what to do when results look wrong

For contributor planning, use [zen-ai-assist-docs-plan.md](./zen-ai-assist-docs-plan.md).

For MCP client wiring details, use [mcp-clients.md](./mcp-clients.md).

## What Zen AI Assist Does

Zen AI Assist is a Zen Cart plugin that helps developers and coding agents work with Zen Cart in a more framework-aware way.

It currently provides:

- a local cache of selected official Zen Cart docs
- a local repo catalog for high-value Zen Cart files
- CLI commands for docs search, docs comparison, plugin doctor, and plugin scaffolding
- a local stdio MCP server for editor and agent integration
- structured workflow skills for common Zen Cart tasks

Zen AI Assist is local-first. It does not require SQLite, Redis, or a vector database.

## Prerequisites

Before using Zen AI Assist, make sure:

- Zen Cart is installed in your checkout
- Composer dependencies for the test/dev environment are installed if you plan to run tests
- `bin/zencart` works from the store root
- the store DB configuration is valid if you want installed-plugin inspection to work

## Installation

Zen AI Assist is an encapsulated plugin and should live under:

```text
zc_plugins/zen-ai-assist/v1.0.0/
```

To use it in a checkout:

1. Ensure the plugin files are present in that directory.
2. Install or enable the plugin through Zen Cart Plugin Manager.
3. Confirm the command surface is available:

```bash
bin/zencart list
```

Look for commands such as:

- `ai:docs:fetch`
- `ai:catalog:build`
- `ai:docs:search`
- `ai:docs:ask`
- `ai:docs:compare`
- `ai:plugin:doctor`
- `ai:mcp:serve`

## First-Time Setup

Zen AI Assist is most useful after its docs and repo catalogs are prepared.

Run:

```bash
ddev php bin/zencart ai:docs:fetch
ddev php bin/zencart ai:catalog:build
```

Underlying Zen Cart commands are:

```bash
bin/zencart ai:docs:fetch
bin/zencart ai:catalog:build
```

That populates:

- `cache/zen-ai-assist/docs-cache/`
- `cache/zen-ai-assist/catalogs/docs-index.json`
- `cache/zen-ai-assist/catalogs/repo-index.json`

You should rebuild catalogs after meaningful code changes if you want fresh repo evidence.

Zen AI Assist keeps this generated data in Zen Cart's standard `cache/` directory, not inside a single plugin version directory. That means upgrading from one Zen AI Assist version to another does not normally discard the cached docs or generated catalogs.

## Common CLI Workflows

### Search Documentation

```bash
bin/zencart ai:docs:search manifest
```

Use this when you already know the concept or keyword you want.

### Ask a Zen Cart Question

```bash
bin/zencart ai:docs:ask "How should an encapsulated plugin admin page be wired?"
```

Use this when you want:

- a documented approach
- current repo behavior
- a mismatch note when docs and code differ

### Compare Docs to Current Code

```bash
bin/zencart ai:docs:compare "How are installer scripts discovered?"
```

Use this when you specifically want both the intended convention and the current implementation evidence.

### Inspect a Plugin Manifest

```bash
bin/zencart ai:manifest:inspect zc_plugins/zen-ai-assist/v1.0.0/manifest.php
```

Use this for quick baseline manifest validation.

### Diagnose an Encapsulated Plugin

```bash
bin/zencart ai:plugin:doctor zc_plugins/zen-ai-assist/v1.0.0
```

Use this when an encapsulated plugin:

- is not loading
- looks partially wired
- behaves differently than expected

The doctor currently checks:

- manifest structure
- installer structure
- installed-plugin runtime state
- page wiring
- admin language/menu wiring
- observer/autoloader visibility
- bundled skill presence

To scan every encapsulated plugin in the checkout, use:

```bash
bin/zencart ai:plugin:doctor --all
```

That walks `zc_plugins/*/*/manifest.php`, runs the doctor against each plugin root, and exits non-zero if any plugin fails.

Pass/fail is severity-based:

- `error` findings make the command exit non-zero
- `warning` findings are reported but do not fail the command
- `info` findings are advisory only

### Scaffold a New Encapsulated Plugin

```bash
bin/zencart ai:make:plugin my-plugin
```

Use this to create a baseline encapsulated plugin structure.

## MCP Usage

Start the MCP server with:

```bash
ddev php bin/zencart ai:mcp:serve
```

Underlying Zen Cart command:

```bash
bin/zencart ai:mcp:serve
```

Zen AI Assist uses stdio transport. Your editor or agent client should launch that command as a local subprocess. In this repository's local development environment, the verified MCP client launch form is `ddev php bin/zencart ai:mcp:serve`.

The MCP server is the best interface when:

- you want coding agents to use Zen AI Assist automatically
- you want skills, docs, repo evidence, and plugin diagnostics available in one place

For client-specific setup, see [mcp-clients.md](./mcp-clients.md).

## Skills

Zen AI Assist ships structured workflow skills under `resources/skills/`.

These are intended to guide common Zen Cart tasks such as:

- encapsulated plugin scaffolding
- admin page creation
- storefront page creation
- installer work
- language-file wiring
- observer hooks
- plugin diagnosis
- core and plugin-local test creation/debugging

Important scope rule:

- plugin-oriented skills refer to encapsulated plugins under `zc_plugins/<unique_key>/<version>/`
- they are not intended for older dropped-in plugin layouts

## Expected Files

Zen AI Assist stores cache data under the Zen Cart checkout `cache/` directory and ships bundled guidance files inside the plugin:

- `cache/zen-ai-assist/docs-cache/`
- `cache/zen-ai-assist/catalogs/`
- `resources/guidance/`
- `resources/skills/`

This keeps generated data version-independent while bundled guidance and skills remain versioned with the plugin.

## Troubleshooting

### The commands do not appear

Check:

- the plugin is present under `zc_plugins/zen-ai-assist/v1.0.0/`
- the plugin is installed/enabled in Plugin Manager
- `bin/zencart` itself is working, or `ddev php bin/zencart` if your checkout runs inside DDEV

### Docs search returns weak or empty results

Refresh the local data:

```bash
ddev php bin/zencart ai:docs:fetch
ddev php bin/zencart ai:catalog:build
```

Underlying commands:

```bash
bin/zencart ai:docs:fetch
bin/zencart ai:catalog:build
```

### Repo results look stale

Rebuild the repo catalog:

```bash
ddev php bin/zencart ai:catalog:build
```

Underlying command:

```bash
bin/zencart ai:catalog:build
```

### Installed-plugin inspection fails

Zen AI Assist can still work in a degraded mode, but DB-aware inspection needs valid runtime context.

Check:

- store DB configure files
- DB host connectivity
- PHP MySQL driver availability
- CLI bootstrap availability

### MCP tools do not appear in the client

Check:

- `ddev php bin/zencart ai:mcp:serve` runs in a DDEV-backed checkout, or `bin/zencart ai:mcp:serve` runs directly from the store root
- the client launches the correct checkout path
- the client tool cache has been refreshed or the client restarted

### A plugin-oriented answer asks for `plugin_root`

That is expected when the matched skill is plugin-oriented and Zen AI Assist wants to attach `plugin_doctor` and installed-plugin state.

Provide a path such as:

```text
zc_plugins/example/v1.0.0/
```

## Suggested Everyday Workflow

For normal use:

1. Install and enable Zen AI Assist.
2. Run `ai:docs:fetch`.
3. Run `ai:catalog:build`.
4. Use CLI commands for direct lookup and diagnostics.
5. Use the MCP server when working with an editor or coding agent.
6. Rebuild catalogs after meaningful code changes.

## Related Docs

- [README.md](../README.md)
- [mcp-clients.md](./mcp-clients.md)
- [zen-ai-assist-docs-plan.md](./zen-ai-assist-docs-plan.md)
