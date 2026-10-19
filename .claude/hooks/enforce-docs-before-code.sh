#!/bin/bash
# =============================================================================
# enforce-docs-before-code.sh — documentation lookup before code (PreToolUse)
# =============================================================================
#
# In a pipeline session, blocks Write and Edit on code until the session has
# looked at the project's docs (CONSTITUTION.md §4, recall before action):
# a Read/Grep/Glob under docs/ (the plan, the roadmap, the handoff, the
# tickets, the pipeline docs), a Bash command reading docs/, or a delegated
# subagent dispatch (CONSTITUTION.md §18.2), whose lookups count across the
# dispatch tree.
#
# Input: JSON on stdin with tool_name, tool_input, transcript_path
# Exit:  0 = allow, 2 = block with feedback on stderr
# =============================================================================

set -euo pipefail

INPUT=$(cat)

if ! command -v jq &>/dev/null; then
    exit 0
fi

FILE_PATH=$(echo "$INPUT" | jq -r '.tool_input.file_path // empty')

if [ -z "$FILE_PATH" ]; then
    exit 0
fi

# shellcheck source=./lib-hook-helpers.sh
source "$(dirname "$0")/lib-hook-helpers.sh"

NORMALIZED=$(normalize_path "$FILE_PATH")

# --- Docs and config are not code ---
case "$NORMALIZED" in
    docs/*|.claude/*)
        exit 0
        ;;
    *.neon|*.json|*.yaml|*.yml|*.xml|*.dist|*.toml|*.md|*.lock|*.env*)
        exit 0
        ;;
esac

TRANSCRIPT_PATH=$(echo "$INPUT" | jq -r '.transcript_path // empty')
if [ -z "$TRANSCRIPT_PATH" ] || [ ! -f "$TRANSCRIPT_PATH" ]; then
    exit 0
fi

if ! is_pipeline_session "$TRANSCRIPT_PATH"; then
    exit 0
fi

# --- Lookup evidence across the dispatch tree (parent + subagents) ---
TOOL_CALLS=$(extract_tool_uses_with_subagents "$TRANSCRIPT_PATH" | sort -u)
FILE_PATHS=$(extract_file_tool_paths_with_subagents "$TRANSCRIPT_PATH")
BASH_COMMANDS=$(extract_bash_commands "$TRANSCRIPT_PATH")

DOCS_LOOKED_UP=false
if echo "$FILE_PATHS" | grep -qE '(^|/)docs/'; then
    DOCS_LOOKED_UP=true
elif echo "$BASH_COMMANDS" | grep -qE '(^|[ /])docs/|bin/ticket\.sh'; then
    DOCS_LOOKED_UP=true
elif tool_was_called "$TOOL_CALLS" "Agent"; then
    DOCS_LOOKED_UP=true
fi

if [ "$DOCS_LOOKED_UP" = false ]; then
    {
        echo ""
        echo "BLOCKED — Look at the project docs before writing code (CONSTITUTION.md §4)."
        echo ""
        echo "Read the ticket (bin/ticket.sh show <n>) and the parts of docs/planning/"
        echo "plan.md and roadmap.md that cover this change, or delegate the lookup to"
        echo "an Explore subagent (CONSTITUTION.md §18.2). Then retry the write."
    } >&2
    exit 2
fi

exit 0
