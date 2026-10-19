#!/bin/bash
# =============================================================================
# enforce-secrets.sh — no secrets in the working tree (Stop)
# =============================================================================
#
# Blocks Stop when gitleaks finds a secret in the working tree (`--no-git`,
# .gitleaks.toml), whenever files changed since the last commit. Runs in
# every session, pipeline or not. gate:17 scans the git history.
#
# The scanner: tools/gitleaks (pinned by bin/install-tools.sh), else gitleaks
# on the PATH, else the Docker image. No usable scanner, or a gitleaks error
# (exit code other than 0 or 1), blocks: a secrets check never passes on
# missing tooling.
#
# After three consecutive blocks by this hook in one session it says how to
# clear its guard; it never passes silently.
#
# Input: JSON on stdin with session_id, stop_hook_active, transcript_path
# Exit:  0 = allow stop, 2 = block with feedback on stderr
# =============================================================================

set -euo pipefail

INPUT=$(cat)

if ! command -v jq &>/dev/null; then
    echo "ERROR: enforce-secrets.sh — jq not found; cannot read the hook input." >&2
    exit 2
fi

SESSION_ID=$(echo "$INPUT" | jq -r '.session_id // "unknown"')
GUARD_FILE="${TMPDIR:-/tmp}/.hook-secrets-guard-${SESSION_ID}"

# shellcheck source=./lib-hook-helpers.sh
source "$(dirname "$0")/lib-hook-helpers.sh"
cd "$PROJECT_ROOT" || exit 0

clean_pass_exit() {
    rm -f "$GUARD_FILE"
    exit 0
}

block() {
    local PASS_COUNT
    PASS_COUNT=$(cat "$GUARD_FILE" 2>/dev/null || echo 0)
    PASS_COUNT=$((PASS_COUNT + 1))
    echo "$PASS_COUNT" > "$GUARD_FILE"
    {
        echo ""
        if [ "$PASS_COUNT" -ge 3 ]; then
            echo "BLOCKED — enforce-secrets.sh has blocked ${PASS_COUNT} times in a row this session."
            echo "If it is a false positive, allow-list it in .gitleaks.toml with the reason; then: rm -f $GUARD_FILE"
            echo ""
        fi
        echo "$1"
    } >&2
    exit 2
}

git_capture CHANGED enforce-secrets.sh git diff --name-only --diff-filter=ACM HEAD
git_capture UNTRACKED enforce-secrets.sh git ls-files --others --exclude-standard

if [ -z "$(printf '%s%s' "$CHANGED" "$UNTRACKED" | tr -d '[:space:]')" ]; then
    clean_pass_exit
fi

ARGS=(detect --config .gitleaks.toml --no-git --redact --no-banner --exit-code 1)
OUTPUT=""
STATUS=0

if [ -x tools/gitleaks ]; then
    OUTPUT=$(tools/gitleaks "${ARGS[@]}" --source . 2>&1) || STATUS=$?
elif command -v gitleaks &>/dev/null; then
    OUTPUT=$(gitleaks "${ARGS[@]}" --source . 2>&1) || STATUS=$?
elif command -v docker &>/dev/null && docker info >/dev/null 2>&1; then
    OUTPUT=$(docker run --rm -v "${PROJECT_ROOT}:/path" -w /path zricethezav/gitleaks:latest \
        "${ARGS[@]}" --source /path 2>&1) || STATUS=$?
else
    block "STOP BLOCKED — enforce-secrets.sh: no gitleaks (tools/gitleaks, PATH or Docker). Run bin/install-tools.sh."
fi

if [ "$STATUS" -eq 1 ]; then
    block "STOP BLOCKED — secrets in the working tree:

$OUTPUT

Secrets are never committed: rotate the credential and remove it before stopping."
fi

if [ "$STATUS" -ne 0 ]; then
    block "STOP BLOCKED — gitleaks exited with code ${STATUS}; this is a tooling or .gitleaks.toml error, not a pass:

$OUTPUT"
fi

clean_pass_exit
