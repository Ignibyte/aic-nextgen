#!/bin/bash
# =============================================================================
# enforce-completion.sh — phase evidence before Stop (Stop)
# =============================================================================
#
# Blocks Stop in a pipeline session until the phase has produced its evidence
# (CONSTITUTION.md §4). The evidence is local:
#
#   recall       a Read/Grep/Glob under docs/ (the ticket, the plan, the
#                pipeline docs) or of memory, a `bin/ticket.sh` run, or an
#                Agent dispatch (CONSTITUTION.md §18.2)
#   plan         `bin/ticket.sh create` or `comment`
#   check        `composer run gate` (the only gate run)
#   complete     an `## AAR` section in the active notes.md, and both docs
#                gates: `composer run gate:19` (docs) and `gate:22` (pipeline docs)
#   work-lite    `composer run gate` too: the lite path skips Plan and
#                Documentation, never Quality checks
#
# Transcript checks are idempotent, so a second Stop attempt re-checks; there
# is no bypass.
#
# Input: JSON on stdin with session_id, stop_hook_active, transcript_path
# Exit:  0 = allow stop, 2 = block with feedback on stderr
# =============================================================================

set -euo pipefail

INPUT=$(cat)

if ! command -v jq &>/dev/null; then
    exit 0
fi

TRANSCRIPT_PATH=$(echo "$INPUT" | jq -r '.transcript_path // empty')
if [ -z "$TRANSCRIPT_PATH" ] || [ ! -f "$TRANSCRIPT_PATH" ]; then
    exit 0
fi

# shellcheck source=./lib-hook-helpers.sh
source "$(dirname "$0")/lib-hook-helpers.sh"

if ! is_pipeline_session "$TRANSCRIPT_PATH"; then
    exit 0
fi

LATEST=$(latest_pipeline_command "$TRANSCRIPT_PATH")
COMMAND_NAME=$(detect_active_command "$TRANSCRIPT_PATH")

# Only the four phases and /work-lite owe evidence here.
if [ -z "$COMMAND_NAME" ] && [ "$LATEST" != "work-lite" ]; then
    exit 0
fi

TOOL_CALLS=$(get_tool_calls "$TRANSCRIPT_PATH")
FILE_PATHS=$(extract_file_tool_paths_with_subagents "$TRANSCRIPT_PATH")
BASH_COMMANDS=$(extract_bash_commands "$TRANSCRIPT_PATH")

did_local_recall() {
    grep -qE '(^|/)docs/|MEMORY|/memory/' <<< "$FILE_PATHS" && return 0
    grep -qE 'bin/ticket\.sh|(^|[ /])docs/' <<< "$BASH_COMMANDS" && return 0
    tool_was_called "$TOOL_CALLS" "Agent" && return 0
    return 1
}

ticket_op_ran() {
    grep -qE "bin/ticket\.sh[\"']? +$1" <<< "$BASH_COMMANDS"
}

ran_full_gate() {
    grep -qE 'composer run gate($|[^:])|gate-runner\.sh|vendor/bin/aic-gate *($|[;&|])' <<< "$BASH_COMMANDS"
}

notes_has_aar() {
    local notes
    notes=$(find "${PROJECT_ROOT}/docs/planning/pipeline/active/" -maxdepth 1 -type f -name '*.notes.md' 2>/dev/null | awk 'NR == 1')
    [ -n "$notes" ] || return 1
    grep -qE '^## AAR' "$notes"
}

MISSING=()

if ! did_local_recall; then
    MISSING+=("recall — read the ticket (bin/ticket.sh show <n>) and the docs this work touches (docs/planning/plan.md, roadmap.md, completed pipelines) before working. [CONSTITUTION.md §4]")
fi

case "${COMMAND_NAME:-$LATEST}" in
    plan)
        if ! ticket_op_ran "create" && ! ticket_op_ran "comment"; then
            MISSING+=("bin/ticket.sh create / comment — Plan records the work on its ticket: create one for new work, comment when continuing one.")
        fi
        ;;
    check|work-lite)
        if ! ran_full_gate; then
            MISSING+=("composer run gate — run the full gate suite and loop until the gate-summary line has no failed clause. [CONSTITUTION.md §0]")
        fi
        ;;
    complete)
        if ! notes_has_aar; then
            MISSING+=("notes.md AAR — write an '## AAR' section in the active notes.md: failures, decisions, outcome, lessons worth keeping. [CONSTITUTION.md §4]")
        fi
        if ! grep -qE 'gate:19|gate-doc-todos\.php' <<< "$BASH_COMMANDS"; then
            MISSING+=("composer run gate:19 — Documentation ends with the docs gate (dead links, TODO markers).")
        fi
        if ! grep -qE 'gate:22|gate-pipeline-spec-sync\.php' <<< "$BASH_COMMANDS"; then
            MISSING+=("composer run gate:22 — Documentation ends with the pipeline-doc gate.")
        fi
        ;;
esac

if [ ${#MISSING[@]} -gt 0 ]; then
    ROLE_DESC="/${LATEST}"
    {
        echo ""
        echo "STOP BLOCKED — '$ROLE_DESC' has not produced its evidence:"
        echo ""
        for item in "${MISSING[@]}"; do
            echo "  MISSING: $item"
            echo ""
        done
        echo "Complete every missing item before stopping."
        echo ""
        echo "If the transcript already holds this evidence, the hook is at fault, not the work:"
        echo "  jq -nc --arg t \"\$TRANSCRIPT\" '{transcript_path: \$t}' | bash .claude/hooks/enforce-completion.sh"
        echo "and fix the hook. There is no ceiling on repeated blocks (#16)."
    } >&2
    exit 2
fi

exit 0
