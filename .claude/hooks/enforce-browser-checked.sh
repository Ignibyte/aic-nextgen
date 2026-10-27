#!/bin/bash
# =============================================================================
# enforce-browser-checked.sh — a live browser check during Code (Stop)
# =============================================================================
#
# When the active spec says `browser_testable: Yes`, Code ends with the agent
# having looked at the change in a real browser on this box and exercised it
# (CONSTITUTION.md §17). The look is best a Playwright test in tests/browser/,
# which the pipeline may keep as a regression gate:23 runs (#174).
#
# Blocks Stop during /pipeline:implement unless both hold:
#   1. This session looked at the page: a Bash call that ran Playwright
#      (`playwright test`, `playwright cli` or `vendor/bin/aic-gate browser`,
#      the browser gate, #174), or a navigation AND a look through Marley's
#      Browser tab (mcp__plugin_marley_marley__: browser_navigate, then
#      browser_snapshot or browser_look) or the local Playwright MCP server
#      (mcp__playwright__: browser_navigate, then browser_snapshot or
#      browser_take_screenshot).
#   2. Every item in the spec's `## Browser Check` list is ticked (`- [x]`).
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

if [ "$(detect_active_command "$TRANSCRIPT_PATH")" != "implement" ]; then
    exit 0
fi

SPEC=""
for doc in "${PROJECT_ROOT}"/docs/planning/pipeline/active/*.spec.md; do
    [ -f "$doc" ] || continue
    SPEC="$doc"
    break
done

if [ -z "$SPEC" ]; then
    exit 0
fi

if ! grep -qiP '^browser_testable:\s*yes\b' "$SPEC" 2>/dev/null; then
    exit 0
fi

# Browser tool names this session used, as "<server> <tool>".
BROWSER_TOOLS=$(jq -sr '
    [.[] | (.message // .) | select(.role == "assistant") |
     (.content // [])[] | select(.type == "tool_use") |
     .name // empty |
     select(startswith("mcp__playwright__") or startswith("mcp__plugin_marley_marley__")) |
     sub("^mcp__playwright__"; "playwright ") |
     sub("^mcp__plugin_marley_marley__"; "marley ")] | unique | .[]
' "$TRANSCRIPT_PATH" 2>/dev/null || true)

# Playwright runs this session, from the shell (#174).
PLAYWRIGHT_RUNS=$(jq -sr '
    [.[] | (.message // .) | select(.role == "assistant") |
     (.content // [])[] | select(.type == "tool_use" and .name == "Bash") |
     .input.command // empty |
     select(test("playwright (test|cli)|aic-gate browser"))] | length
' "$TRANSCRIPT_PATH" 2>/dev/null || echo 0)

VIOLATIONS=()

if [ "${PLAYWRIGHT_RUNS:-0}" -gt 0 ]; then
    BROWSER_TOOLS=$'playwright browser_navigate\nplaywright browser_snapshot'
fi

if ! grep -qE '^(playwright|marley) browser_navigate$' <<< "$BROWSER_TOOLS"; then
    VIOLATIONS+=("No look at the page this session. Write the look as a Playwright test in tests/browser/ and run it with vendor/bin/aic-gate browser --base-url=<site> (or open the page with browser_navigate in Marley's Browser tab or the local Playwright MCP).")
fi

if ! grep -qE '^(playwright (browser_snapshot|browser_take_screenshot)|marley (browser_snapshot|browser_look))$' <<< "$BROWSER_TOOLS"; then
    VIOLATIONS+=("No look at the page this session. Use browser_snapshot or browser_take_screenshot (Playwright), or browser_snapshot or browser_look (Marley).")
fi

CHECK_ITEMS=$(sed -n '/^## Browser Check/,/^## /p' "$SPEC" 2>/dev/null | grep -E '^- \[[ xX]\]' || true)
if [ -z "$CHECK_ITEMS" ]; then
    VIOLATIONS+=("The spec's '## Browser Check' list is empty. List what to do and what should happen, then check each item live.")
else
    OPEN_ITEMS=$(echo "$CHECK_ITEMS" | grep -E '^- \[ \]' || true)
    if [ -n "$OPEN_ITEMS" ]; then
        VIOLATIONS+=("Browser Check items not yet verified in the browser:
${OPEN_ITEMS}")
    fi
fi

if [ ${#VIOLATIONS[@]} -gt 0 ]; then
    {
        echo ""
        echo "BROWSER CHECK INCOMPLETE — $(basename "$SPEC") is browser-testable."
        echo ""
        echo "Code ends with a live look at the change (CONSTITUTION.md §17): drive the page,"
        echo "work through the Browser Check list, and tick each item with what you saw."
        echo ""
        for v in "${VIOLATIONS[@]}"; do
            echo "  - $v"
        done
    } >&2
    exit 2
fi

exit 0
