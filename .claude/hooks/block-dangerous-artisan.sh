#!/usr/bin/env bash
# Claude Code PreToolUse hook: block unsafe Laravel database commands.
#
# Advisory rules live in AGENTS.md/CLAUDE.md; this hook is the enforcement layer
# for the most dangerous local agent mistakes. It denies the individual Bash
# tool call (PreToolUse permissionDecision=deny) rather than halting the session,
# so the agent can retry with the only permitted form:
#   php artisan migrate --database=sqlite --no-interaction
# Destructive variants (migrate:fresh/refresh/reset/rollback, db:wipe) and
# schema:dump --prune are never allowed here. This is defense-in-depth, not a
# sandbox: the documented rules + human oversight remain the primary guard.
set -euo pipefail

payload="$(cat || true)"
command=""
if command -v jq >/dev/null 2>&1; then
  command="$(printf '%s' "$payload" | jq -r '.tool_input.command // ""' 2>/dev/null || true)"
fi
if [[ -z "$command" ]]; then
  command="$payload"
fi

deny() {
  printf '%s\n' "{\"hookSpecificOutput\":{\"hookEventName\":\"PreToolUse\",\"permissionDecision\":\"deny\",\"permissionDecisionReason\":\"$1\"}}"
  exit 0
}

# Bash strips quotes before argv reaches artisan, so normalize quotes to spaces;
# this exposes quoted forms like 'migrate' or --database "mysql".
norm="${command//\'/ }"
norm="${norm//\"/ }"

# Tokenize and classify any Artisan subcommand that appears after an `artisan`
# token (resetting at shell separators). Scanning every following token catches
# space-separated global option values (e.g. `--env production migrate`) that a
# positional regex misses, and quoted subcommands once quotes are normalized.
read -r mig dest dump < <(printf '%s\n' "$norm" | awk '
  BEGIN { seen=0; mig=0; dest=0; dump=0 }
  {
    for (i=1; i<=NF; i++) {
      t=$i
      if (t=="&&"||t=="||"||t==";"||t=="|"||t=="&") { seen=0; continue }
      if (t=="artisan" || t ~ /\/artisan$/) { seen=1; continue }
      if (seen) {
        if (t=="migrate") mig=1
        else if (t=="migrate:fresh"||t=="migrate:refresh"||t=="migrate:reset"||t=="migrate:rollback"||t=="db:wipe") dest=1
        else if (t=="schema:dump") dump=1
      }
    }
  }
  END { print mig, dest, dump }
')

if [[ "$mig" == 0 && "$dest" == 0 && "$dump" == 0 ]]; then
  exit 0
fi

# Destructive subcommands are never allowed via the agent, even on sqlite.
if [[ "$dest" == 1 ]]; then
  deny "Blocked destructive migration command (migrate:fresh/refresh/reset/rollback or db:wipe). These drop or rewrite data and are never run automatically here; a human must run it deliberately if it is truly required."
fi

# Collect every --database value (handles =value and space value; quotes already
# normalized) so repeated/conflicting flags cannot smuggle a non-sqlite target.
has_sqlite_db=false
has_non_sqlite_db=false
while IFS= read -r v; do
  [[ -z "$v" ]] && continue
  if [[ "$v" == "sqlite" ]]; then has_sqlite_db=true; else has_non_sqlite_db=true; fi
done < <(printf '%s\n' "$norm" | grep -oE -- '--database[= ]+[^ ]+' | sed -E 's/^--database[= ]+//' || true)

no_interaction=false
[[ "$norm" == *"--no-interaction"* ]] && no_interaction=true

if [[ "$mig" == 1 ]]; then
  if ! $has_sqlite_db || $has_non_sqlite_db || ! $no_interaction; then
    deny "Blocked unsafe migration. Only run migrations when explicitly requested, against SQLite only and non-interactively: php artisan migrate --database=sqlite --no-interaction. No non-sqlite --database is allowed."
  fi
fi

if [[ "$dump" == 1 ]]; then
  if ! $has_sqlite_db || $has_non_sqlite_db || [[ "$norm" == *"--prune"* ]]; then
    deny "Blocked unsafe schema dump. Only run when explicitly requested, use --database=sqlite, and never use --prune."
  fi
fi

exit 0
