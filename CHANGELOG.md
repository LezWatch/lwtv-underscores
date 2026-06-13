# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).


## [6.2.8] — 2024-12-23

- Updated FontAwesome to latest release
- Bumped NPM audit dependencies

-----

## [6.2.7] — 2024-12-10

### Added

- Distinct letter-based avatars for visiting (non-gravatar) commenters — color generated from username, initials displayed

### Fixed

- Dark mode CSS for comment sections was broken
- Theme update blocker was not functioning correctly
- Mobile CSS layout fixes

### Improved

- WikiData checker improvements and QID saving
- Admin display fixes for avatar feature

-----

## [6.2.6] — 2024-12-02

### Added

- WikiData sidebar metabox for actors, converted to Gutenberg block with a refresh button
- New WikiData API endpoint for checking actor data
- Support for displaying QID on actor edit pages
- Auto-draft check improvements: page now auto-refreshes when a name is entered

### Fixed

- Deprecated SASS `@import` rules replaced with `@use` (breaking change in SASS; this resolves build warnings and CSS output inconsistencies)
- WikiData metabox: fixed rare bug where characters aren’t an array
- Facebook check made smarter; metabox now only runs on actor post type

### Improved

- Version references consolidated and cleaned up across files
- Bootstrap TableSorter renamed to remove version from filename
- Block version bumped
- NPM security audit fixes

-----

## [6.2.5] — 2024-12-02

### Added

- Actor modals now update the browser URL so individual stats/articles sections are directly linkable; URL resets on close
- QID displayed on actor WikiData edit panel

### Fixed

- CLI output when running the daily cron
- Actor debugger call corrected
- TVMaze updater now properly flushes cache after update

### Improved

- CSS build corrected so webpack output is cleaner
- Added `SECURITY.md` and `CONTRIBUTING.md`
- JS search box documented

-----

## [6.2.4] — 2024-11-25

### Added

- Weekly grid view and monthly calendar view for the TV calendar
- Tab-based view switching with URL persistence via JavaScript (view survives page navigation)
- Subnav to jump to days within the weekly view
- Responsive and dark mode support for calendar
- TVMaze IDs now generated and saved on show/actor save for faster calendar lookups
- FacetWP: shows can now be sorted by newest/oldest air date (fixes long-standing request)
- TMDB ID auto-generation for shows and actors from IMDB data
- Threads support added to actor and author social links
- Editor tool: “Queer Flag” field replaced post type dropdown with a simpler Yes/No field
- Header search input now auto-focuses when the magnifying glass is clicked (keyboard accessibility fix)
- OTD (On This Day): cache flusher added to reduce latency
- CLI: new command to flush any page cache on demand
- Added `CODE_OF_CONDUCT.md` and `SECURITY.md`

### Fixed

- Nginx Helper cache purge order corrected — connected pages (e.g. actor ↔ character) now cross-flush properly; WP Rocket purge moved to after Nginx
- SVGs now have enforced max-size to prevent occasional large-image rendering bug
- Graceful failure when no actor is listed on a character
- Post type check for overlay display corrected
- CLI output improved for cron jobs
- Calendar cache now flushed when TVMaze data is downloaded daily
- Redundant queries and stats calculations cleaned up

### Improved

- Calendar renamed internally from “Blocks” to “Display” for clarity
- CSS broken apart into partials to ease future migration off `@import`
- Build time improved by excluding unused files
- Non-minified CSS build restored (partial)
- Main icon preloading removed from pages that don’t use it
- Editor title labels improved for mobile data validation
- FontAwesome updated (minor release)
- Console.log removed from search form fix
- Dependabot: webpack 5.96.1, @types/node 22.8.7, and several other NPM packages

-----

## [6.2.0] — 2024-10-29

### Added

- Plugin code merged into theme repository — lwtv-plugin is now a one-stop-shop alongside the theme
- Parallel build system: wp-scripts and Grunt now run together to build JS and CSS
- On-air detector made smarter with improved logic
- FontAwesome updated to 6.6.0
- PHPCS linting added and applied across codebase
- Gutenberg category fix for WordPress 6.6
- FacetWP file moved to group folder for better organization
- Self-hosted copies of third-party plugins added to `/plugins/`

### Fixed

- Auto-population of primary genre on show save
- On-air detection edge cases
- CMB2 Grid pathing corrected after plugin move
- Build scripts updated to use `postbuild` instead of `postinstall`

### Improved

- Functions moved to components architecture
- JS moved to consolidated location
- Linting commands updated
- Chart.JS updated to 4.4.6
- minimist updated to 1.2.8 (security)
- CMB2 Grid updated; CSS updated accordingly

-----

## [6.1.1] — 2024-06-24

### Added

- Dark mode support (full implementation with Bootstrap dark mode, icons, and FacetWP)
- CSS for Bootstrap dark mode base
- JS flicker prevention during dark mode toggle
- Death SVG icon added
- CSS `@import` → `@use` migration begun

### Fixed

- Responsive dark mode compatibility
- Background colors for dark mode display
- Multiple layout fixes following PageSpeed work

### Improved

- Sidebars and partials broken out into their own directories
- Sidebars updated to call card templates (easier to maintain)
- Character cliché card added as layout experiment
- Icon size increased for accessibility
- CSS version bumped to force cache refresh

-----

## [6.1.0] — 2024-06-24

### Added

- PageSpeed improvements: LCP (Largest Contentful Paint) optimized across CPTs, search, and 404 page
- First image added to LCP preload on search
- Aria labels added for intersection taxonomy

### Fixed

- A11Y: header order corrected
- A11Y: Green Thumb contrast ratio fixed
- A11Y: author box accessibility fixes
- A11Y: show page accessibility fixes
- Social name display fix
- `show-appears` sticky positioning fixed
- Fallbacks added for missing actor/show data

### Improved

- Styles cleaned up and reorganized
- Intersection taxonomy treated as its own entity (no longer grouped under tropes)
- Mobile age display fixed
- Package dependencies updated

-----

## [6.0.5] — 2024-06-19

### Added

- Support for updated “Ways to Watch” taxonomy
- Overlays for stats sections and related posts/articles
- Overlay for suggestions adjusted

### Fixed

- Post missing from TikTok feed
- Google SEO: prohibited ARIA attribute on elements
- LCP tweaks for Google scoring

### Improved

- CSS improvements for overlays and layout
- Style polish pass

-----

## [6.0.4] — 2024-04-04

### Added

- Bluesky support in actor social links and navigation menu
- Font Awesome updated to 6.5.2

### Fixed

- Character photo linking restored
- Character photo width corrected
- Actor social link URLs (props Carmel)
- IMDB URL handling in actor socials
- Alt text on character images (props Nikki)
- CSS spacing

### Improved

- Card image cleanup
- Minor fixes pass

-----

## [6.0.3] — 2024-04-02

### Fixed

- Related articles display
- Character template rendering corrected
- Data loading reduced by passing data to templates instead of re-fetching

-----

## [6.0.2] — 2024-03-18

### Added

- Bootstrap 5 upgrade (replaces Bootstrap 4)
- Shadow taxonomy support — characters, shows, and actors now use shadow terms for performance
- LCP applied to custom post type pages
- Actors and Shows display as accordions when 2+ entries exist (e.g. Sara Lance)
- Templates broken apart into reusable partials (DRY)
- Responsive fixes for mobile navigation (Bootstrap 5 navmenu)

### Fixed

- JavaScript errors from Bootstrap 5 migration
- Navmenu on mobile
- Responsive layout on phone
- Related posts header
- Years display on actor pages
- Character priority in display logic

### Improved

- Load times improved via LCP and shadow taxonomy queries
- CSS minified for Bootstrap
- Code that was duplicated across actors and shows shared into common templates
- Actor and show page speed improvements

-----

## Priot to 2024-03-18

*Versions prior to 6.0.2 are not covered in this changelog. See git history for full detail.*