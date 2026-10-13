---
name: lwtv-release
description: Cut a LezWatch.TV theme release PR — runs rad-shipshape-pr's prepare phase, then `npm run buildquick` so the version in package.json is stamped into style.css, style.min.css, functions.php and scss/_dynamic.scss, then ships with the build output in the release commit. Use when the user says lwtv-release, "cut the release", "ship 7.x.y", or wants a release PR for this theme.
---

# LWTV Release

A thin wrapper around `rad-shipshape-pr`. ShipShape bumps `package.json` (the only real
version source here) and writes the changelog; this wrapper runs the asset build in between so
the generated version strings match before the commit.

Invocation: `lwtv-release [<version>] [shipshape flags]`. Pass the version and any ShipShape
flags (`--no-bump`, `--no-changelog`, `--no-testing`, `--draft`, …) straight through. Don't
pass `--phase` or `--include`; this skill sets those.

## Steps

### 1. Prepare

Invoke `rad-shipshape-pr` with the user's arguments plus `--phase prepare`. Follow it fully,
including its discovery confirmation (the config in `.claude/shipshape.json` usually answers
it). If prepare stops for any reason, stop here too.

### 2. Build

```bash
cd "$(git rev-parse --show-toplevel)"
source ~/.nvm/nvm.sh && nvm use
npm run buildquick
```

Use `buildquick`, **not** `npm run build`: the full build runs `composer update`, which would
pull PHP dependency upgrades into the release commit.

If the build fails, stop and report the error. Don't ship, and don't try to fix the build.

### 3. Collect build output

```bash
cd "$(git rev-parse --show-toplevel)"
source "$(git rev-parse --absolute-git-dir)/shipshape/env"
{ git -c core.quotePath=false diff --name-only HEAD; git -c core.quotePath=false ls-files --others --exclude-standard; } \
  | sort -u | comm -23 - <(sort -u "$STATE/files")
```

These are the files the build changed. Expect a subset of:

- `functions.php`
- `scss/_dynamic.scss`
- `style.css`
- `style.min.css`

Anything outside that list (for example `inc/js/*.min.js`, or a file the build shouldn't
touch) → show it to the human and ask whether it belongs in the release commit before going
on. Never revert, stash or delete it yourself.

If nothing changed (the files were already current), there's nothing to include.

### 4. Ship

Invoke `rad-shipshape-pr --phase ship` with one `--include <path>` per file from step 3 that
the human didn't exclude. Mention in the PR body's Notes that the build output is included.

### 5. Report

ShipShape's report, plus the list of build files included.
