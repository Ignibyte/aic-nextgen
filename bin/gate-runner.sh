#!/bin/bash
# =============================================================================
# bin/gate-runner.sh — `composer run gate`: every quality gate, one summary
# =============================================================================
#
# Runs every `gate:N` script in composer.json, in numeric order, and keeps
# going after a failure, so one run reports every failing gate. (Old AIC
# chained the gates through one composer script, which stops at the first
# failure: a stack of four failures took four full runs to see.) The gate
# list is read from composer.json, so adding a gate is adding a script.
#
# Each gate ends as one of:
#   passed    exit 0
#   deferred  exit 0, and it printed a "gate:N — ... deferring" line: it could
#             not run in full yet (the require-checker on a manifest with no
#             source). A deferral is named in the summary; it is not coverage.
#   failed    any non-zero exit
#
# Before the summary, one line per gate gives its verdict for the workflow
# engine (#101): gate-result: gate:N passed|deferred|failed.
#
# The last line is always the summary:
#   gate-summary: P passed, D deferred (reasons), F failed (gate:a,gate:b).
#   gate-summary: P passed, 0 deferred. Full coverage.
#
# The gates are static analysis of code and config, so they run on the host;
# none needs DDEV or a database.
#
# Exit: 0 when no gate failed, 1 otherwise.
# =============================================================================

set -uo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${PROJECT_ROOT}" || exit 1

# Psalm and Rector expect their cache directories to exist.
mkdir -p "${PROJECT_ROOT}/.psalm-cache" "${PROJECT_ROOT}/.rector-cache"

# Every gate:N script name, sorted by N.
mapfile -t GATES < <(php -r '
    $scripts = json_decode((string) file_get_contents("composer.json"), true)["scripts"] ?? [];
    $gates = array_filter(array_keys($scripts), static fn (string $name): bool => preg_match("/^gate:\d+$/", $name) === 1);
    usort($gates, static fn (string $a, string $b): int => (int) substr($a, 5) <=> (int) substr($b, 5));
    echo implode(PHP_EOL, $gates), PHP_EOL;
')

if [ "${#GATES[@]}" -eq 0 ]; then
    echo "gate-runner: no gate:N scripts found in composer.json" >&2
    exit 1
fi

GATE_LOG=$(mktemp -t gate-runner.XXXXXX.log)
trap 'rm -f "${GATE_LOG}"' EXIT

PASSED=0
FAILED=()
DEFERRED=()
RESULTS=()

for gate in "${GATES[@]}"; do
    echo ""
    echo "=== ${gate} ==="
    composer run --timeout=0 "${gate}" 2>&1 | tee "${GATE_LOG}"
    status="${PIPESTATUS[0]}"

    if [ "${status}" -ne 0 ]; then
        FAILED+=("${gate}")
        RESULTS+=("${gate} failed")
        continue
    fi

    # Every deferral line the gate printed, without the "gate:N — " prefix.
    deferrals=$(grep -E "^${gate} — .*deferring" "${GATE_LOG}" | sed -E "s/^${gate} — //" | paste -sd '|' - || true)

    if [ -n "${deferrals}" ]; then
        DEFERRED+=("${gate}: ${deferrals//|/ | }")
        RESULTS+=("${gate} deferred")
        continue
    fi

    PASSED=$((PASSED + 1))
    RESULTS+=("${gate} passed")
done

if [ "${#DEFERRED[@]}" -gt 0 ]; then
    REASONS=$(printf '%s; ' "${DEFERRED[@]}")
    DEFERRED_PART="${#DEFERRED[@]} deferred (${REASONS%; })"
else
    DEFERRED_PART="0 deferred"
fi

echo ""

# One line per gate, which the workflow engine reads (#101): the summary's
# reasons are free text, so they cannot be parsed safely on their own.
printf 'gate-result: %s\n' "${RESULTS[@]}"

if [ "${#FAILED[@]}" -gt 0 ]; then
    FAILED_LIST=$(printf '%s,' "${FAILED[@]}")
    echo "gate-summary: ${PASSED} passed, ${DEFERRED_PART}, ${#FAILED[@]} failed (${FAILED_LIST%,})."
    exit 1
fi

if [ "${#DEFERRED[@]}" -gt 0 ]; then
    echo "gate-summary: ${PASSED} passed, ${DEFERRED_PART}."
    echo "Deferred gates are not coverage."
    exit 0
fi

echo "gate-summary: ${PASSED} passed, 0 deferred. Full coverage."
