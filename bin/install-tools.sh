#!/usr/bin/env bash
#
# Install the non-PHP gate tooling: gitleaks (pinned, into ./tools/, gate:17),
# semgrep (gate:15) and shellcheck (gate:18). The PHP tools come from
# composer's require-dev. captainhook's post-merge hook runs this script.
#
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
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

install_gitleaks
install_semgrep
install_shellcheck

echo
echo "Done. Tools are at:"
ls -1 "$TOOLS_DIR"
command -v semgrep || true
