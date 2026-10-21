#!/bin/bash
# =============================================================================
# enforce-sast.sh — SAST and dependency checks on changed code (Stop)
# =============================================================================
#
# In a pipeline session, blocks Stop while the changed code fails:
#
#   1. PHPMD (phpmd.xml) on the changed PHP files under framework/src, app, gates
#   2. Deptrac over the whole tree, when PHP changed
#   3. composer-unused (gate:4, deferring while app/ is empty), when composer.json changed
#   4. semgrep (semgrep.yml), when PHP changed
#   5. dangerous patterns in changed runtime code (framework/src, app, public):
#      shell execution, eval, unserialize without allowed_classes, hardcoded
#      credentials. The gate tooling under gates/ shells out by design.
#   6. phpcpd on the changed PHP files
#
# Old AIC ran semgrep only when `.semgrep.yml` existed; the file is
# `semgrep.yml`, so the check never ran. Fixed in #3.
#
# After three consecutive blocks by this hook in one session it says how to
# clear its guard; it never passes silently.
#
# Input: JSON on stdin with session_id, stop_hook_active, transcript_path
# Exit:  0 = allow stop, 2 = block with feedback on stderr
# =============================================================================

set -euo pipefail

INPUT=$(cat)

SESSION_ID=$(echo "$INPUT" | jq -r '.session_id // "unknown"' 2>/dev/null || echo unknown)
GUARD_FILE="${TMPDIR:-/tmp}/.hook-sast-guard-${SESSION_ID}"

clean_pass_exit() {
    rm -f "$GUARD_FILE"
    exit 0
}

if ! command -v jq &>/dev/null; then
    clean_pass_exit
fi

TRANSCRIPT_PATH=$(echo "$INPUT" | jq -r '.transcript_path // empty')

# shellcheck source=./lib-hook-helpers.sh
source "$(dirname "$0")/lib-hook-helpers.sh"
cd "$PROJECT_ROOT" || exit 0

if ! is_pipeline_session "$TRANSCRIPT_PATH"; then
    clean_pass_exit
fi

SCAN_PATHS=('framework/src/*.php' 'app/*.php' 'gates/*.php' 'public/*.php' 'bin/*.php')
git_capture CHANGED enforce-sast.sh git diff --name-only --diff-filter=ACM HEAD -- "${SCAN_PATHS[@]}"
git_capture UNTRACKED enforce-sast.sh git ls-files --others --exclude-standard -- "${SCAN_PATHS[@]}"
git_capture COMPOSER_CHANGED enforce-sast.sh git diff --name-only HEAD -- composer.json

PHP_FILES=()
while IFS= read -r file; do
    [ -n "$file" ] && [ -f "$file" ] && PHP_FILES+=("$file")
done <<< "$(printf '%s\n%s\n' "$CHANGED" "$UNTRACKED" | sort -u)"

if [ ${#PHP_FILES[@]} -eq 0 ] && [ -z "$COMPOSER_CHANGED" ]; then
    clean_pass_exit
fi

VIOLATIONS=()

if [ ${#PHP_FILES[@]} -gt 0 ]; then
    # --- 1. PHPMD, on the files inside its gate:10 scope ---
    PHPMD_FILES=()
    for file in "${PHP_FILES[@]}"; do
        case "$file" in
            framework/src/*|app/*|gates/*) PHPMD_FILES+=("$file") ;;
        esac
    done
    if [ -x vendor/bin/phpmd ] && [ ${#PHPMD_FILES[@]} -gt 0 ]; then
        PHPMD_OUTPUT=$(vendor/bin/phpmd "$(IFS=,; echo "${PHPMD_FILES[*]}")" text phpmd.xml 2>&1) || \
            VIOLATIONS+=("PHPMD:
$PHPMD_OUTPUT")
    fi

    # --- 2. Deptrac ---
    if [ -x vendor/bin/deptrac ]; then
        DEPTRAC_OUTPUT=$(vendor/bin/deptrac analyse --no-progress --fail-on-uncovered 2>&1) || \
            VIOLATIONS+=("DEPTRAC: layer violations (vendor/bin/deptrac analyse for details):
$(echo "$DEPTRAC_OUTPUT" | tail -20)")
    fi

    # --- 4. semgrep ---
    # The repo root is the target and each changed file an --include: a rule's
    # anchored paths ("/bin/**") match only relative to the scan root, so
    # passing the files as targets ignored them and reported what gate:15 does not.
    if command -v semgrep &>/dev/null && [ -f semgrep.yml ]; then
        SEMGREP_INCLUDES=()
        for file in "${PHP_FILES[@]}"; do
            SEMGREP_INCLUDES+=("--include=${file}")
        done
        SEMGREP_OUTPUT=$(semgrep scan --config semgrep.yml --quiet --error --metrics=off "${SEMGREP_INCLUDES[@]}" . 2>&1) || \
            VIOLATIONS+=("SEMGREP:
$SEMGREP_OUTPUT")
    fi

    # --- 5. dangerous patterns in runtime code ---
    SECURITY_ISSUES=""
    for file in "${PHP_FILES[@]}"; do
        case "$file" in
            framework/src/*|app/*|public/*) ;;
            *) continue ;;
        esac
        HITS=$(grep -nP '(?<!->)(?<!::)\b(shell_exec|exec|system|passthru|proc_open|popen)\s*\(|\beval\s*\(' "$file" 2>/dev/null || true)
        HITS="${HITS}$(grep -nP '\bunserialize\s*\(' "$file" 2>/dev/null | grep -v 'allowed_classes' || true)"
        HITS="${HITS}$(grep -nPi '(password|secret|api_key|token)\s*=\s*["\x27][^"\x27]{8,}["\x27]' "$file" 2>/dev/null || true)"
        [ -n "$HITS" ] && SECURITY_ISSUES="${SECURITY_ISSUES}${file}:
${HITS}
"
    done
    [ -n "$SECURITY_ISSUES" ] && VIOLATIONS+=("SECURITY: dangerous patterns in runtime code:
$SECURITY_ISSUES")

    # --- 6. phpcpd ---
    if [ -x vendor/bin/phpcpd ]; then
        PHPCPD_OUTPUT=$(vendor/bin/phpcpd --min-lines=4 --min-tokens=50 --fuzzy "${PHP_FILES[@]}" 2>&1) || true
        if grep -q 'duplicated lines' <<< "$PHPCPD_OUTPUT"; then
            VIOLATIONS+=("PHPCPD: duplication in the changed files:
$PHPCPD_OUTPUT")
        fi
    fi
fi

# --- 3. composer-unused ---
if [ -n "$COMPOSER_CHANGED" ] && [ -f bin/gate-composer-unused.php ]; then
    UNUSED_OUTPUT=$(php bin/gate-composer-unused.php 2>&1) || \
        VIOLATIONS+=("COMPOSER-UNUSED:
$UNUSED_OUTPUT")
fi

if [ ${#VIOLATIONS[@]} -eq 0 ]; then
    clean_pass_exit
fi

PASS_COUNT=$(cat "$GUARD_FILE" 2>/dev/null || echo 0)
PASS_COUNT=$((PASS_COUNT + 1))
echo "$PASS_COUNT" > "$GUARD_FILE"

{
    echo ""
    if [ "$PASS_COUNT" -ge 3 ]; then
        echo "BLOCKED — enforce-sast.sh has blocked ${PASS_COUNT} times in a row this session."
        echo "Fix the finding below. If it is a flake and the tree is clean: rm -f $GUARD_FILE"
        echo ""
    fi
    echo "STOP BLOCKED — SAST and dependency checks not met:"
    echo ""
    for violation in "${VIOLATIONS[@]}"; do
        echo "  VIOLATION: $violation"
        echo ""
    done
    echo "Fix every violation at the source (CONSTITUTION.md §0)."
} >&2
exit 2
