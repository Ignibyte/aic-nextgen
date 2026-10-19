#!/bin/bash
# =============================================================================
# enforce-mcp-config.sh — environment check (PreToolUse)
# =============================================================================
#
# In a pipeline session, blocks Write, Edit and Bash until `.mcp.json` exists:
# it carries the local Playwright MCP server that browser checks use when Chad
# is not watching (CONSTITUTION.md §17). The file is owner-managed: the agent
# never writes it, and a missing one is restored by Chad (or symlinked from
# the main checkout in a worktree).
#
# Input: JSON on stdin with tool_name, tool_input, transcript_path
# Exit:  0 = allow, 2 = block with feedback on stderr
# =============================================================================

set -euo pipefail

INPUT=$(cat)

if ! command -v jq &>/dev/null; then
    exit 0
fi

# shellcheck source=./lib-hook-helpers.sh
source "$(dirname "$0")/lib-hook-helpers.sh"

TRANSCRIPT_PATH=$(echo "$INPUT" | jq -r '.transcript_path // empty')
if [ -z "$TRANSCRIPT_PATH" ] || [ ! -f "$TRANSCRIPT_PATH" ]; then
    exit 0
fi

if ! is_pipeline_session "$TRANSCRIPT_PATH"; then
    exit 0
fi

# --- Worktree bootstrap: symlinking the canonical file is the fix itself ---
TOOL_NAME=$(echo "$INPUT" | jq -r '.tool_name // empty')
if [ "$TOOL_NAME" = "Bash" ]; then
    COMMAND=$(echo "$INPUT" | jq -r '.tool_input.command // empty')
    case "$COMMAND" in
        *"ln -s"*".mcp.json"*)
            exit 0
            ;;
    esac
fi

if [ ! -f "${PROJECT_ROOT}/.mcp.json" ]; then
    {
        echo ""
        echo "BLOCKED — .mcp.json not found."
        echo ""
        echo "Pipeline work needs the local Playwright MCP server declared in .mcp.json."
        echo "The file is owner-managed (CLAUDE.md): ask Chad to restore it; never write it."
        echo "In a worktree, symlink the main checkout's file: ln -s <main-checkout>/.mcp.json .mcp.json"
    } >&2
    exit 2
fi

exit 0
