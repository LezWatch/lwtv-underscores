#!/usr/bin/env python3
"""Classify a PR as high or low risk from its changed files and any new dependencies.

Prints one JSON object:
  {"tier": "high"|"low", "files": [...], "packages": [...], "reason": str}
Always exits 0; the risk-tier workflow decides pass/fail. Any error yields tier "high"
(fail closed), so a broken check holds a PR for review instead of waving it through.

A file is high risk if it matches .github/risk-tiers.txt, or if it matches nothing in the
allowlist .github/low-risk.txt, so anything nobody thought to list defaults to high.
Both files are read at the BASE commit, so a PR can't loosen the lists it is judged by;
the working copy is used only when the base doesn't have a file yet.

Lockfiles (composer.lock, package-lock.json) and manifests (composer.json, package.json)
are on the allowlist, because a version bump of a package we already ship is routine.
A manifest change that does anything else (scripts, overrides, config, a new or removed
dependency, a git/tarball/alias source) is high risk. A package name that appears in a lockfile at head
but not at base is reported under "packages" and makes the PR high risk, transitive
dependencies included: new code is new code, however it arrives.
"""
import argparse
import fnmatch
import json
import posixpath
import subprocess
from pathlib import Path

LOCKFILES = ("composer.lock", "package-lock.json")


def load_patterns(text):
    """Return the non-blank, non-comment lines of a patterns file."""
    patterns = []
    for line in text.splitlines():
        line = line.strip()
        if line and not line.startswith("#"):
            patterns.append(line)
    return patterns


def match_files(files, patterns):
    """Return the sorted files that match any pattern (`*` crosses `/`)."""
    return sorted(f for f in files if any(fnmatch.fnmatchcase(f, p) for p in patterns))


def composer_packages(lock_text):
    """Package names in a composer.lock (runtime and dev)."""
    if not lock_text:
        return set()
    data = json.loads(lock_text)
    return {
        pkg["name"]
        for section in ("packages", "packages-dev")
        for pkg in data.get(section) or []
        if pkg.get("name")
    }


def npm_packages(lock_text):
    """Installed package names in a package-lock.json (lockfileVersion 2 or 3).

    Keys look like `node_modules/a` or `node_modules/a/node_modules/b`; keys without
    `node_modules/` are this repo's own workspaces, and `link` entries point at them, so
    both are skipped. An alias (`"prettier": "npm:wp-prettier@..."`) carries the real
    package in `name`, which is what we record: retargeting an alias to a different
    package then shows up as a new name even though the key didn't change.
    """
    if not lock_text:
        return set()
    data = json.loads(lock_text)
    packages = data.get("packages")
    if packages is None:
        raise ValueError("package-lock.json has no 'packages' map (lockfileVersion 1?)")
    names = set()
    for key, meta in packages.items():
        if "node_modules/" not in key or meta.get("link"):
            continue
        names.add(meta.get("name") or key.rsplit("node_modules/", 1)[1])
    return names


def package_names(path, lock_text):
    base = posixpath.basename(path)
    if base == "composer.lock":
        return composer_packages(lock_text)
    if base == "package-lock.json":
        return npm_packages(lock_text)
    raise ValueError(f"not a lockfile: {path}")


def new_packages(locks):
    """Packages present at head but not at base, as sorted `lockfile: name` strings.

    `locks` maps a lockfile path to (base_text, head_text); either may be "" or None
    when the file doesn't exist at that commit.
    """
    found = []
    for path, (base_text, head_text) in sorted(locks.items()):
        added = package_names(path, head_text) - package_names(path, base_text)
        found += [f"{path}: {name}" for name in sorted(added)]
    return found


SPECIAL_MODES = {"120000", "160000"}  # symlink, submodule (gitlink)


def special_files(raw_diff):
    """Paths that are symlinks or submodules after the change, from `git diff --raw`.

    The deploy rsyncs with -l, which copies symlinks as symlinks. One at a harmless-looking
    path (say under images/) could point at wp-config.php and be served, so the path alone
    can't be trusted.
    """
    found = []
    for line in raw_diff.splitlines():
        if not line.startswith(":") or "\t" not in line:
            continue
        meta, path = line.split("\t", 1)
        fields = meta[1:].split()
        if len(fields) >= 2 and fields[1] in SPECIAL_MODES:
            found.append(path)
    return sorted(found)


def changed_lockfiles(files):
    return sorted(f for f in files if posixpath.basename(f) in LOCKFILES)


MANIFESTS = ("composer.json", "package.json")
DEP_SECTIONS = (
    "dependencies", "devDependencies", "optionalDependencies", "peerDependencies",
    "require", "require-dev",
)
# A version constraint never needs these; a git URL, tarball, `file:`/`link:` path,
# `npm:` alias or `user/repo` shorthand does. Changing a value to or from one of those
# swaps the code source, not the version, so it isn't a bump.
_NON_VERSION_CHARS = (":", "/")


def _is_plain_version(value):
    """A string version constraint that names no source (no URL, path, alias, repo)."""
    return isinstance(value, str) and not any(c in value for c in _NON_VERSION_CHARS)


def changed_manifests(files):
    return sorted(f for f in files if posixpath.basename(f) in MANIFESTS)


def manifest_is_version_bump(base_text, head_text):
    """True when a composer.json/package.json change only edits dependency versions.

    Scripts, overrides, config, repositories, new or removed dependencies, a new or deleted
    manifest, or a version value that isn't a plain constraint all return False. So does
    unparseable JSON: the caller treats False as high risk.
    """
    if not base_text or not head_text:
        return False
    try:
        base, head = json.loads(base_text), json.loads(head_text)
    except ValueError:
        return False
    if not isinstance(base, dict) or not isinstance(head, dict):
        return False
    rest_base = {k: v for k, v in base.items() if k not in DEP_SECTIONS}
    rest_head = {k: v for k, v in head.items() if k not in DEP_SECTIONS}
    # `version` is the project's own version; release PRs bump it alongside deps.
    rest_base.pop("version", None)
    rest_head.pop("version", None)
    if rest_base != rest_head:
        return False
    for section in DEP_SECTIONS:
        old, new = base.get(section) or {}, head.get(section) or {}
        if not isinstance(old, dict) or not isinstance(new, dict) or old.keys() != new.keys():
            return False
        for name, value in new.items():
            if value == old[name]:
                continue
            # Both sides: moving FROM a git source or inline definition to a registry
            # version changes where the code comes from just as much as the reverse.
            if not _is_plain_version(old[name]) or not _is_plain_version(value):
                return False
    return True


def classify(files, patterns, low_patterns, locks=None, special=(), manifests=None):
    """A file is high risk if it matches `patterns` or matches nothing in `low_patterns`.

    The allowlist is what makes this safe by default: a file nobody thought to list (a
    new cron script, a new top-level directory, a new plugin folder) is high risk rather
    than slipping through as low.
    """
    if not patterns:
        raise ValueError("no risk patterns loaded")
    if not low_patterns:
        raise ValueError("no low-risk patterns loaded")
    listed = match_files(files, patterns)
    allowed = set(match_files(files, low_patterns))
    unlisted = sorted(f for f in files if f not in allowed and f not in listed)
    special = sorted(special)
    # Manifests are allowlisted so a pure version bump is low; anything more is high.
    # A changed manifest with no contents supplied counts as edited (fail closed).
    manifests = manifests or {}
    edited = sorted(
        path for path in changed_manifests(files)
        if path not in manifests or not manifest_is_version_bump(*manifests[path])
    )
    hit = sorted(set(listed) | set(unlisted) | set(special) | set(edited))
    packages = new_packages(locks or {})
    reasons = []
    if listed:
        reasons.append(f"{len(listed)} high-risk file(s)")
    if unlisted:
        reasons.append(f"{len(unlisted)} file(s) not on the low-risk list")
    if special:
        reasons.append(f"{len(special)} symlink or submodule path(s)")
    if edited:
        reasons.append(f"{len(edited)} manifest change(s) beyond version bumps")
    if packages:
        reasons.append(f"{len(packages)} new package(s)")
    return {
        "tier": "high" if reasons else "low",
        "files": hit,
        "packages": packages,
        "reason": "; ".join(reasons) or "no high-risk changes",
    }


def _git(*args):
    return subprocess.run(["git", *args], check=True, capture_output=True, text=True).stdout


def _file_at(sha, path):
    """File contents at a commit, or None if it doesn't exist there."""
    try:
        return _git("show", f"{sha}:{path}")
    except subprocess.CalledProcessError:
        return None


def _patterns_at(base, path):
    """A patterns file as of the base commit, or the working copy if the base lacks it."""
    text = _file_at(base, path)
    return text if text is not None else Path(path).read_text()


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base", required=True)
    parser.add_argument("--head", required=True)
    parser.add_argument("--patterns", default=".github/risk-tiers.txt")
    parser.add_argument("--low-patterns", default=".github/low-risk.txt")
    args = parser.parse_args(argv)
    try:
        # --no-renames: rename detection lists only the new path, so moving a high-risk
        # file (say a cron script into docs/) would otherwise evade every pattern. Without
        # it, a rename shows up as a delete of the old path plus an add of the new one.
        diff = _git("diff", "--name-only", "--no-renames", f"{args.base}...{args.head}")
        files = [f for f in diff.splitlines() if f]
        raw = _git("diff", "--raw", "--no-renames", f"{args.base}...{args.head}")
        # Compare against the merge base, like the three-dot diff above, so packages the
        # base branch gained since the PR branched aren't blamed on the PR.
        merge_base = _git("merge-base", args.base, args.head).strip()
        locks = {
            path: (_file_at(merge_base, path) or "", _file_at(args.head, path) or "")
            for path in changed_lockfiles(files)
        }
        manifests = {
            path: (_file_at(merge_base, path) or "", _file_at(args.head, path) or "")
            for path in changed_manifests(files)
        }
        result = classify(
            files,
            load_patterns(_patterns_at(args.base, args.patterns)),
            load_patterns(_patterns_at(args.base, args.low_patterns)),
            locks=locks,
            special=special_files(raw),
            manifests=manifests,
        )
    except Exception as exc:  # fail closed on anything unexpected
        result = {
            "tier": "high",
            "files": [],
            "packages": [],
            "reason": f"risk check failed, treating as high risk: {exc}",
        }
    print(json.dumps(result))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
