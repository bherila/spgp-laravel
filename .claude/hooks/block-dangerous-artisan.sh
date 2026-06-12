#!/usr/bin/env bash
# Claude Code PreToolUse hook: block unsafe Laravel database commands.
# Instructions alone are not an enforcement layer; this guard keeps accidental
# production/staging migrations out of agent sessions.
set -euo pipefail

payload="$(cat || true)"
command=""

if command -v jq >/dev/null 2>&1; then
  command="$(printf '%s' "$payload" | jq -r '.tool_input.command // ""' 2>/dev/null || true)"
fi

# Fallback lets the grep still catch obvious commands if jq is unavailable.
if [[ -z "$command" ]]; then
  command="$payload"
fi

if ! grep -Eq '(^|[;&|[:space:]])(php[[:space:]]+)?artisan[[:space:]]+(migrate|schema:dump)([[:space:]]|$)' <<<"$command"; then
  exit 0
fi

if grep -Eq 'artisan[[:space:]]+migrate([[:space:]]|$)' <<<"$command"; then
  if [[ "$command" != *"--database=sqlite"* || "$command" != *"--no-interaction"* ]]; then
    printf '%s\n' '{"continue":false,"stopReason":"Blocked unsafe migration. Only run migrations when explicitly requested, and use: php artisan migrate --database=sqlite --no-interaction"}'
    exit 0
  fi
fi

if grep -Eq 'artisan[[:space:]]+schema:dump([[:space:]]|$)' <<<"$command"; then
  if [[ "$command" != *"--database=sqlite"* || "$command" == *"--prune"* ]]; then
    printf '%s\n' '{"continue":false,"stopReason":"Blocked unsafe schema dump. Only run schema dumps when explicitly requested, use --database=sqlite, and never use --prune."}'
    exit 0
  fi
fi

exit 0
