#!/bin/bash
# =============================================================================
# enforce-agent-scope.sh — pipeline phase write scope (PreToolUse)
# =============================================================================
#
# Blocks a pipeline phase from writing outside its scope (CONSTITUTION.md §4).
# The active phase comes from the transcript (lib-hook-helpers.sh).
#
#   plan              docs/planning/* only (the ticket and the pipeline doc pair)
#   implement, check  unrestricted (Code and Quality checks write the code)
#   complete          docs, CHANGELOG*, CLAUDE.md, README.md, CONSTITUTION.md,
#                     and the project's auto-memory (outside the repo)
#
# Documentation runs after Quality checks passed, and the code gates do not
# run again, so it must not touch code. A code change found during
# Documentation goes back through /pipeline:check.
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

TRANSCRIPT_PATH=$(echo "$INPUT" | jq -r '.transcript_path // empty')
if [ -z "$TRANSCRIPT_PATH" ] || [ ! -f "$TRANSCRIPT_PATH" ]; then
    exit 0
fi

if ! is_pipeline_session "$TRANSCRIPT_PATH"; then
    exit 0
fi

COMMAND_NAME=$(detect_active_command "$TRANSCRIPT_PATH")

# Documentation's memory step writes here. Claude Code names the directory
# after the project path with every non-alphanumeric character as a dash.
MEMORY_DIR="$HOME/.claude/projects/${PROJECT_ROOT//[^A-Za-z0-9]/-}/memory"

if [ -z "$COMMAND_NAME" ]; then
    exit 0
fi

# --- Allowed paths per phase ---
check_scope() {
    local cmd="$1"
    local path="$2"

    case "$cmd" in
        plan)
            case "$path" in
                docs/planning/*) return 0 ;;
                *) return 1 ;;
            esac
            ;;
        complete)
            case "$path" in
                docs/*|CHANGELOG*|CLAUDE.md|README.md|CONSTITUTION.md|"$MEMORY_DIR"/*) return 0 ;;
                *) return 1 ;;
            esac
            ;;
        *)
            return 0
            ;;
    esac
}

if ! check_scope "$COMMAND_NAME" "$NORMALIZED"; then
    {
        echo ""
        echo "WRITE BLOCKED — /pipeline:$COMMAND_NAME cannot write to: $NORMALIZED"
        echo ""
        echo "Each phase has a write scope (CONSTITUTION.md §4); the boundaries are hard walls."
        echo ""
        echo "Allowed paths for /pipeline:$COMMAND_NAME:"
        case "$COMMAND_NAME" in
            plan)
                echo "  - docs/planning/*  (the ticket and the pipeline docs)" ;;
            complete)
                echo "  - docs/*, CHANGELOG*, CLAUDE.md, README.md, CONSTITUTION.md"
                echo "  - $MEMORY_DIR/*  (auto-memory)"
                echo "  A code change during Documentation goes back through /pipeline:check." ;;
        esac
        echo ""
        echo "Route this change to the phase that owns it."
    } >&2
    exit 2
fi

exit 0
