# CLAUDE.md

@AGENTS.md

## Claude Code overlay

This file is intentionally small. `AGENTS.md` is the cross-agent source of truth for architecture, commands, validation, safety rules, and workflow.

- Prefer targeted context: `git diff --name-only`, `rg`, relevant tests, and neighboring files before broad scans.
- Use subagents only for independent high-volume exploration or parallel implementation; require concise findings and changed-file summaries.
- Use Laravel Boost `search-docs` before changing Laravel ecosystem behavior when the tool is available.
- Because this repo deploys from `main`, do not push or merge without running the affected-stack gates from `AGENTS.md`.
- Do not run migrations, schema dumps, production deploy commands, or broad destructive operations unless the user explicitly asks.
