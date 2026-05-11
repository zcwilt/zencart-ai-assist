# Zen AI Assist

Zen AI Assist is a Zen Cart developer plugin for working with docs, repo code, and plugin diagnostics from one place.

It helps you:

- cache selected Zen Cart docs locally
- search docs and repo code together
- inspect plugin manifests and plugin structure
- run a local MCP server for editor or agent workflows

It is local-first and does not need a separate database or search service.

## Quick Start

Install or enable the plugin, then build its local data:

```bash
bin/zencart ai:docs:fetch
bin/zencart ai:catalog:build
```

Then use the commands you need:

```bash
ddev php bin/zencart ai:docs:fetch
ddev php bin/zencart ai:catalog:build
ddev php bin/zencart ai:mcp:serve
```

Underlying Zen Cart command names are:

```bash
bin/zencart ai:docs:search manifest
bin/zencart ai:docs:ask "How should an encapsulated plugin admin page be wired?"
bin/zencart ai:plugin:doctor zc_plugins/zen-ai-assist/v1.0.0
bin/zencart ai:plugin:doctor --all
bin/zencart ai:mcp:serve
```

## Common Uses

- `docs:search` for quick keyword lookup
- `docs:ask` when you want a docs-based answer with repo context
- `plugin:doctor` when a plugin looks incomplete or miswired
- `mcp:serve` when you want to connect Zen AI Assist to an editor or coding agent

## More Information

- User guide: [docs/user-guide.md](docs/user-guide.md)
- MCP client setup: [docs/mcp-clients.md](docs/mcp-clients.md)
- Contributor notes: [docs/zen-ai-assist-docs-plan.md](docs/zen-ai-assist-docs-plan.md)

## Notes

- Zen AI Assist stores fetched docs and generated catalogs under `cache/zen-ai-assist/`.
- If results look stale, rerun `ai:docs:fetch` and `ai:catalog:build`.
- For setup, troubleshooting, and command details, use the user guide.
