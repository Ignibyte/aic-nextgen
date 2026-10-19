#!/bin/bash
# =============================================================================
# enforce-quality.sh — code quality on changed PHP files (Stop)
# =============================================================================
#
# Blocks Stop while the PHP files changed since the last commit (tracked and
# untracked) fail the fast checks (CONSTITUTION.md §14):
#
#   1. declare(strict_types=1) in every file
#   2. a class-level docblock on every new file that declares a class
#   3. Pint
#   4. PHPStan over phpstan.neon (whole scope: per-file runs miss cross-file errors)
#   5. Rector (dry run) on the files under its paths
#   6. PHPCS (phpcs.xml.dist) on the changed files, less the four type-hint
#      sniffs that need a per-file human decision; gate:11 runs them all
#
# This is a fast backstop, not the gate: whole-scope findings (PHPMD across
# the tree, Deptrac, the native gate) show up only in `composer run gate`.
# It runs in every session, pipeline or not.
#
# After three consecutive blocks by this hook in one session it stops
# retrying and says how to clear its guard; it never passes silently.
#
# Input: JSON on stdin with session_id, stop_hook_active, transcript_path
# Exit:  0 = allow stop, 2 = block with feedback on stderr
# =============================================================================

set -euo pipefail

INPUT=$(cat)

SESSION_ID=$(echo "$INPUT" | jq -r '.session_id // "unknown"' 2>/dev/null || echo unknown)
GUARD_FILE="${TMPDIR:-/tmp}/.hook-quality-guard-${SESSION_ID}"

clean_pass_exit() {
    rm -f "$GUARD_FILE"
    exit 0
}

if ! command -v jq &>/dev/null; then
    clean_pass_exit
fi

# shellcheck source=./lib-hook-helpers.sh
source "$(dirname "$0")/lib-hook-helpers.sh"
cd "$PROJECT_ROOT" || exit 0

git_capture CHANGED_PHP_FILES enforce-quality.sh \
    git diff --name-only --diff-filter=ACM HEAD -- '*.php'
git_capture UNTRACKED_PHP_FILES enforce-quality.sh \
    git ls-files --others --exclude-standard -- '*.php'

FILES=""
while IFS= read -r file; do
    [ -n "$file" ] || continue
    case "$file" in
        vendor/*|storage/*|docs/*|.native-gate/*) continue ;;
    esac
    [ -f "$file" ] && FILES="${FILES}${file}"$'\n'
done <<< "$(printf '%s\n%s\n' "$CHANGED_PHP_FILES" "$UNTRACKED_PHP_FILES" | sort -u)"
FILES=$(echo "$FILES" | grep -v '^$' || true)

if [ -z "$FILES" ]; then
    clean_pass_exit
fi

mapfile -t FILE_LIST <<< "$FILES"
VIOLATIONS=()

# --- 1. strict types ---
for file in "${FILE_LIST[@]}"; do
    if grep -q '<?php' < <(head -5 "$file") && ! grep -q 'declare(strict_types=1)' < <(head -5 "$file"); then
        VIOLATIONS+=("STRICT_TYPES: missing declare(strict_types=1) in $file")
    fi
done

# --- 2. class docblock on new files ---
while IFS= read -r file; do
    [ -n "$file" ] && [ -f "$file" ] || continue
    CLASS_LINE=$(grep -m 1 -n -E '^(abstract |final |readonly )*(class|enum|interface|trait) ' "$file" | cut -d: -f1 || true)
    if [ -n "$CLASS_LINE" ] && ! grep -q '\*/' < <(sed -n "1,${CLASS_LINE}p" "$file"); then
        VIOLATIONS+=("DOCBLOCK: missing class-level docblock in $file")
    fi
done <<< "$UNTRACKED_PHP_FILES"

# --- 3. Pint ---
if [ -x vendor/bin/pint ]; then
    PINT_OUTPUT=$(vendor/bin/pint --test --config=pint.json "${FILE_LIST[@]}" 2>&1) || \
        VIOLATIONS+=("PINT: formatting. Run 'vendor/bin/pint':
$PINT_OUTPUT")
fi

# --- 4. PHPStan (whole scope) ---
if [ -x vendor/bin/phpstan ]; then
    PHPSTAN_OUTPUT=$(vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=2G 2>&1) || \
        VIOLATIONS+=("PHPSTAN: static analysis errors:
$PHPSTAN_OUTPUT")
fi

# --- 5. Rector, on the files under rector.php's paths ---
RECTOR_FILES=()
for file in "${FILE_LIST[@]}"; do
    case "$file" in
        framework/src/*|app/*|public/*|bin/*|gates/*) RECTOR_FILES+=("$file") ;;
    esac
done
if [ -x vendor/bin/rector ] && [ ${#RECTOR_FILES[@]} -gt 0 ]; then
    RECTOR_OUTPUT=$(vendor/bin/rector process --dry-run --no-progress-bar "${RECTOR_FILES[@]}" 2>&1) || \
        VIOLATIONS+=("RECTOR: pending refactors. Run 'vendor/bin/rector process' on the files and keep its output:
$RECTOR_OUTPUT")
fi

# --- 6. PHPCS on the changed files ---
if [ -x vendor/bin/phpcs ] && [ -f phpcs.xml.dist ]; then
    PHPCS_OUTPUT=$(vendor/bin/phpcs --standard=phpcs.xml.dist --no-cache --no-colors \
        --exclude=SlevomatCodingStandard.TypeHints.PropertyTypeHint,SlevomatCodingStandard.TypeHints.ReturnTypeHint,SlevomatCodingStandard.TypeHints.ParameterTypeHint,SlevomatCodingStandard.TypeHints.DisallowMixedTypeHint \
        "${FILE_LIST[@]}" 2>&1) || \
        VIOLATIONS+=("PHPCS: coding standard. Run 'vendor/bin/phpcbf' for what it can fix:
$PHPCS_OUTPUT")
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
        echo "BLOCKED — enforce-quality.sh has blocked ${PASS_COUNT} times in a row this session."
        echo "Fix the finding below. If it is a flake and the tree is clean: rm -f $GUARD_FILE"
        echo ""
    fi
    echo "STOP BLOCKED — code quality not met on the changed PHP files:"
    echo ""
    for violation in "${VIOLATIONS[@]}"; do
        echo "  VIOLATION: $violation"
        echo ""
    done
    echo "Fix every violation at the source (CONSTITUTION.md §0, §14)."
} >&2
exit 2
