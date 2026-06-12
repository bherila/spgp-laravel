#!/usr/bin/env bash
# Claude Code PreToolUse hook: block unsafe Laravel database commands.
#
# Advisory rules live in AGENTS.md/CLAUDE.md; this hook is the enforcement layer
# for the most dangerous local agent mistakes. It denies the individual Bash
# tool call (PreToolUse permissionDecision=deny) rather than halting the session,
# so the agent can retry with the only permitted form:
#   php artisan migrate --database=sqlite --no-interaction
#
# Design: normalize quotes, split the command into segments on shell separators
# and redirections, then validate EACH artisan segment against its OWN flags.
# Any migrate:* / schema:d* / db:wi* subcommand (including Symfony command-prefix
# abbreviations such as migrate:ref, schema:du, db:wi) is treated as destructive
# and always denied; only a plain `migrate` with --database=sqlite and
# --no-interaction (and no conflicting --database) is allowed.
#
# This is defense-in-depth for a cooperative agent, not a sandbox: indirection
# such as eval, base64, $(...), or env-var assembly can still reach the shell and
# is intentionally out of scope — the documented rule + human oversight remain
# the primary guard.
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

# Bash strips quotes before argv reaches artisan; normalize them to spaces so
# quoted forms ('migrate', --database "mysql") are seen the same as bare ones.
norm="${command//\'/ }"
norm="${norm//\"/ }"

verdict="$(printf '%s' "$norm" | awk '
  { all = all $0 "\n" }
  END {
    # Split into command segments on shell separators / redirections / subshells
    # so each artisan call is validated with only its own options.
    gsub(/\$\(/, "\n", all)
    gsub(/[;|&<>()`]/, "\n", all)
    nseg = split(all, segs, "\n")

    dest = 0; bad_migrate = 0; bad_schema = 0

    for (s = 1; s <= nseg; s++) {
      m = split(segs[s], tok, /[ \t]+/)
      seen = 0; danger = ""; sqlite = 0; nonsqlite = 0; nointer = 0; prune = 0; expectdb = 0
      for (i = 1; i <= m; i++) {
        t = tok[i]
        if (t == "") continue
        if (t == "artisan" || t ~ /\/artisan$/) { seen = 1; continue }
        if (!seen) continue
        if (expectdb) { if (t == "sqlite") sqlite = 1; else nonsqlite = 1; expectdb = 0; continue }
        if (t == "--no-interaction") { nointer = 1; continue }
        if (t == "--prune") { prune = 1; continue }
        if (t ~ /^--database=/) { v = substr(t, 12); if (v == "sqlite") sqlite = 1; else nonsqlite = 1; continue }
        if (t == "--database") { expectdb = 1; continue }
        # Destructive: any migrate:* subcommand, or db:wipe (and its abbreviations).
        if (t ~ /^migrate:/ || t ~ /^db:wi/) { danger = "dest"; continue }
        # Plain migrate (the only form that can be allowed).
        if (t == "migrate") { if (danger == "") danger = "migrate"; continue }
        # schema:dump and its abbreviations (schema:d, schema:du, ...).
        if (t ~ /^schema:d/) { if (danger == "") danger = "schema"; continue }
      }
      if (!seen || danger == "") continue
      if (danger == "dest") dest = 1
      else if (danger == "migrate") { if (!(sqlite && !nonsqlite && nointer)) bad_migrate = 1 }
      else if (danger == "schema") { if (!(sqlite && !nonsqlite && !prune)) bad_schema = 1 }
    }

    if (dest) print "DENY_DEST"
    else if (bad_migrate) print "DENY_MIGRATE"
    else if (bad_schema) print "DENY_SCHEMA"
    else print "OK"
  }
')"

case "$verdict" in
  DENY_DEST)
    deny "Blocked destructive migration command (migrate:fresh/refresh/reset/rollback/install, db:wipe, or an abbreviation of one). These drop or rewrite data and are never run automatically here; a human must run it deliberately if truly required." ;;
  DENY_MIGRATE)
    deny "Blocked unsafe migration. Only run migrations when explicitly requested, against SQLite only and non-interactively: php artisan migrate --database=sqlite --no-interaction. Each chained artisan call must carry the flags itself, and no non-sqlite --database is allowed." ;;
  DENY_SCHEMA)
    deny "Blocked unsafe schema dump. Only run when explicitly requested, use --database=sqlite, and never use --prune." ;;
esac

exit 0
