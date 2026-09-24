# Statistics data model

How the statistics code reads show, character and actor data, and the storage quirks every query in `plugins/lwtv-plugin/php/statistics/` has to respect.

## Published-only scoping

Every statistics query joins `wp_posts` and restricts to `post_status = 'publish'` for the CPT it counts. Don't scan `wp_postmeta` on its own. ACF copies meta, including every repeater sub-field row, onto revision posts. A bare meta scan counts a revised character twice or more, and revision IDs show up as nameless "phantom" cast members.

Examples: `Character_Death_Leaders::build_leaders_data()`, `Actors::generate_roles_totals()`, `This_Year\Build\Shows_Builder` (character lookup by show).

## ACF storage

The character field group is `plugins/lwtv-plugin/acf-json/group_lwtv_chars_details.json`.

### Repeaters

ACF repeaters don't store a serialized blob under the parent key. Each sub-field of each row gets its own postmeta row named `{repeater}_{n}_{sub_field}`, and the parent key holds only the row count.

| Repeater | Sub-fields | Meta keys |
|---|---|---|
| `lezchars_show_group` | `show` (post object, ID), `type` (select: `regular` / `recurring` / `guest`), `appears` (multi-select of years) | `lezchars_show_group_{n}_show`, `_type`, `_appears` |
| `lezchars_death_year` | `date` (date picker) | `lezchars_death_year_{n}_date` |

So queries match sub-field keys with `LIKE`, sometimes with a `REGEXP` as well (see [docs/sql/optimization.md](../sql/optimization.md#repeater-sub-field-key-matching)). Two rules apply:

- Build the pattern with `$wpdb->esc_like()`. An unescaped `_` is a single-character wildcard, and these keys are full of literal underscores. Pass it through a `prepare()` placeholder, as `Taxonomy_Optimized::get_bulk_character_counts()` and `Actors::generate_roles_totals()` do.
- A character can have two `show_group` rows for the same show (for example guest in one stint, regular in another). "How many shows" must count `DISTINCT` show IDs, not rows (`Character_Show_Leaders`).

The `appears` sub-field is a multi-select, so its single meta value is a serialized array of year strings. `maybe_unserialize()` it in PHP. A lone year can come back as a plain string instead of a one-item array, so wrap it (`Actors::generate_active_this_year()`, `On_Air_Optimized`).

### Relationship fields

`lezchars_actor` is an ACF relationship field. It's stored as one serialized array of actor IDs per character, with no per-actor meta row. SQL can't `COUNT` or join through it. The pattern is to pull one `(post_id, meta_value)` row per published character in a single query, then unserialize and count in PHP. Filter the array to positive integer IDs (`array_filter( array_map( 'absint', … ) )`) so stray zeros and non-numeric values drop out. `Character_Actor_Leaders`, `Actors`, `Character_Queer_Cast_Firsts` and `Unknown_Actor` all use this shape. Any ordering on that count has to happen in PHP after the query.

## Taxonomy cardinality

Whether a taxonomy is single- or multi-value decides whether its shares partition to 100%. The ACF `field_type` sets this (from the `acf-json/` field groups):

| CPT | Single-value (`select`) | Multi-value (`multi_select` / `checkbox`) |
|---|---|---|
| Shows | `lez_formats`, `lez_stars`, `lez_triggers` | `lez_genres`, `lez_intersections`, `lez_tropes`, `lez_stations`, `lez_country` |
| Characters | `lez_gender`, `lez_sexuality`, `lez_romantic` | `lez_cliches` |
| Actors | `lez_actor_gender`, `lez_actor_sexuality`, `lez_actor_romantic` | `lez_actor_pronouns` |

- **Single-value:** each object contributes to exactly one term, so a donut or 100%-stacked chart is valid. `Format_Decade_Buckets` and `Character_Identity_Decade_Buckets` assume this.
- **Multi-value:** a show can carry several terms, so per-term shares ("% of shows carrying X") don't sum to 100. `Genre_Decade_Buckets` counts each bucket's distinct objects separately from its tag counts for this reason, and templates show top-N bars, not a donut. Despite its name, `Genre_Decade_Buckets` works for any taxonomy: `Intersection_Trend` feeds it `lez_intersections`.
- A show tagged with several terms on a multi-value taxonomy (a co-production on two networks, say) contributes its full counts to each term. `Dead::generate_shows_by_taxonomy()` and `Taxonomy_Death_Leaders` both work this way.

## Anchoring characters to a year

Characters have no premiere-year field. Any per-year or per-decade character statistic uses the character's **earliest `appears` year** across all their `lezchars_show_group` rows (`Character_Identity_Trend`, `Character_Queer_Cast_Firsts`, `Character_Longevity_Leaders`). Shows use `lezshows_airdates` start (see `Format_Trend`, `Genre_Trend`, `Intersection_Trend`), with the legacy serialized `lezshows_airdates['start']` as a fallback (`Taxonomy_Optimized::get_bulk_first_years()`).

"Longest-running" counts **distinct credited years**, not the span from first to last year. A character written out and back in doesn't get credit for the years off screen. `Character_Longevity_Leaders` still returns min and max for display.

## Death rows

`lezchars_death_year` is a repeater because characters can die more than once (soap-style fake deaths and resurrections).

- **Per-character boards** (`Character_Death_Leaders`, "most resurrected") count rows per character and require 2 or more. One death is not a resurrection, so the board can return fewer rows than its limit, or none.
- **Per-event trends** (`Death_Trend`) bucket each dated death by the decade the death happened in. A character who died in the 1990s and again in the 2000s counts once in each decade. This is on purpose. It's the opposite of `Character_Identity_Trend`, which folds everything to one row per character.
- Raw date-picker postmeta may be `Ymd` or legacy `Y-m-d`. Normalise to `Y-m-d` before using a date as a sort key (`Dead`, the date-grouped list), or a string sort interleaves the two formats.

## Counts vs curator tags

The `dead-queers` trope is a curator's manual tag, not a live count, so a show can have recorded deaths without it. "Most lethal" rankings (`Show_Death_Leaders`, `Taxonomy_Death_Leaders`) read the computed `lezshows_char_count` and `lezshows_dead_count` postmeta instead. Death → Nations and Death → Stations raw lists still rank by tagged-show count. `Taxonomy_Death_Leaders` is the rate-based view that corrects for "more shows means more deaths".

## Recasts

`lezchars_actor` is an ordered list. The field instructions say to enter "most recent actor first", and there's no year range per actor. No data says which actor was on screen in a given year, or which actor played which `show_group` row.

The rule the statistics code uses: **the first-listed actor stands in for the character.**

- `Actors::generate_active_this_year()` credits only the first-listed actor of each character on screen this year.
- `Actors::get_first_actor_by_character()` (feeding `generate_prolific_by_role()`) credits every role type a recast character has to whoever is listed first today.

This is only as accurate as editors' ordering on recast. For the same reason, the Actors → Sexuality and Actors → Gender pages have no decade trend or Firsts list: there's no per-year path from an actor to their roles.

## The Unknown actor

Post `14080` (`Unknown_Actor::ACTOR_ID`) is the placeholder actor for characters with no confirmed performer. Every actor-facing count and leaderboard skips it: `Actors::get_actor_character_counts()`, `Actors::get_first_actor_by_character()`, `Character_Queer_Cast_Firsts`, and `This_Year\Build\Characters_Builder`'s "busiest actor". `Unknown_Actor` and the Actors → Unknown Actor page are the one place that query for it on purpose.

## None terms

`lez_tropes` and `lez_cliches` each have a real `none` term that editors pick to mean "no tropes" or "no clichés". Both fields are required, so every published show and character carries at least one term.

- Load distributions, pairings and averages exclude `none` (`Term_Count_Distribution::build( …, array( 'none' ) )`, `Taxonomy_Optimized::get_terms_per_object_stats( …, array( 'none' ) )`). The "0" bucket then means "tagged None", a real reading, not missing data. That's why Trope Load and Cliché Load colour bucket 0 on the ramp, while Genre Load and Intersection Load (no `none` term) use grey for zero. See [docs/design/colors.md](../design/colors.md#load-waffle-ramps).
- Templates label bucket 0 with the `none` term's real name (`get_term_by( 'slug', 'none', … )`), falling back to the slug.
- `none` never shows up as a pairing partner, because it's exclusive of real terms, so pairing code needn't filter it.

## Stored queer flag

`lezactors_queer` is the stored result of `Queeries\Is_Actor_Queer::make()`. `Actors\Calculations::do_the_math()` writes it on actor save and on `wp lwtv calc actors`. Statistics read the stored flag (`Actors::count_queer_among_terms()`) rather than recomputing per actor, so the figures agree with the actors admin column, the ACF relationship labels and the REST endpoints.

- **Staleness:** an actor is only as current as their last save or recalculation.
- A missing row counts as not queer, matching PHP truthiness checks. SQL excludes `''` and `'0'` rather than matching `'1'`.
- The `uncalculated` count reports tagged actors with no row at all. Non-zero means a recalc is overdue and the figure is understated.
- Buckets made of several terms (Cisgender = `cis-woman` + `cis-man` + `cisgender`) must count `DISTINCT` actor IDs.

## Role type

Role type (`regular` / `recurring` / `guest`) lives on the character's `lezchars_show_group_{n}_type`, one per show appearance, not on the actor. The sitewide Roles figures (`Actors::generate_roles_totals()`, `Dead::generate_characters_by_roles()`) tally every published character's every tagged appearance. Each row has exactly one type, so the three buckets sum to the total (`Role_Podium` needs no separate denominator).
