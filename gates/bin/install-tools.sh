#!/usr/bin/env bash
#
# Install the non-PHP gate tooling: gitleaks (pinned, into ./tools/, gate:17),
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
SEMGREP_VERSION="${SEMGREP_VERSION:-1.95.0}"

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
    if command -v semgrep >/dev/null 2>&1; then
        echo "semgrep already on PATH: $(command -v semgrep) ($(semgrep --version 2>/dev/null | awk 'NR == 1'))"
        return
    fi
    # A missing python3 is a hard fail: a warning would only surface later,
    # as gate:15's binary-not-found error, far from the real cause.
    if ! command -v python3 >/dev/null 2>&1; then
        echo "ERROR: python3 not found — required to install semgrep $SEMGREP_VERSION." >&2
        echo "Install python3 (e.g. apt install python3 python3-pip) and re-run." >&2
        return 1
    fi
    if ! command -v pip3 >/dev/null 2>&1 && ! python3 -m pip --version >/dev/null 2>&1; then
        echo "ERROR: pip is not available under python3." >&2
        echo "Install python3-pip (e.g. apt install python3-pip) and re-run." >&2
        return 1
    fi
    echo "Installing semgrep $SEMGREP_VERSION via pip --user ..."
    python3 -m pip install --user --upgrade "semgrep==$SEMGREP_VERSION"
}

install_shellcheck() {
    if command -v shellcheck >/dev/null 2>&1; then
        echo "shellcheck already on PATH: $(command -v shellcheck) ($(shellcheck --version 2>/dev/null | grep -E '^version:' | awk '{print $2}'))"
        return
    fi
    # The shellcheck binary is shipped by every major distro and Homebrew.
    # We do not pin a specific version because the 0.x line is stable and
    # our gate (gate:18) asserts against -S warning, not against any
    # version-specific check ID.
    if command -v pacman >/dev/null 2>&1; then
        echo "Installing shellcheck via pacman ..."
        sudo pacman -S --needed --noconfirm shellcheck
        return
    fi
    if command -v apt-get >/dev/null 2>&1; then
        echo "Installing shellcheck via apt-get ..."
        sudo apt-get install -y shellcheck
        return
    fi
    if command -v brew >/dev/null 2>&1; then
        echo "Installing shellcheck via brew ..."
        brew install shellcheck
        return
    fi
    echo "ERROR: shellcheck not found and no supported package manager (pacman / apt-get / brew)." >&2
    echo "Install shellcheck manually from https://www.shellcheck.net/" >&2
    return 1
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
command -v semgrep || true
