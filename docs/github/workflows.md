# Github Workflows

All workflows live in `./.github/workflows/`. Every third-party action is pinned to a commit SHA with the version in a trailing comment; Dependabot's `github-actions` updates keep both current.

## Pull request gates

These run on every PR into `development` or `production`. With branch protection on (see below), a PR can't merge until they pass.

### CI (`ci.yml`)

Two jobs:

- **`lint-test`** runs the PR's code with a read-only token: `npm ci`, `composer validate` (lockfiles match manifests), `npm run lint` (PHPCS, ESLint, Stylelint), PHPStan (`composer analyse`), PHPUnit (`npm test`), the asset build, and the gate's own Python tests.
- **`security`** runs no PR code: `composer audit` (theme and plugin), `npm audit` (runtime deps, high and critical only), and gitleaks over only the commits the PR adds.

CI also runs on every push to `development` and `production`.

### Risk tier (`risk-tier.yml`)

`.github/scripts/risk_tier.py` sorts every PR into **low** or **high** risk. Low passes. High fails until Mika reviews it.

A PR is **high** if any of these is true:

- It touches a path in `.github/risk-tiers.txt`: CI and deploy, cron scripts, ACF JSON, bootstrap files, REST, WP-CLI, schedulers, lint config, agent instructions, SVGs, and so on.
- It touches a path that isn't in `.github/low-risk.txt`. Anything nobody thought to list is high by default. That includes most plugin PHP.
- It adds a symlink or submodule (the deploy rsync copies symlinks).
- A lockfile gains a package that wasn't there before, including transitive packages.
- A `composer.json` or `package.json` change goes beyond version numbers: scripts, overrides, config, new or removed dependencies, git or tarball sources.

A plain Dependabot version bump is low.

Both lists are read from the PR's **base** commit, so a PR can't loosen the rules it's judged by. Change the lists in their own PR, which will itself be high risk.

**To approve a high-risk PR:** read it, then add the `human-reviewed` label. The workflow records a `human-review` commit status on the head SHA and re-runs the gate. The status belongs to that commit, so any new push starts over, and the stale label is removed automatically.

Only logins in the `HUMAN_REVIEWERS` repository variable count (Settings → Secrets and variables → Actions → Variables; comma-separated). A label from anyone else, including a bot or an agent's token, is removed, and nothing is recorded.

Known limits:

- On PRs from **forks**, GitHub gives the workflow a read-only token, so the label can't record a status. Review those by pushing the branch to this repo, or merge with admin override.
- On **Dependabot** PRs, label edits made by the workflow can fail. The gate decision still works, because it reads the status, not the labels.

### Branch protection

Feature branches are merged into `development` by hand for testing. When a feature is ready, it gets a PR from the feature branch into `production`. The gates run on that PR, and the branch is deleted once it merges (see below).

Create these in Settings → Rules → Rulesets.

**`production`** branch ruleset:

- Require a pull request before merging.
- Require status checks to pass: `lint-test`, `security`, `risk-tier`.
- Block force pushes.
- Restrict deletions.

**`development`** branch ruleset:

- Block force pushes.
- Restrict deletions.

Don't require PRs or checks on `development`, because that would break the manual merge. CI still runs on every push to `development`, so a broken merge shows up red before the production PR.

Dependabot targets `production` directly, so its PRs are compared against what's actually live and never pick up unreleased changes from `development`. They go through the same gates as any other production PR, and a plain version bump is low risk, so it passes without a label.

### Delete merged branch (`cleanup-branches.yml`)

This workflow deletes a PR's head branch, but only after the PR merges into `production`, so a feature branch survives being merged into `development` for testing. Dependabot's branches are cleaned up the same way, since they also target `production`.

The workflow never deletes `development` or `production`, a branch from a fork, or a branch that's still the head of another open PR.

GitHub's own **Automatically delete head branches** setting (Settings → General → Pull Requests) must stay **off**. It deletes the head branch after every merged PR, whatever the base.

Also add **Restrict deletions** to the rulesets for `development` and `production`. That way, no setting or workflow can remove the long-lived branches.

### OSV-Scanner (`osv-scanner-pr.yml`, `osv-scanner-scheduled.yml`)

This scans dependencies on PRs to `production` and weekly. It reports to the Security tab and isn't a required check.

## Deploys

Two workflows automate pushing code to staging (aka development) and production.

Each flow is very similar and contains the following steps:

1. Checkout the code
2. Confirm the workspace
3. Setup node
4. Setup composer and PHP
5. Install dependencies
6. Run lint
7. Build the code - this will regenerate CSS and update versions if needed
8. Rsync the code to the server
9. Call the symbolicons repository to push the new images
