# LezWatch.TV Custom Theme

The 100% Original Theme for LezWatch.TV

<!-- Badges -->
<p align="center">
	<a href="https://wordpress.org"><img src="https://img.shields.io/badge/wordpress-7.2-blue" alt="WordPress"></a>
	<a href="https://github.com/LezWatch/lwtv-underscores/issues"><img alt="Issues" src="https://img.shields.io/github/issues/LezWatch/lwtv-underscores"></a>
	<a href="https://github.com/LezWatch/lwtv-underscores/pulls"><img alt="PRs" src="https://img.shields.io/github/issues-pr-raw/LezWatch/lwtv-underscores"></a>
	<a href="https://github.com/LezWatch/lwtv-underscores/blob/production/LICENSE"><img src="https://img.shields.io/github/license/LezWatch/lwtv-underscores" alt="License"></a>
</p>

## Description

Based on the Yikes! Starter Theme (circa 2018) and Underscores (circa 2016), the LezWatch.TV theme has been customized for custom post types (shows, characters, actors) and to be as queer as possible.

Usage documentation can be found at [docs.lezwatchtv.com](https://docs.lezwatchtv.com)

- Uses Node to install and manage components, as well as to build final versions of Blocks.
- Uses Composer for adding project dependencies.
- Includes automated build and deploy pipelines to servers using Github actions

## Scheduled Actions

Server crontab is used to run CLI commands that generate and update complex content.

The command `wp lwtv generate cron daily` will run a different debugger each day, update the FacetWP cache, and so on.

## Tools

- PHP 8.4 or higher
- [Node.js](https://nodejs.org) version 22+
- [NVM](https://github.com/nvm-sh/nvm)

It's recommended to use [Homebrew](https://brew.sh) on macOS or [Chocolatey](https://chocolatey.org) for Windows to install the project dependencies.

## Setup 🛠

1. Clone this repository: `git clone git@github.com:lezwatch/lwtv-underscores`
2. Move into the project directory: `cd lwtv-underscores`
3. Set NPM to the correct version: `nvm use`
4. Install the project dependencies: `npm install`
5. Update all the things (npm, composer, etc): `npm run updater`
6. Run an initial build (generate CSS etc): `npm run build`

## Contributing 📺

All pull requests should be made to **production**.

### Building Releases

If you're fixing a one-off issue, create a new branch with a descriptive name like `fixing-charts` or `fix/chartjs421` or `feature/latest-posts` using `production` as your base. You will commit your work to that branch until it's ready for full testing.

If you're working on a longer feature, it's best to name a branch like `release/x.x.x`. You can merge all your smaller branches and PRs into the release branch.

Any time you update CSS or JS, you will need to re-build the code:

- `$ npm run build` will build all the CSS and JS, as well as update all the libraries.
- `$ npm run buildquick` will build the assets and copy files over _without_ updating libraries.

### Testing Releases

When you think your release is ready, first run linting and testing:

- `$ npm run test` - This will run all the lint checks as well as the PHP tests and Composer.

To fix lint issues automatically:

- `$ npm run fix` - Fix everything
- `$ npm run fix:css` - Fix all SCSS files in all folders
- `$ npm run fix:js` - Fix all Block JS files only

### Pushing Releases

Once the code is ready you can either open [a pull request](https://help.github.com/en/desktop/contributing-to-projects/creating-a-pull-request) directly from your feature branch to the `production` branch OR you can ask your IDE to run the `lwtv-release` skill (see `.agents/skills/lwtv-release/SKILL.md`) which will auto update the version.

If you choose to manually build the release PR, then you will need to do the following:

1. Update the version in `package.json` (a small release would bump the third number - 7.5.0 to 7.5.1)
2. Update `CHANGELOG.md` and add in the new release, the date, and a brief summary of what changed.
3. Run the build command: `npm run build` (this will update the version numbers)

In either case, run a push to development (`npm run merge-to-develop`) to update the staging site. If this is a success, you're good to go. An admin will need to review the PR and merge it.

**Note!** If the review tool flags a PR as `high-risk` then a human will need to add the tag `human-reviewed` in order for it to pass muster.

Pushes to branches are automatically deployed via Github Actions as follows:

- Development: [lezwatchtvcom.stage.site](https://lezwatchtvcom.stage.site) (password required - Ask Mika)
- Production: [lezwatchtv.com](https://lezwatchtv.com)

## 3rd Party Features

### Libraries

JS and PHP libraries are included via NPM and Composer. WordPress plugins that have been forked are now included in the main code and managed by us to prevent breakage.

### Icons

In order to speed up the site, we make use of SVG sprites to generate our font-icons. The icons can be found in the private [Symbolicons Repository](https://github.com/lezwatch/symbolicons-private/). When a change is pushed to development and production branches, it will auto-deploy.

## Theme Features

- Supports three front facing Custom Post Types and related taxonomies: Characters, Themes, Actors
- Supports one back end Custom Post Type: TV Maze Aliases
- Integrated with [ACF Pro](https://www.advancedcustomfields.com/pro/)
- Integrated with [FacetWP](https://facetwp.com)
- Additional custom image sizes: Show (960x400), Character (225x300), Actor (225x300)
- Additional custom sidebars: Show, Character, and Actor Archives
- Widgets: Display latest custom post type posts (show and character) with image
- Internal plugins (`/plugins/`)

## Plugins

As everything is interconnected, we include some required plugins with the theme.

### LWTV Plugin (`/plugins/lwtv-plugin/`)

For the plugin, we use Namespaces and auto-loading in order to properly generate and call new content dynamically.

#### Components

A _component_ can be thought of as a _sub-plugin_. It is an atomic, independent module that stores business logic related to a specific feature. It may expose some template tags (functions) that can be called from within the theme.

All Components are stored in `/plugins/lwtv-plugins/php/` and the details of their use in `/plugins/lwtv-plugins/php/readme.md`

#### Blocks

Development is fully documented in `/plugins/lwtv-plugins/php/blocks/README.md`

### Developer Features

There is a function for logging to the error log _only_ if debug is active: `lwtv_plugin()->debug_log( $TYPE, $MESSAGE )`

This will output `[TYPE]: Message` into the error log.

## Miscellaneous

The following folders/files are for use by Developers. They are not pushed to the dev nor production servers.

- `/_build_scripts/` - All of our build scripts and tools.
- `/.agents/` - Shared skills for LLM Agents
- `/.claude/` - Claude specific settings
- `/.gemini/` - Google Gemini features
- `./.githooks/` - shared hooks (pre-commit etc)
- `./.github/` - all Github specific files such as workflows, dependabot, and pull request templates
- `/.phpunit.cache/` - cached runs for PHPUnit.
- `/.vscode/` - default VSCode settings
- `/cron/` - Copy of all Cron commands
- `/docs/` - Structure files
- `/tests/` - PHPUnit testing docs
- `.editorconfig` - Basic editor configuration
- `.gitignore` - Files and folders to be exempt from Git syncs
- `.gitleaks.toml` - Rules for the `.github/workflows/ci.yml` security job
- `.npmrc` - NPM configuration requirements
- `.nvmrc` - NVM version control
- `composer.json` - Composer settings, includes all libraries used
- `package-lock.json` - Saved package.json data
- `package.json` - NPM configuration, commands, and libraries used
- `phpcs.xml.dist` - PHPCS configuration
