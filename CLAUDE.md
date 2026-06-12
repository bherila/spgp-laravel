# CLAUDE.md

@AGENTS.md

## Claude Code session rules

- Treat `AGENTS.md` as the project contract and `TESTING.AGENTS.md` as the validation contract.
- Keep private workstation preferences out of committed files; use a local, gitignored override if needed.
- Because this repo currently lacks a PR CI workflow, run the relevant `TESTING.AGENTS.md` gate before pushing or merging.
- Do not run migrations or schema dumps unless the user explicitly asks. The committed hook also blocks unsafe forms of those commands.
- Prefer `gh` CLI for GitHub work and fully-qualified references such as `bherila/spgp-laravel#123`.
