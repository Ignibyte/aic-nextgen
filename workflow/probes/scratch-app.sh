#!/usr/bin/env bash
# =============================================================================
# workflow/probes/scratch-app.sh: the one app builder the probes share (#119)
# =============================================================================
#
#   scratch-app.sh make <app> <package-copy>
#       This repository's working tree as an app in <app> (files the tree
#       deleted stay deleted), with aic/workflow required by path from a copy
#       of workflow/ in <package-copy>, the tool configs stripped of the
#       package's own source (scratch-app.php strip), gate:21's cache.txt
#       copied so its first run starts warm, the git hooks installed and
#       bin/aic workflow:init run. Two commits: base, then workflow:init.
#   scratch-app.sh look <spec>
#       Plan's code lookups for <spec>, run in the current app (#118): the
#       overview, each manifest row and its module, a search for each PHP
#       row's class, and the refs of each class the spec names.
#
# The seeker probe (seeker-checklist.sh) and bin/aic workflow:probe both call
# it, so a change to what an app needs is made here once.
# =============================================================================

set -euo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

make_app() {
    local app="$1" package="$2"
    rm -rf "${app}" "${package}"
    mkdir -p "${package}"
    rsync -a --delete "${repo}/workflow/" "${package}/workflow/"
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
        ln -sfn "${package}/workflow" vendor/aic/workflow
        if [ -f "${repo}/.native-gate/cache.txt" ]; then
            mkdir -p .native-gate
            cp "${repo}/.native-gate/cache.txt" .native-gate/cache.txt
        fi
        php "${repo}/workflow/probes/scratch-app.php" strip "${package}"
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

case "${1:-}" in
    make) make_app "${2:?usage: scratch-app.sh make <app> <package-copy>}" "${3:?usage: scratch-app.sh make <app> <package-copy>}" ;;
    look) look "${2:?usage: scratch-app.sh look <spec>}" ;;
    *) echo 'usage: scratch-app.sh make <app> <package-copy> | look <spec>' >&2; exit 2 ;;
esac
