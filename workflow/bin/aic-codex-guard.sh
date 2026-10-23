#!/usr/bin/env bash
# Native callbacks use supported JSON decisions. A broken pre-hook refuses.
set -euo pipefail
verb="${1:-}"
fallback() {
    if [ "${verb}" = "pre" ]; then
        printf '%s\n' '{"hookSpecificOutput":{"hookEventName":"PreToolUse","permissionDecision":"deny","permissionDecisionReason":"The AIC guard could not run. Restore the installed package."}}'
    elif [ "${verb}" = "permission" ]; then
        printf '%s\n' '{"hookSpecificOutput":{"hookEventName":"PermissionRequest","decision":{"behavior":"deny","message":"The AIC guard could not run. Restore the installed package."}}}'
    else
        printf '%s\n' '{"systemMessage":"The AIC lifecycle guard could not run. No review receipt counts."}'
    fi
}
root="$(git rev-parse --show-toplevel 2>/dev/null)" || {
    fallback
    exit 0
}
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)" || {
    fallback
    exit 0
}
# Resolve parent directories with logical cd before PHP sees the path.
# A filesystem lookup of /linked/package/bin/../../../autoload.php follows the symlink first.
autoload="$(cd "${here}/../../.." && pwd)/autoload.php" || {
    fallback
    exit 0
}
if [ ! -f "${autoload}" ]; then
    autoload="$(cd "${here}/../.." && pwd)/vendor/autoload.php" || {
        fallback
        exit 0
    }
fi
if ! command -v php > /dev/null 2>&1 || [ ! -f "${autoload}" ]; then
    fallback
    exit 0
fi
if ! reply="$(php "${here}/aic-codex-guard.php" "${root}" "${HOME:-}" "${verb}" "${autoload}")"; then
    fallback
    exit 0
fi
printf '%s\n' "${reply}"
