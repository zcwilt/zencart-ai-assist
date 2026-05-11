# MCP Client Setup

This document explains how to connect Zen AI Assist's MCP server to common MCP clients.

Canonical server command for an installed Zen Cart checkout:

```bash
bin/zencart ai:mcp:serve
```

If your local checkout is normally accessed through DDEV, the recommended MCP client command is:

```bash
ddev php bin/zencart ai:mcp:serve
```

Zen AI Assist uses stdio transport. The client should launch the command as a local subprocess and communicate over standard input and output.

The current MCP surface includes docs search, repo search, docs-versus-code comparison, plugin diagnostics, guidance-topic reads, and task-specific skill reads from the local plugin bundle.

## Prerequisites

- Zen AI Assist is installed in the target Zen Cart checkout.
- The Zen Cart checkout has a working `bin/zencart`.
- You can run `bin/zencart ai:mcp:serve` from the store root.
- If your local environment uses DDEV, you can run `ddev php bin/zencart ai:mcp:serve`.
- For tools that inspect installed plugin state, the store's DB configuration must be reachable from the client runtime.

## VS Code

VS Code supports workspace-level and user-level MCP configuration via `mcp.json`.

Recommended workspace config:

Path:

- `.vscode/mcp.json`

Example:

```json
{
  "servers": {
    "zen-ai-assist": {
      "type": "stdio",
      "command": "ddev",
      "args": ["php", "bin/zencart", "ai:mcp:serve"]
    }
  }
}
```

Notes:

- Open the Zen Cart checkout itself as the VS Code workspace root, not the standalone `zencart-zen-ai-assist` repo.
- The `ddev` launch form is the verified setup for this repository's local development environment.
- If your checkout is not using DDEV, launch the underlying Zen Cart command directly with your local PHP runtime instead.
- After editing the config, use `MCP: List Servers` or restart VS Code if the server does not appear immediately.

Useful VS Code commands:

- `MCP: Add Server`
- `MCP: Open Workspace Folder MCP Configuration`
- `MCP: Open User Configuration`
- `MCP: List Servers`
- `MCP: Reset Cached Tools`

## PhpStorm / Junie

PhpStorm itself is not the MCP client here. The relevant client is Junie inside JetBrains IDEs.

You can configure MCP servers in:

- global scope: `~/.junie/mcp/mcp.json`
- project scope: `.junie/mcp/mcp.json`

Recommended project-level example:

```json
{
  "mcpServers": {
    "zen-ai-assist": {
      "command": "ddev",
      "args": ["php", "bin/zencart", "ai:mcp:serve"]
    }
  }
}
```

Setup flow in PhpStorm:

1. Open the Zen Cart project in PhpStorm.
2. Go to `Settings | Tools | Junie | MCP Settings`.
3. Add or edit the `mcp.json` configuration.
4. Confirm the server appears in the MCP Servers list and reaches an active state.

Notes:

- If your IDE is not running in a DDEV-backed checkout, replace the command with your local PHP runtime and the store's `bin/zencart` path.
- Junie currently supports stdio MCP servers, which matches Zen AI Assist.
- If the server starts but some DB-backed tools fail, verify the store's DB host is reachable from the same machine where PhpStorm runs.

## Codex CLI

Codex supports MCP configuration and the official docs confirm that MCP configuration is shared between the CLI and IDE extension.

This repository does not yet include a verified Zen AI Assist-specific Codex config example because the `codex` binary is not available in this environment for command-shape verification.

Recommended process:

1. On the machine where Codex is installed, run:

```bash
codex mcp --help
```

2. If available, inspect:

```bash
codex mcp add --help
```

3. Add a local stdio server that launches:

```bash
ddev php /absolute/path/to/your/store/bin/zencart ai:mcp:serve
```

4. Verify it appears in:

```bash
codex mcp list
```

If your Codex installation uses direct config editing, use the current Codex MCP config schema from the official docs and point the local server command at the same `ai:mcp:serve` entrypoint.

## Standalone Plugin Repo

If you are working inside the standalone `zencart-zen-ai-assist` repository instead of an installed checkout:

- prefer connecting clients to an actual Zen Cart checkout where the plugin is installed
- treat `bin/zen-ai-assist-mcp` as a development shim only
- if you use the shim directly, run it from the Zen Cart checkout root or set `ZEN_AI_ASSIST_PROJECT_ROOT`

Example:

```bash
ZEN_AI_ASSIST_PROJECT_ROOT=/absolute/path/to/your/store php bin/zen-ai-assist-mcp
```

This is useful for development and testing, but it is not the stable integration contract for end users.

## Troubleshooting

- Server does not start:
  Ensure `bin/zencart ai:mcp:serve` runs from the store root, or `ddev php bin/zencart ai:mcp:serve` if your checkout uses DDEV.
- Tools are missing:
  Rebuild catalogs with `bin/zencart ai:catalog:build` and reconnect the client. Generated indexes are written to `cache/zen-ai-assist/catalogs/`.
- Docs tools return no useful results:
  Fetch docs first with `bin/zencart ai:docs:fetch`, then rebuild catalogs. Fetched docs are cached in `cache/zen-ai-assist/docs-cache/`.
- Installed plugin inspection fails:
  Check store DB connectivity and configure files.
- MCP client shows stale tools:
  Restart the client or reset the cached tool list.

## Sources

- OpenAI Codex / Docs MCP: https://developers.openai.com/learn/docs-mcp
- VS Code MCP configuration reference: https://code.visualstudio.com/docs/copilot/reference/mcp-configuration
- VS Code MCP usage: https://code.visualstudio.com/docs/copilot/customization/mcp-servers
- Junie MCP settings: https://www.jetbrains.com/help/junie/mcp-settings.html
- Junie MCP model/protocol overview: https://www.jetbrains.com/help/junie/model-context-protocol-mcp.html
- Junie CLI MCP configuration: https://junie.jetbrains.com/docs/junie-cli-mcp-configuration.html
