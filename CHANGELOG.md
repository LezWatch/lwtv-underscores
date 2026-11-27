# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [8.0.0] - 2026-11-27

### Added

- Meilisearch search, built alongside SearchWP and switched on with `LWTV_MEILI_MODAL` (or the Search Settings page): `'editors'` for logged-in editors only, `'everyone'` for the whole site. SearchWP stays the default until the switch covers everyone.
- Four Meilisearch indexes, for characters, shows, actors, and posts and pages, kept in sync as content is published, edited, trashed or recalculated. `wp lwtv meili` sets them up, reindexes and reports their status.
- A header search modal with Shows, Characters, Actors and Posts tabs, result counts, alternate show names ("Also known as"), keyboard support, screen reader announcements and dark mode. It falls back to SearchWP, or to WordPress search, if Meilisearch can't be reached.
- The search results page (`/?s=`) served from Meilisearch for the people the switch covers, falling back to SearchWP when Meilisearch fails. The sidebar search keeps its scope tabs.
- Meilisearch engines for the FacetWP keyword facets ("Meilisearch – Shows" and so on), ready to pick in FacetWP → Facets.
- A **Search** admin menu:
  - **Settings**, with a Status tab (server connection, each index's document count, whether settings and synonyms match, reindex jobs), a General tab (the switch, the fallback, the search timeout, and Connection Settings for the Meilisearch URL and index prefix), and a Typo tolerance tab for each index.
  - **Rules**, for search synonyms.
  - **Compare**, for checking Meilisearch results against SearchWP side by side.
- An Other Names field on actors. Other names are searchable but never displayed. `wp lwtv migrate othernames` moves them out of the old excerpt field.

### Changed

- Same-name shows rank by Worth It, then score, so plain "skins" finds the UK original and "charmed" the 2018 one. Same-name actors rank queer actors first, then those with more characters.
- The Meilisearch URL and index prefix can be set on the Search Settings page instead of in `wp-config.php`. They default to `search.` plus the site's domain and to the environment type. A define still wins and greys out its field. The API keys stay in `wp-config.php`.
- Actors no longer have an excerpt ("Additional Notes") field; Other Names replaces it.

## Pervious Versions

- See [v7 Changelog](docs/changelogs/v7.md)
- See [v6 Changelog](docs/changelogs/v6.md)

*Versions prior to 6.0.2 are not covered in this changelog. See git history for full detail.*
