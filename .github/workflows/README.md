# GitHub Actions

- `ci.yml` runs on pull requests and manual dispatch. Manual dispatch runs all checks. On pull requests, path filters select frontend and backend checks, including frontend module scripts (`.mjs`/`.cjs`) and package-manager configuration. Both paths build Vite assets; backend checks include Pint and Paratest. `CI result` remains the required aggregate check.
- `deploy.yml` runs the full frontend and backend checks, including Pint, before deploying pushes to `main` or a manual dispatch. Deployments are serialized and use the protected `prod` environment.
- `dependency-audit.yml` audits Composer and pnpm lockfiles every Monday and on manual dispatch. It uploads full reports, fails on Composer advisories, and rejects production JavaScript advisories or high/critical development advisories. Moderate development advisories remain visible as warnings. Weekly grouped dependency update PRs are configured in `../dependabot.yml`.
- `copilot-setup-steps.yml` prepares PHP, Composer, Node.js, and pnpm dependencies for Copilot.

The workflows use PHP 8.5 and Node.js 24 where needed. pnpm uses the version pinned in `package.json`; CI and deployment cache its store through `actions/setup-node`.

## Dependency monitoring

Dependabot checks Composer, JavaScript, and GitHub Actions every Monday. Composer and JavaScript minor/patch updates are grouped by ecosystem; major updates get separate PRs so compatibility changes can be reviewed independently. TypeScript 7 and ESLint 10 currently exceed the supported peer ranges of `ts-jest` and `eslint-plugin-react`, respectively.

The audit workflow reads lockfiles without installing project dependencies or running their scripts. Composer advisories fail the workflow. JavaScript production advisories at any severity and development advisories at high/critical severity also fail. Lower-severity development advisories appear in the log summary and as a warning; full JSON reports are retained as artifacts for 30 days. Registry failures and invalid reports fail rather than being treated as clean audits. The current moderate `sprintf-js` advisory, `GHSA-hp3w-g68c-fv3c`, is transitive through Jest and has no published fix.

[GitHub supports Dependabot version updates for pnpm 12](https://github.blog/changelog/2026-10-20-dependabot-now-supports-npm-12-pnpm-11-and-12-and-bun-1-4/), using the `npm` ecosystem identifier. Its dependency graph does not yet parse pnpm 12's default two-document lockfile, so Dependabot alerts and security update PRs are unavailable for those dependencies. The scheduled pnpm audit provides independent advisory coverage while this limitation remains.

## Deployment setup

Create the GitHub Environment `prod` under **Settings → Environments** and configure these environment secrets:

- `SSH_HOST`: Deployment server hostname or IP address.
- `SSH_USERNAME`: SSH login user.
- `SSH_PRIVATE_KEY`: A dedicated private SSH key for the deployment user, preferably Ed25519.
- `SSH_KNOWN_HOSTS`: The verified, pinned host key entry for the server, in `known_hosts` format.

The deployment user needs write access to `~/laravel-spgp/`. The workflow installs production Composer dependencies, builds frontend assets, transfers the application there, and runs the existing remote Artisan tasks. It activates `~/spgp.vxcv.com` and `~/web` as links to `~/laravel-spgp/public`, then verifies the production response.
