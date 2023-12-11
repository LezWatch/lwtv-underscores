## LezWatch.TV Theme - Built on Yikes! Starter Theme

The 100% Original Theme for LezWatch.TV

## Description

Based on the [Yikes!](https://YikesInc.com) Starter Theme, the LezWatch.TV theme has been customized for custom post types (shows, characters, actors), and is fully integrated with the LezWatch.TV Plugin.

## Requirements

- PHP 8.1 or higher
- [Grunt](https://gruntjs.com)
- [Node.js](https://nodejs.org) version 16+

It's recommended to use [Homebrew](https://brew.sh) on macOS or [Chocolatey](https://chocolatey.org) for Windows to install the project dependencies.

## Setup 🛠

1. Clone this repository: `git clone git@github.com:lezwatch/lwtv-underscores`
2. Move into the project directory: `cd lwtv-underscores`
3. Install the project dependencies: `npm install`

## Contributing

All code must pass through the `development` branch which is kept up tp date. As such, any pull requests should be made to **development**, which will push the code automatically to our development server for testing.

1. Using the `development` branch as base, create a new branch with a descriptive name like `fixing-charts` or `fix/chartjs421` or `feature/latest-posts` . Commit your work to that branch until it's ready for full testing
2. Open [a pull request](https://help.github.com/en/desktop/contributing-to-projects/creating-a-pull-request) from your feature branch to the `development` branch.
3. If you are not a main developer, your pull request will be reviewed before it can be merged. If there are issues or changes needed, you may be asked to do so, or they may be done for you.
4. When the code passes review, it will be merged into the `development` branch and can be tested on the dev server.
5. Once the code passes tests, the `development` branch will be merged into `production` and the job is done!

To install and update:

* `$ npm install` - Install all the things.
* `$ npm update` - Updates all the things.
* `$ npm build` - Builds all the CSS and handles composer versions.

Commits are currently not linted by default.

### CSS

If you're updating CSS you have a couple options, since it's all SCSS:

1. `grunt watch` - run grunt and leave open for ongoing changes.
2. `grunt build` - run the build process once.

### Libraries

JS libraries are included via NPM.

The `vendor` and `node_module` files are not synced to Github, to minimize the amount of files stored on the servers.

### Deployment

Pushes to branches are automatically deployed via Github Actions as follows:

* Development: [lezwatchtvcom.stage.site](https://lezwatchtvcom.stage.site) (password required - Ask Mika)
* Production: [lezwatchtv.com](https://lezwatchtv.com)

## Features

* Supports three Custom Post Types and related taxonomies: Characters, Themes, Actors
* Integrated with LWTV Plugin ( `lwtv_plugin()->FUNCTION()` )
* Integrated with [FacetWP](https://facetwp.com), [Jetpack Instant Search](https://jetpack.com/support/search/), and [CMB2](https://cmb2.io/).
* Additional custom image sizes: Show (960x400), Character (225x300), Actor (225x300)
* Additional custom sidebars: Show, Character, and Actor Archives
* Widgets: Display latest custom post type posts (show and character) with image
