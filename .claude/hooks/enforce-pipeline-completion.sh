#!/bin/bash
# =============================================================================
# enforce-pipeline-completion.sh — Documentation evidence and archiving (Stop)
# =============================================================================
#
# In a pipeline session that wrote a pipeline doc:
#
#   1. During /pipeline:complete, blocks Stop until this session wrote the
#      changelog (CHANGELOG.md) and a decision record: docs/planning/plan.md,
#      roadmap.md or handoff.md, or any doc outside the pipeline and ticket
#      directories. Decisions live in plan.md (nextgen keeps no ADR directory).
#   2. Outside /pipeline:complete, blocks Stop while a spec whose status is
#      `Phase 4 — Documentation PASS` still sits in active/: /commit archives
#      the pair into completed/. Pipeline docs are never deleted.
#
# The pipeline docs live in the private planning repo (docs/planning); without
# it there is nothing to check.
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

# shellcheck source=./lib-hook-helpers.sh
source "$(dirname "$0")/lib-hook-helpers.sh"

if ! is_pipeline_session "$TRANSCRIPT_PATH"; then
    exit 0
fi

ACTIVE_DIR="${PROJECT_ROOT}/docs/planning/pipeline/active"
COMPLETED_DIR="${PROJECT_ROOT}/docs/planning/pipeline/completed"

if [ ! -d "$ACTIVE_DIR" ]; then
    exit 0
fi

# Write/Edit targets of this session, relative to the project root.
WRITTEN=$(jq -sr '
    [.[] | (.message // .) | select(.role == "assistant") |
     (.content // [])[] | select(.type == "tool_use") |
     select(.name == "Write" or .name == "Edit") |
     .input.file_path // empty] | unique | .[]
' "$TRANSCRIPT_PATH" 2>/dev/null | while IFS= read -r path; do normalize_path "$path"; done || true)

if ! echo "$WRITTEN" | grep -q '^docs/planning/pipeline/active/'; then
    exit 0
fi

VIOLATIONS=()

if [ "$(detect_active_command "$TRANSCRIPT_PATH")" = "complete" ]; then
    if ! echo "$WRITTEN" | grep -qE '^CHANGELOG'; then
        VIOLATIONS+=("CHANGELOG.md was not updated. Every delivered pipeline gets a changelog entry with its ticket number.")
    fi

    DECISIONS=$(echo "$WRITTEN" | grep -E '^docs/' | grep -vE '^docs/planning/(pipeline|tickets)/' || true)
    if [ -z "$DECISIONS" ]; then
        VIOLATIONS+=("No decision record was written. Record what this pipeline decided in docs/planning/plan.md (or roadmap.md, handoff.md, or the doc the change affects), so the next session finds it.")
    fi
else
    for doc in "$ACTIVE_DIR"/*.spec.md; do
        [ -f "$doc" ] || continue
        if grep -qP '^status:.*Phase\s+4\s+[—-]\s+Documentation\s+PASS' "$doc" 2>/dev/null; then
            FILENAME=$(basename "$doc")
            VIOLATIONS+=("$FILENAME is complete but still in active/: mv it and its .notes.md to completed/ (mv $doc ${COMPLETED_DIR}/${FILENAME}).")
        fi
    done
fi

if [ ${#VIOLATIONS[@]} -gt 0 ]; then
    {
        echo ""
        echo "STOP BLOCKED — the pipeline's Documentation is not finished:"
        echo ""
        for violation in "${VIOLATIONS[@]}"; do
            echo "  VIOLATION: $violation"
            echo ""
        done
        echo "Pipeline documents are never deleted; they move to completed/ for history."
    } >&2
    exit 2
fi

exit 0
