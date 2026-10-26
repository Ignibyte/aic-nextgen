#!/usr/bin/env bash
# =============================================================================
# workflow/bin/aic-guard.sh <verb>: the workflow's guard hook (#102)
# =============================================================================
#
# Claude Code runs it with the hook's payload on stdin; the verb is edit,
# shell, record, stop or returned (#103; SubagentStop since #169: the guard
# notes what a subagent returned) (workflow/pack/hooks.json). It finds the project's
# root, stays silent where the workflow is not installed, and hands the call
# to aic-guard.php. Exit 0 allows, exit 2 refuses with the reason on stderr.
#
# Any other exit is a crash, and a crash refuses: an agent must not get past
# a guard that broke. Two exceptions: record and returned only keep the ledger, so their
# crash allows; and a stop crash lets a Stop that already continues once
# (stop_hook_active) end, so a broken guard cannot trap a session.
# =============================================================================

set -euo pipefail

verb="${1:-}"
payload="$(cat)"
root="${CLAUDE_PROJECT_DIR:-$(git rev-parse --show-toplevel 2>/dev/null || pwd)}"

if [ ! -f "${root}/config/workflow.php" ]; then
    exit 0
fi

crash() {
    if [ "${verb}" = "record" ] || [ "${verb}" = "returned" ]; then
        exit 0
    fi

    if [ "${verb}" = "stop" ] && [[ "${payload}" == *'"stop_hook_active":true'* || "${payload}" == *'"stop_hook_active": true'* ]]; then
        exit 0
    fi

    echo "aic-guard: $1; the guard refuses until it runs." >&2
    exit 2
}

if ! command -v php > /dev/null 2>&1; then
    crash "php is not on PATH"
fi

# The package's directory as the hook named it, symlinks kept: composer links a path repository
# into vendor/, and PHP's __DIR__ would resolve the link and miss the project's autoloader.
# cd keeps the path logical, so `..` climbs the link as named.
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
autoload="$(cd "${here}/../../.." && pwd)/autoload.php"
if [ ! -f "${autoload}" ]; then
    autoload="$(cd "${here}/../.." && pwd)/vendor/autoload.php"
fi

status=0
printf '%s' "${payload}" | php "${here}/aic-guard.php" "${root}" "${HOME:-}" "${verb}" "${autoload}" || status=$?

case "${status}" in
    0 | 2) exit "${status}" ;;
    *) crash "the guard crashed (exit ${status})" ;;
esac
