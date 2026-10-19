#!/bin/bash
# =============================================================================
# enforce-phase-gate.sh — Pipeline Phase Gate Enforcement (PreToolUse)
# =============================================================================
#
# Blocks Write/Edit tool calls on application code unless the pipeline
# document shows the previous phase as PASS. This is the entry gate for each
# phase of the four-phase workflow (CONSTITUTION.md §4):
#
#   1 Plan            /pipeline:plan       no prerequisite (one active doc)
#   2 Code            /pipeline:implement  Phase 1 PASS, spec complete
#   3 Quality checks  /pipeline:check      Phase 2 PASS
#   4 Documentation   /pipeline:complete   Phase 3 PASS (writes limited to
#                                          docs by enforce-agent-scope.sh)
#
# Every phase has an entry gate, and evidence beats claims.
#
# Only activates when:
#   1. A pipeline command is active (detected from transcript)
#   2. An active pipeline document exists
#   3. The file being written is application code (not pipeline docs/config)
#
# Registered as a project-wide PreToolUse hook in .claude/settings.json.
#
# Input: JSON on stdin with tool_name, tool_input, transcript_path
# Exit:  0 = allow, 2 = block with feedback on stderr
# =============================================================================

set -euo pipefail

# Read hook input from stdin
INPUT=$(cat)

# --- Check jq availability ---
if ! command -v jq &>/dev/null; then
    exit 0
fi

# --- Source shared helpers ---
source "$(dirname "$0")/lib-hook-helpers.sh"

# --- Extract file path from tool input ---
FILE_PATH=$(echo "$INPUT" | jq -r '.tool_input.file_path // empty')

if [ -z "$FILE_PATH" ]; then
    exit 0
fi

# --- Normalize path ---
NORMALIZED=$(normalize_path "$FILE_PATH")

# --- Exempt non-application files ---
# Pipeline docs, config files, .claude/ infrastructure, docs/ are not gated
case "$NORMALIZED" in
    docs/planning/*|.claude/*|docs/*)
        exit 0
        ;;
    *.json|*.yaml|*.yml|*.xml|*.md|*.lock|*.env*)
        exit 0
        ;;
esac

# --- Get transcript path ---
TRANSCRIPT_PATH=$(echo "$INPUT" | jq -r '.transcript_path // empty')
if [ -z "$TRANSCRIPT_PATH" ] || [ ! -f "$TRANSCRIPT_PATH" ]; then
    exit 0
fi

# --- Pipeline-only: skip during normal conversation ---
if ! is_pipeline_session "$TRANSCRIPT_PATH"; then
    exit 0
fi

# --- Detect which pipeline command is active ---
COMMAND_NAME=$(detect_active_command "$TRANSCRIPT_PATH")

if [ -z "$COMMAND_NAME" ]; then
    exit 0
fi

# --- Check for conflicting active pipelines ---
# Never two pipeline documents active at once.
# A compact-spec pipeline is one `.spec.md` + `.notes.md` pair, so only the
# `.spec.md` files count. Returns 0 if clear, 1 if a pipeline is already active.
check_no_conflicting_pipelines() {
    local pipeline_dir="$1"
    local count=0

    for doc in "$pipeline_dir"/WORK-*.spec.md "$pipeline_dir"/PIPELINE-*.spec.md; do
        [ -f "$doc" ] || continue
        count=$((count + 1))
    done

    if [ "$count" -gt 0 ]; then
        echo "$count active pipeline(s) found:"
        for doc in "$pipeline_dir"/WORK-*.spec.md "$pipeline_dir"/PIPELINE-*.spec.md; do
            [ -f "$doc" ] || continue
            echo "  - $(basename "$doc")"
        done
        return 1
    fi
    return 0
}

# plan has no prerequisite phase, but check for conflicting active pipelines
if [ "$COMMAND_NAME" = "plan" ]; then
    PIPELINE_DIR="${PROJECT_ROOT}/docs/planning/pipeline/active"
    if [ -d "$PIPELINE_DIR" ]; then
        if ! CONFLICT_MSG=$(check_no_conflicting_pipelines "$PIPELINE_DIR" 2>&1); then
            {
                echo ""
                echo "PHASE GATE BLOCKED — Cannot create a new pipeline. $CONFLICT_MSG"
                echo ""
                echo "Never two pipeline documents active at once (CONSTITUTION.md §4)."
                echo "Complete or archive the existing pipeline first, then start a new one."
                echo "To archive: mv docs/planning/pipeline/active/{file} docs/planning/pipeline/completed/"
            } >&2
            exit 2
        fi
    fi
    exit 0
fi

# --- Find active pipeline document ---
PIPELINE_DIR="${PROJECT_ROOT}/docs/planning/pipeline/active"

PIPELINE_DOCS=()
if [ -d "$PIPELINE_DIR" ]; then
    for doc in "$PIPELINE_DIR"/WORK-*.spec.md "$PIPELINE_DIR"/PIPELINE-*.spec.md; do
        [ -f "$doc" ] || continue
        PIPELINE_DOCS+=("$doc")
    done
fi

if [ ${#PIPELINE_DOCS[@]} -eq 0 ]; then
    {
        echo ""
        echo "BLOCKED — No pipeline document found in $PIPELINE_DIR."
        echo "Run /pipeline:plan first to create a pipeline document."
    } >&2
    exit 2
fi

# Use the first (should be only) pipeline doc
PIPELINE_DOC="${PIPELINE_DOCS[0]}"

# --- Verify pipeline doc has a real UUID (not template placeholder) ---
PIPELINE_UUID=$(grep -oP '^pipeline_id:\s*\K[0-9a-f-]{36}' "$PIPELINE_DOC" 2>/dev/null || true)
if [ -z "$PIPELINE_UUID" ]; then
    {
        echo ""
        echo "PHASE GATE BLOCKED — Pipeline document has no UUID."
        echo ""
        echo "The pipeline_id frontmatter field must contain a valid UUID (not a template placeholder)."
        echo "Generate one: python3 -c \"import uuid; print(uuid.uuid4())\""
        echo ""
        echo "Pipeline document: $PIPELINE_DOC"
    } >&2
    exit 2
fi

# --- Check phase prerequisites ---
# The compact spec carries its state in YAML frontmatter as
# `status: Phase {N} — <Title> PASS; ready for Phase {M} — <Title>`. A phase
# advances `status` only when it finishes, so the active command checks the
# phase immediately before it. Plan advances to `Phase 1 — Plan PASS` only
# after the human confirmed the plan.
check_phase_pass() {
    local doc="$1"
    local phase_num="$2"
    local status_line
    status_line=$(grep -m 1 -oP '^status:\s*\K.*' "$doc" 2>/dev/null || true)
    if [ -n "$status_line" ] && grep -qE "Phase\s+${phase_num}\s+[—-].*PASS" <<< "$status_line"; then
        return 0
    fi
    return 1
}

# --- Check spec completeness (entry to Code) ---
# Verifies the plan carries real content, not template placeholders: a goal,
# at least one acceptance criterion, a file manifest with at least one row, and
# — when the change has a page to look at — a browser check list.
# Prints the missing items and returns 1 when incomplete.
check_spec_complete() {
    local doc="$1"
    local missing=""

    if ! grep -qP '^goal:\s*(?!<).{10,}' "$doc" 2>/dev/null; then
        missing="${missing}goal not populated. "
    fi
    if ! grep -qP '^\|\s*\d+\s*\|\s*(?!<)\S' < <(sed -n '/^## Acceptance Criteria/,/^## /p' "$doc" 2>/dev/null); then
        missing="${missing}Acceptance Criteria has no criterion row. "
    fi
    # A row whose first cell is path-like (contains "/" or "."), so the table
    # header (`| File | …`) and separator rows never count.
    if ! grep -qP '^\|\s*`?(?!<)[A-Za-z0-9_-]+[./][A-Za-z0-9_./*-]*`?\s*\|' < <(sed -n '/^### File Manifest/,/^##/p' "$doc" 2>/dev/null); then
        missing="${missing}Design → File Manifest has no file row. "
    fi
    if grep -qiP '^browser_testable:\s*yes' "$doc" 2>/dev/null; then
        if ! grep -qP '^- \[[ xX]\] (?!<).{5,}' < <(sed -n '/^## Browser Check/,/^## /p' "$doc" 2>/dev/null); then
            missing="${missing}browser_testable is Yes but the Browser Check list is empty. "
        fi
    fi

    if [ -n "$missing" ]; then
        echo "$missing"
        return 1
    fi
    return 0
}

# --- Gate logic per command ---
GATE_FAILED=false
GATE_MESSAGE=""

case "$COMMAND_NAME" in
    implement)
        if ! check_phase_pass "$PIPELINE_DOC" 1; then
            GATE_FAILED=true
            GATE_MESSAGE="Phase 1 (Plan) must be PASS — confirmed by the human — before Code can begin."
        elif ! SPEC_ISSUES=$(check_spec_complete "$PIPELINE_DOC"); then
            GATE_FAILED=true
            GATE_MESSAGE="The plan is incomplete: ${SPEC_ISSUES}Fill it in before Code begins."
        fi
        ;;
    check)
        if ! check_phase_pass "$PIPELINE_DOC" 2; then
            GATE_FAILED=true
            GATE_MESSAGE="Phase 2 (Code) must be PASS before Quality checks can begin."
        fi
        ;;
    complete)
        if ! check_phase_pass "$PIPELINE_DOC" 3; then
            GATE_FAILED=true
            GATE_MESSAGE="Phase 3 (Quality checks) must be PASS — every code gate green — before Documentation can begin."
        fi
        ;;
esac

if [ "$GATE_FAILED" = true ]; then
    {
        echo ""
        echo "PHASE GATE BLOCKED — $GATE_MESSAGE"
        echo ""
        echo "Every phase has an entry gate (CONSTITUTION.md §4)."
        echo "The previous phase must be PASS before the next phase begins."
        echo ""
        echo "Pipeline document: $PIPELINE_DOC"
        echo "Active command: /pipeline:$COMMAND_NAME"
    } >&2
    exit 2
fi

exit 0
