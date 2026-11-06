#!/usr/bin/env bash
#
# Install the non-PHP gate tooling at the versions AIC pins, into or linked from ./tools/:
# gitleaks (gate:17),
# semgrep (gate:15), shellcheck (gate:18), the app's Playwright (#174) and, in an
# app, AIC's pinned elephc release (gates 21 and 23, #281). The PHP tools come from
# ignibyte/aic-gates' require (#116). `vendor/bin/aic-gate tools` runs it in the
# project's root, as captainhook's post-merge hook does, and passes the profile
# (`app` or `aic`) as its one argument.
#
set -euo pipefail

ROOT="$(pwd)"
PROFILE="${1:-app}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ELEPHC_PIN="$SCRIPT_DIR/../native/elephc-release"
TOOLS_DIR="$ROOT/tools"
mkdir -p "$TOOLS_DIR"

GITLEAKS_VERSION="${GITLEAKS_VERSION:-8.30.1}"
SEMGREP_VERSION="${SEMGREP_VERSION:-1.156.0}"
SHELLCHECK_VERSION="${SHELLCHECK_VERSION:-0.11.0}"

case "$(uname -m)" in
    x86_64)  ARCH_GITLEAKS="x64" ;;
    aarch64) ARCH_GITLEAKS="arm64" ;;
    arm64)   ARCH_GITLEAKS="arm64" ;;
    *) echo "unsupported arch: $(uname -m)" >&2; exit 1 ;;
esac

case "$(uname -s)" in
    Linux)  OS="linux" ;;
    Darwin) OS="darwin" ;;
    *) echo "unsupported os: $(uname -s)" >&2; exit 1 ;;
esac

install_gitleaks() {
    local current=""
    if [[ -x "$TOOLS_DIR/gitleaks" ]]; then
        current="$("$TOOLS_DIR/gitleaks" version 2>/dev/null | awk '{print $1}' | tr -d 'v' || true)"
    fi
    if [[ "$current" == "$GITLEAKS_VERSION" ]]; then
        echo "gitleaks $GITLEAKS_VERSION already installed."
        return
    fi
    echo "Installing gitleaks $GITLEAKS_VERSION ($OS/$ARCH_GITLEAKS) ..."
    local url="https://github.com/gitleaks/gitleaks/releases/download/v${GITLEAKS_VERSION}/gitleaks_${GITLEAKS_VERSION}_${OS}_${ARCH_GITLEAKS}.tar.gz"
    curl -fsSL "$url" | tar -xz -C "$TOOLS_DIR" gitleaks
    chmod +x "$TOOLS_DIR/gitleaks"
}

install_semgrep() {
    # Pinned to the version AIC's verdicts run on (#280's rule), in a virtualenv of its own per
    # host (pip --user is refused on current Debian and Ubuntu, PEP 668), linked as tools/semgrep.
    local venv="${XDG_CACHE_HOME:-$HOME/.cache}/aic-tools/semgrep-$SEMGREP_VERSION"
    if [[ "$("$venv/bin/semgrep" --version 2>/dev/null | tail -n 1)" != "$SEMGREP_VERSION" ]]; then
        # A missing python3 or venv is a hard fail: a warning would only surface later,
        # as gate:15's binary-not-found error, far from the real cause.
        if ! command -v python3 >/dev/null 2>&1 || ! python3 -m venv --help >/dev/null 2>&1; then
            echo "ERROR: python3 with its venv module is required to install semgrep $SEMGREP_VERSION." >&2
            echo "Install them (python3 and python3-venv on Debian and Ubuntu) and re-run." >&2
            return 1
        fi
        echo "Installing semgrep $SEMGREP_VERSION into $venv ..."
        rm -rf "$venv"
        python3 -m venv "$venv"
        "$venv/bin/python" -m pip install --quiet --disable-pip-version-check "semgrep==$SEMGREP_VERSION"
    fi
    ln -sfn "$venv/bin/semgrep" "$TOOLS_DIR/semgrep"
    echo "semgrep $SEMGREP_VERSION installed: tools/semgrep -> $venv/bin/semgrep"
}

install_shellcheck() {
    # Pinned like semgrep, from the release binaries, into tools/: no package manager or root.
    if [[ "$("$TOOLS_DIR/shellcheck" --version 2>/dev/null | awk '$1 == "version:" { print $2 }')" == "$SHELLCHECK_VERSION" ]]; then
        echo "shellcheck $SHELLCHECK_VERSION already installed."
        return
    fi
    local arch
    case "$(uname -m)" in
        arm64) arch="aarch64" ;;
        *)     arch="$(uname -m)" ;;
    esac
    local name="shellcheck-v$SHELLCHECK_VERSION"
    local url="https://github.com/koalaman/shellcheck/releases/download/v$SHELLCHECK_VERSION/$name.$OS.$arch.tar.xz"
    echo "Installing shellcheck $SHELLCHECK_VERSION ($OS/$arch) ..."
    curl -fsSL "$url" | tar -xJ -C "$TOOLS_DIR" --strip-components=1 "$name/shellcheck"
    chmod +x "$TOOLS_DIR/shellcheck"
}

install_playwright() {
    # The app's browser tests (#174): Playwright pinned by package-lock.json, written by
    # vendor/bin/aic-gate init, and the Chromium it drives.
    if [ ! -f "$ROOT/package-lock.json" ]; then
        echo "No package-lock.json: no browser tests to install for (vendor/bin/aic-gate init writes it)."
        return
    fi
    if ! command -v npm >/dev/null 2>&1; then
        echo "ERROR: npm not found; install Node.js 20 or later for the browser tests." >&2
        return 1
    fi
    echo "Installing Playwright (npm ci) ..."
    (cd "$ROOT" && npm ci --no-audit --no-fund)
    "$ROOT/node_modules/.bin/playwright" install chromium
}

sha256_of() {
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum "$1" | awk '{print $1}'
    else
        shasum -a 256 "$1" | awk '{print $1}'
    fi
}

install_elephc() {
    # AIC's own repository proves the fork's main, built from source, on PATH or
    # through ELEPHC_BIN; only an app takes the pinned release.
    if [[ "$PROFILE" == "aic" ]]; then
        echo "elephc: AIC's own repository runs the fork built from source; no release installed."
        return
    fi
    local target
    case "$(uname -s)-$(uname -m)" in
        Linux-x86_64)  target="x86_64-unknown-linux-gnu" ;;
        Linux-aarch64) target="aarch64-unknown-linux-gnu" ;;
        Darwin-arm64)  target="aarch64-apple-darwin" ;;
        *)             target="$(uname -s)-$(uname -m)" ;;
    esac
    local tag sha
    tag="$(awk '$1 == "release" { print $2 }' "$ELEPHC_PIN")"
    sha="$(awk -v target="$target" '$1 == target { print $2 }' "$ELEPHC_PIN")"
    if [[ -z "$tag" || -z "$sha" ]]; then
        echo "elephc: AIC pins no compiler release for $target. Build the fork from source"
        echo "(vendor/ignibyte/aic-framework/docs/native.md) and put it on PATH or in ELEPHC_BIN."
        return
    fi
    local version="${tag#aic-v}"
    version="${version%-*}"
    local cache="${XDG_CACHE_HOME:-$HOME/.cache}/aic-elephc/$tag/$target"
    local tarball="elephc-$tag-$target.tar.gz"
    if [[ ! -x "$cache/elephc" || "$(cat "$cache/.sha256" 2>/dev/null || true)" != "$sha" ]]; then
        local base="${AIC_ELEPHC_MIRROR:-https://github.com/Ignibyte/aic_compiler/releases/download}"
        local download
        download="$(mktemp -d)"
        echo "Downloading elephc $tag ($target) from $base ..."
        if ! curl -fSL --retry 3 -o "$download/$tarball" "$base/$tag/$tarball"; then
            rm -rf "$download"
            echo "ERROR: could not download $base/$tag/$tarball." >&2
            return 1
        fi
        local actual
        actual="$(sha256_of "$download/$tarball")"
        if [[ "$actual" != "$sha" ]]; then
            rm -rf "$download"
            echo "ERROR: $tarball has SHA-256 $actual; AIC pins $sha. Nothing was installed." >&2
            return 1
        fi
        rm -rf "$cache.partial"
        mkdir -p "$cache.partial"
        tar -xzf "$download/$tarball" -C "$cache.partial"
        rm -rf "$download"
        printf '%s\n' "$sha" > "$cache.partial/.sha256"
        rm -rf "$cache"
        mv "$cache.partial" "$cache"
    fi
    local reported
    reported="$("$cache/elephc" --version)"
    if [[ "$reported" != "elephc $version" ]]; then
        echo "ERROR: $cache/elephc reports '$reported', not 'elephc $version'." >&2
        return 1
    fi
    ln -sfn "$cache/elephc" "$TOOLS_DIR/elephc"
    echo "elephc $tag ($target) installed: tools/elephc -> $cache/elephc"
}

install_gitleaks
install_semgrep
install_shellcheck
install_playwright
install_elephc

echo
echo "Done. Tools are at:"
ls -1 "$TOOLS_DIR"
