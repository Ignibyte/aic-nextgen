#!/usr/bin/env bash
# split-packages.sh (#278): split each AIC package's directory into a branch of its own, with
# that directory's history, so Packagist can read a composer.json at a repository's root. The
# tag workflow (.github/workflows/split.yml) runs it and pushes each branch to the package's
# read-only repository; run it by hand to try a split before any tag.
#
# Usage: bin/split-packages.sh [<ref>]   (default HEAD)
# Leaves one local branch split/<name> per package and prints one line per package:
#   <package> <directory> <branch> <commit>
set -euo pipefail

ref="${1:-HEAD}"
cd "$(git rev-parse --show-toplevel)"

while read -r package directory; do
    branch="split/${package#ignibyte/}"
    commit="$(git subtree split --quiet --prefix="$directory" "$ref")"
    git branch --force "$branch" "$commit" > /dev/null
    printf '%s %s %s %s\n' "$package" "$directory" "$branch" "$commit"
done <<'PACKAGES'
ignibyte/aic-framework framework
ignibyte/aic-gates gates
ignibyte/aic-scaffold scaffold
ignibyte/aic-workflow workflow
PACKAGES
