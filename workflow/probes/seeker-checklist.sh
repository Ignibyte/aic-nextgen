#!/usr/bin/env bash
# =============================================================================
# workflow/probes/seeker-checklist.sh <scratch-dir> [runs]: the seeker probe (#103, #127)
# =============================================================================
#
# Counts what the aic-seeker catches. For each planted item under
# workflow/probes/seeker/<item>/ (ticket.md, spec.md, change.patch) and the
# clean control, it makes an app from this repository's working tree (files it
# deleted stay deleted) in <scratch-dir>/<item>,
# the package required by path from a copy, installs the workflow, takes the
# ticket's run through plan (with plan's code lookups, #118), applies the change
# (two lines of context, so a newer CHANGELOG still takes it) and builds, and
# runs code once:
# the answer must be inspection-due, which shows every gate green. Then it runs
# the seeker headless <runs> times (3; 2 for the control) and reads each ledger
# with the workflow's own parser. A run with no ledger is invalid, never a miss.
# It prints, per item, how many runs caught it (an open finding citing it) and
# how many rated it CRITICAL or HIGH (`high`; since #127 no finding holds the
# run, so this is the ledger's severity only). Each read costs a Claude Code
# session.
#
# The seeker runs as the session's main agent, defined from the installed agent
# file through --agents, with no project settings and so no hooks
# (--setting-sources local), and CLAUDECODE unset so it starts inside a Claude
# Code session.
# PG* must point at a Postgres the app can use (gate:23).
# =============================================================================

set -euo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
probes="${repo}/workflow/probes/seeker"
scratch="${1:?usage: seeker-checklist.sh <scratch-dir> [runs]}"
runs="${2:-3}"
mkdir -p "${scratch}"
scratch="$(cd "${scratch}" && pwd)"

# make_app <dir>: this repository's tree as an app, aic/workflow required by path from a copy.
make_app() {
    local app="$1"
    rm -rf "${app}"
    mkdir -p "${scratch}/pkg"
    rsync -a --delete "${repo}/workflow/" "${scratch}/pkg/workflow/"
    git clone -q "${repo}" "${app}"
    (cd "${repo}" && git ls-files -co --exclude-standard -z) | rsync -a --from0 --files-from=- "${repo}/" "${app}/"
    comm -23 <(git -C "${app}" ls-files | sort) <(cd "${repo}" && git ls-files -co --exclude-standard | sort) \
        | (cd "${app}" && xargs -r -d '\n' rm -f --)
    find "${app}" -mindepth 1 -depth -type d -empty -not -path "${app}/.git/*" -delete
    (
        cd "${app}"
        git rm -rq --cached workflow .claude CLAUDE.md CONSTITUTION.md
        rm -rf workflow .claude CLAUDE.md CONSTITUTION.md
        cp -a "${repo}/vendor" "${repo}/tools" .
        ln -sfn "${scratch}/pkg/workflow" vendor/aic/workflow
        php "${probes}/probe.php" strip "${scratch}/pkg"
        git config user.email probe@example.com
        git config user.name probe
        git add -A
        git commit -qm "base" --no-verify
        vendor/bin/captainhook install -f -q
        php bin/aic workflow:init < /dev/null > /dev/null
        git add -A
        git commit -qm "workflow:init" --no-verify
    )
}

# look <spec>: plan's lookups (#118's grounding): the overview, each manifest row and its module, a
# search for each PHP row's class, and the refs of each class the spec names.
look() {
    local path module class
    php bin/aic code:tree < /dev/null > /dev/null
    while IFS= read -r path; do
        module="$(sed -nE 's#^modules/([^/]+)/.*#\1#p' <<< "${path}")"
        if [ -n "${module}" ]; then
            php bin/aic code:tree "${module}" < /dev/null > /dev/null
        fi
        php bin/aic code:tree "${path}" < /dev/null > /dev/null || true
        if [ "${path%.php}" != "${path}" ]; then
            php bin/aic code:search "$(basename "${path}" .php)" < /dev/null > /dev/null || true
        fi
    done < <(sed -nE "s/^\\| \`([^\`]+)\`.*/\\1/p" "$1")
    while IFS= read -r class; do
        php bin/aic code:refs "${class}" < /dev/null > /dev/null || true
    done < <(grep -oE 'App\\[A-Za-z\\]+' "$1" | sort -u)
}

# plant <item>: the item's ticket through plan, its change applied and built; prints the code answer's outcome.
plant() {
    local item="$1" app="${scratch}/$1"
    make_app "${app}" > /dev/null
    (
        cd "${app}"
        cp "${probes}/${item}/ticket.md" tickets/0001-probe.md
        git add tickets
        git commit -qm "the ticket" --no-verify > /dev/null
        php bin/aic workflow:run 1 < /dev/null > /dev/null || true
        cp "${probes}/${item}/spec.md" var/workflow/spec-1.md
        look "${probes}/${item}/spec.md"
        php bin/aic workflow:run < /dev/null > /dev/null || true
        git apply -C2 "${probes}/${item}/change.patch"
        composer run build > /dev/null 2>&1
        php bin/aic workflow:run < /dev/null > "${scratch}/${item}.code.json" || true
        php "${probes}/probe.php" field "${scratch}/${item}.code.json" outcome
    )
}

# read <item> <n>: one headless seeker read; its last message saved as the ledger.
read_once() {
    local item="$1" n="$2"
    (
        cd "${scratch}/${item}"
        php "${probes}/probe.php" agent .claude/agents/aic-seeker.md > "${scratch}/${item}.agents.json"
        env -u CLAUDECODE timeout 900 claude -p "Inspect the open run." --agents "${scratch}/${item}.agents.json" --agent aic-seeker \
            --setting-sources local --permission-mode bypassPermissions --output-format json < /dev/null \
            > "${scratch}/${item}.read${n}.json" 2> "${scratch}/${item}.read${n}.err" || true
    )
    php "${probes}/probe.php" field "${scratch}/${item}.read${n}.json" result > "${scratch}/${item}.ledger${n}.md"
}

# score <item> <n>: high, caught, missed or invalid, from the workflow's own parser.
score() {
    local item="$1" n="$2" target
    target="$(cat "${probes}/${item}/item")"
    (cd "${scratch}/${item}" && php "${probes}/probe.php" score "${scratch}/${item}.ledger${n}.md" "${target}")
}

printf '%-8s %-6s %-5s %-6s %-5s %s\n' item plant runs caught high invalid
for dir in "${probes}"/*/; do
    item="$(basename "${dir}")"
    outcome="$(plant "${item}")"
    count="${runs}"
    if [ "${item}" = control ]; then
        count=2
    fi
    if [ "${outcome}" != inspection-due ]; then
        printf '%-8s %-6s %s\n' "${item}" no "code answered ${outcome}: unplantable as it stands"
        continue
    fi
    caught=0
    high=0
    invalid=0
    for n in $(seq 1 "${count}"); do
        read_once "${item}" "${n}"
        case "$(score "${item}" "${n}")" in
            high) caught=$((caught + 1)); high=$((high + 1)) ;;
            caught) caught=$((caught + 1)) ;;
            invalid) invalid=$((invalid + 1)) ;;
            *) ;;
        esac
    done
    printf '%-8s %-6s %-5s %-6s %-5s %s\n' "${item}" yes "${count}" "${caught}" "${high}" "${invalid}"
done
