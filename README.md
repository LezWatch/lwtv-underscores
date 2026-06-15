# LezWatch.TV Custom Theme

The 100% Original Theme for LezWatch.TV

<!-- Badges -->
<p align="center">
	<a href="https://wordpress.org"><img src="https://img.shields.io/badge/wordpress-7.0-blue" alt="WordPress"></a>
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

1. Using the `production` branch as base, create a new branch with a descriptive name like `fixing-charts` or `fix/chartjs421` or `feature/latest-posts` . Commit your work to that branch until it's ready for full testing
2. Open [a pull request](https://help.github.com/en/desktop/contributing-to-projects/creating-a-pull-request) from your feature branch to the `production` branch.
3. If you are not a main developer, your pull request will be reviewed before it can be merged. If there are issues or changes needed, you may be asked to do so, or they may be done for you.
4. When the code passes review, run `npm run merge-to-develop` to push it to **development** (no extra PR needed).
5. Once the code passes tests and is approved, the branch can be merged into `production` and the job is done!

If you need to update the theme version (due to changing CSS or adding features) edit the `package.json` file and change the version. When `npm run build` runs, it will automagically update all needed files.

To install and update:

- `$ npm install` - Install all the things.
- `$ npm run updater` - Updates all the things.
- `$ npm run build` - Builds all the CSS and handles composer versions.

### Linting 🧪

To run linting:

- `$ npm run lint` - Lint everything
- `$ npm run lint:css` - Lint all SCSS files in all folders
- `$ npm run lint:js` - Lint all Block JS files only
- `$ npm run lint:php` - Lint all PHP files (excludes 3rd party plugins)

To fix lint issues automatically:

- `$ npm run fix` - Fix everything
- `$ npm run fix:css` - Fix all SCSS files in all folders
- `$ npm run fix:js` - Fix all Block JS files only

### CSS & JS

`$ npm run build` will build all the CSS and JS, as well as update all the libraries.

`$ npm run buildquick` will build the assets and copy files over _without_ updating libraries.

### Libraries

JS and PHP libraries are included via NPM and Composer. WordPress plugins that have been forked are now included in the main code and managed by us to prevent breakage.

### Icons

In order to speed up the site, we make use of SVG sprites to generate our font-icons. The icons can be found in the private [Symbolicons Repository](https://github.com/lezwatch/symbolicons-private/). When a change is pushed to development and production branches, it will auto-deploy.

If the sprites are missing, the site falls back to Font Awesome.

### Deployment

Pushes to branches are automatically deployed via Github Actions as follows:

- Development: [lezwatchtvcom.stage.site](https://lezwatchtvcom.stage.site) (password required - Ask Mika)
- Production: [lezwatchtv.com](https://lezwatchtv.com)

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

### Forked Plugins

The following plugins are forked from their original versions to support newer versions of WordPress and PHP. They are stored in the `/plugins/` folder:

- `shadow-taxonomy` - By [PatelUtkarsh](https://github.com/patelutkarsh/shadow-taxonomy)

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
- `/.cursor/` - Cursor specific settings
- `./.github/` - all Github specific files such as workflows, dependabot, and pull request templates
- `./.githooks/` - shared hooks (pre-commit etc)
- `/.vscode/` - default VSCode settings
- `/docs/` - Structure files
- `.editorconfig` - Basic editor configuration
- `.gitignore` - Files and folders to be exempt from Git syncs
- `.npmrc` - NPM configuration requirements
- `.nvmrc` - NVM version control
- `composer.json` - Composer settings, includes all libraries used
- `package-lock.json` - Saved package.json data
- `package.json` - NPM configuration, commands, and libraries used
- `phpcs.xml.dist` - PHPCS configuration
