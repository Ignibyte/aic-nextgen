#!/bin/bash
# =============================================================================
# lib-hook-helpers.sh — shared helpers for the enforcement hooks
# =============================================================================
#
# Source this file from a hook: source "$(dirname "$0")/lib-hook-helpers.sh"
#
# Transcript reading. Claude Code writes transcripts as JSONL, one entry per
# line, each wrapped as { "type": ..., "message": { "role": ..., "content": ... } }.
# Every reader slurps (`jq -s`) and unwraps with `(.message // .)`, so it also
# reads older top-level-shaped transcripts. Never use a bare `.[]` without -s:
# it errors on every line, the `|| true` fallback swallows the error, and the
# hook turns into a silent no-op.
#
# Pipeline sessions. The hooks act only while a workflow command runs. A
# command shows up in the transcript two ways:
#   - typed by the user: a user text entry starting
#     `<command-name>/pipeline:plan</command-name>` (plain `/pipeline:plan`
#     is accepted too);
#   - invoked by the model: a Skill tool use with input.skill `pipeline:plan`.
# Old AIC matched only text starting with `/`, so a typed command never made a
# session a pipeline session (found and fixed in #3).
# =============================================================================

# --- Project root ---
# Claude Code sets CLAUDE_PROJECT_DIR for every hook; the session's current
# directory may sit in the nested planning repo (docs/planning), where the git
# toplevel is the wrong root. Outside Claude Code, fall back to the git root.
PROJECT_ROOT="${CLAUDE_PROJECT_DIR:-$(git rev-parse --show-toplevel 2>/dev/null || pwd)}"

# --- The workflow commands the hooks recognize ---
# jq regex over a command text (the leading `/` already in place).
PIPELINE_COMMAND_RE='^\s*/(pipeline[-:](plan|implement|check|complete)|commit|work-lite|work|qa|bug|spec|scope-split)\b'

# --- Normalize a path relative to PROJECT_ROOT ---
# Strips the project root and any `.claude/worktrees/<name>/` prefix, so a
# worktree path matches the same patterns as the main checkout.
normalize_path() {
    echo "$1" \
        | sed "s|^${PROJECT_ROOT}/||" \
        | sed 's|^\.claude/worktrees/[A-Za-z0-9._-]\+/||'
}

# =============================================================================
# Safe git capture
# =============================================================================
# Capture git output into a named variable without masking a real git failure:
# rc 0 and 1 are results, anything else (128: not a repo) blocks the hook.
#
# Usage:
#   git_capture VAR_NAME hook-name git args...
git_capture() {
    local __var_name="$1"
    local __hook_name="$2"
    shift 2
    local __out
    local __rc
    set +e
    __out=$("$@" 2>/dev/null)
    __rc=$?
    set -e
    if [ "$__rc" -ne 0 ] && [ "$__rc" -ne 1 ]; then
        echo "ERROR: ${__hook_name} — git failed (rc=${__rc}, command: $*). Refusing to silently mask." >&2
        exit 2
    fi
    printf -v "$__var_name" '%s' "$__out"
}

# =============================================================================
# Transcript extraction
# =============================================================================

# Every Bash command the assistant ran.
extract_bash_commands() {
    local transcript_path="$1"
    if [ -z "$transcript_path" ] || [ ! -f "$transcript_path" ]; then
        return 0
    fi
    jq -sr '
        [.[] | (.message // .) | select(.role == "assistant") |
         (.content // [])[] | select(.type == "tool_use") |
         select(.name == "Bash") | .input.command // empty] | .[]
    ' "$transcript_path" 2>/dev/null || true
}

# Every tool the assistant used, MCP prefixes stripped (mcp__x__browser_navigate → browser_navigate).
extract_assistant_tool_uses() {
    local transcript_path="$1"
    if [ -z "$transcript_path" ] || [ ! -f "$transcript_path" ]; then
        return 0
    fi
    jq -sr '
        [.[] | (.message // .) | select(.role == "assistant") |
         (.content // [])[] | select(.type == "tool_use") |
         .name // empty |
         sub("^mcp__.*__"; "")] | .[]
    ' "$transcript_path" 2>/dev/null || true
}

# The file paths of every Read, Grep, Glob, Write and Edit.
extract_file_tool_paths() {
    local transcript_path="$1"
    if [ -z "$transcript_path" ] || [ ! -f "$transcript_path" ]; then
        return 0
    fi
    jq -sr '
        [.[] | (.message // .) | select(.role == "assistant") |
         (.content // [])[] | select(.type == "tool_use") |
         select(.name == "Read" or .name == "Grep" or .name == "Glob"
                or .name == "Write" or .name == "Edit") |
         (.input.file_path // empty), (.input.path // empty)] | .[]
    ' "$transcript_path" 2>/dev/null || true
}

# The same, across the dispatch tree: the primary transcript plus every
# subagent transcript of the same session and working directory.
extract_file_tool_paths_with_subagents() {
    local primary="$1"
    if [ -z "$primary" ] || [ ! -f "$primary" ]; then
        return 0
    fi
    extract_file_tool_paths "$primary"
    local session_dir
    case "$primary" in
        */subagents/agent-*.jsonl)
            session_dir=$(dirname "$(dirname "$primary")")
            ;;
        *)
            session_dir=$(dirname "$primary")
            ;;
    esac
    local subagent_dir="${session_dir}/subagents"
    [ -d "$subagent_dir" ] || return 0
    local primary_cwd
    primary_cwd=$(jq -sr '[.[] | .cwd // empty] | first // empty' "$primary" 2>/dev/null)
    [ -n "$primary_cwd" ] || return 0
    local sub sub_cwd
    for sub in "$subagent_dir"/agent-*.jsonl; do
        [ -f "$sub" ] || continue
        [ "$sub" = "$primary" ] && continue
        sub_cwd=$(jq -sr '[.[] | .cwd // empty] | first // empty' "$sub" 2>/dev/null)
        if [ "$sub_cwd" = "$primary_cwd" ]; then
            extract_file_tool_paths "$sub"
        fi
    done
}

# Tool uses across the dispatch tree.
extract_tool_uses_with_subagents() {
    local primary="$1"
    if [ -z "$primary" ] || [ ! -f "$primary" ]; then
        return 0
    fi
    extract_assistant_tool_uses "$primary"
    local session_dir
    case "$primary" in
        */subagents/agent-*.jsonl)
            session_dir=$(dirname "$(dirname "$primary")")
            ;;
        *)
            session_dir=$(dirname "$primary")
            ;;
    esac
    local subagent_dir="${session_dir}/subagents"
    [ -d "$subagent_dir" ] || return 0
    local primary_cwd
    primary_cwd=$(jq -sr '[.[] | .cwd // empty] | first // empty' "$primary" 2>/dev/null)
    [ -n "$primary_cwd" ] || return 0
    local sub sub_cwd
    for sub in "$subagent_dir"/agent-*.jsonl; do
        [ -f "$sub" ] || continue
        [ "$sub" = "$primary" ] && continue
        sub_cwd=$(jq -sr '[.[] | .cwd // empty] | first // empty' "$sub" 2>/dev/null)
        if [ "$sub_cwd" = "$primary_cwd" ]; then
            extract_assistant_tool_uses "$sub"
        fi
    done
}

# The unique tool names of one transcript.
get_tool_calls() {
    extract_assistant_tool_uses "$1" | sort -u
}

# Whether a tool name appears in a get_tool_calls list.
tool_was_called() {
    local tool_calls="$1"
    local tool_name="$2"
    echo "$tool_calls" | grep -q "^${tool_name}$"
}

# =============================================================================
# Workflow command detection
# =============================================================================

# Every workflow command in the transcript, oldest first, one per line as
# `/name` (e.g. `/pipeline:plan`): typed commands (`<command-name>` stripped)
# and Skill tool uses. Only the matched command is emitted, never the rest of a
# multi-line message.
_workflow_command_texts() {
    local transcript_path="$1"
    if [ -z "$transcript_path" ] || [ ! -f "$transcript_path" ]; then
        return 0
    fi
    jq -sr --arg re "$PIPELINE_COMMAND_RE" '
        [.[] | (.message // .) |
         if .role == "user" then
             ((.content // []) |
              if type == "array" then
                  (.[] | select(.type == "text") | .text // empty)
              elif type == "string" then
                  .
              else empty end) |
             sub("^\\s*<command-name>"; "")
         elif .role == "assistant" then
             ((.content // [])[]? |
              select(.type == "tool_use" and .name == "Skill") |
              "/" + (.input.skill // ""))
         else empty end] |
        map(select(test($re)) | match($re).string | sub("^\\s*"; "")) | .[]
    ' "$transcript_path" 2>/dev/null || true
}

# The latest workflow command (pipeline:plan, work-lite, commit, ...), or empty.
latest_pipeline_command() {
    _workflow_command_texts "$1" | tail -1 | sed -E 's#^/##' || true
}

# Whether the session runs a workflow command; casual conversation does not.
is_pipeline_session() {
    local latest
    latest=$(latest_pipeline_command "$1")
    [ -n "$latest" ]
}

# The active pipeline phase (plan, implement, check, complete), or empty when
# the latest workflow command is not a phase.
detect_active_command() {
    latest_pipeline_command "$1" | grep -oE '^pipeline[-:](plan|implement|check|complete)$' \
        | sed -E 's/^pipeline[-:]//' || true
}
