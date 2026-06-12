#!/usr/bin/env bash
# Claude Code PreToolUse hook: block unsafe Laravel database commands.
#
# Advisory rules live in AGENTS.md/CLAUDE.md; this hook is the enforcement layer
# for the most dangerous local agent mistakes. It denies the individual Bash
# tool call (via PreToolUse permissionDecision=deny) rather than halting the
# whole session, so the agent can retry with the documented safe form.
set -euo pipefail

payload="$(cat || true)"
command=""

if command -v jq >/dev/null 2>&1; then
  command="$(printf '%s' "$payload" | jq -r '.tool_input.command // ""' 2>/dev/null || true)"
fi

# Fallback lets grep catch obvious commands when jq is unavailable.
if [[ -z "$command" ]]; then
  command="$payload"
fi

deny() {
  # PreToolUse denial: deny just this Bash call and return the reason to Claude
  # (continue:false would instead stop the whole turn and hide the reason).
  printf '%s\n' "{\"hookSpecificOutput\":{\"hookEventName\":\"PreToolUse\",\"permissionDecision\":\"deny\",\"permissionDecisionReason\":\"$1\"}}"
  exit 0
}

# Allow global options (e.g. --env=production, -v) to appear between `artisan`
# and the subcommand, and catch destructive migrate variants + db:wipe.
opts='([[:space:]]+-{1,2}[A-Za-z0-9][A-Za-z0-9=:._-]*)*'
migrate_cmds='(migrate|migrate:fresh|migrate:refresh|migrate:reset|migrate:rollback|db:wipe)'

migrate_re="artisan${opts}[[:space:]]+${migrate_cmds}([[:space:]]|$)"
schema_re="artisan${opts}[[:space:]]+schema:dump([[:space:]]|$)"

is_migrate=false
is_schema_dump=false
if grep -Eq "$migrate_re" <<<"$command"; then is_migrate=true; fi
if grep -Eq "$schema_re" <<<"$command"; then is_schema_dump=true; fi

if ! $is_migrate && ! $is_schema_dump; then
  exit 0
fi

# Any --database value (--database=x or --database x) that is not sqlite is
# disqualifying — this also rejects repeated/conflicting flags such as
# `--database=sqlite --database=mysql`, where the console uses the last value.
has_non_sqlite_db=false
while IFS= read -r dbval; do
  if [[ -n "$dbval" && "$dbval" != "sqlite" ]]; then has_non_sqlite_db=true; fi
done < <(grep -oE -- '--database[=[:space:]]+[^[:space:]"]+' <<<"$command" | sed -E 's/^--database[=[:space:]]+//' || true)

if $is_migrate; then
  if [[ "$command" != *"--database=sqlite"* ]] || $has_non_sqlite_db || [[ "$command" != *"--no-interaction"* ]]; then
    deny "Blocked unsafe migration. Only run migrations when explicitly requested, against SQLite only and non-interactively: php artisan migrate --database=sqlite --no-interaction. Destructive variants (migrate:fresh/refresh/reset/rollback, db:wipe) and any non-sqlite --database are not allowed here."
  fi
fi

if $is_schema_dump; then
  if [[ "$command" != *"--database=sqlite"* ]] || $has_non_sqlite_db || [[ "$command" == *"--prune"* ]]; then
    deny "Blocked unsafe schema dump. Only run when explicitly requested, use --database=sqlite, and never use --prune."
  fi
fi

exit 0
