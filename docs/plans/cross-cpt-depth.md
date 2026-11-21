# Handoff: Cross-CPT Depth (what's left)

**Repo:** `LezWatch.TV` (plugin under `plugins/lwtv-plugin/`), theme+plugin.
**Scope:** Show stats that depend on the **shows ↔ characters ↔ actors** relationships, not just one CPT at a time.

## Already shipped (don't redo)

- **Most-prolific actor per group**: `Build\Actors::generate_prolific_by_orientation()`, `generate_prolific_by_gender()` and `generate_prolific_by_role()`, all built on `get_actor_character_counts()`. Rendered on the actors sub-views (e.g. `templates/actors/gender.php`).
- **Deaths by sexuality / gender / role**: `generate_dead_statistics( 'characters', 'sexuality' | 'gender' | 'role', 'array' )` via `Build\Dead::generate_characters_taxonomy()`. Rendered in `templates/death/characters.php`.
- **Actor CSV roster** with characters played (`CSV_Download::actor_roster_rows()`, reading `lezactors_char_count`).

## What's left

### Phase 1: overall "Actors by roles" leaderboard  ⭐ do first

- **New view:** an "Actors by roles" leaderboard in the actors sub-nav (`templates/actors/subnav.php`), ranking all actors by number of queer roles and linking to `/actor/{slug}/`. Reuse `partials/leaderboard.php` or `partials/ranked-bars.php`.
- **Callout:** "The most prolific queer performer is X with N roles", via `partials/phrases.php`.
- **Data source decision:** the prolific helpers count live (`get_actor_character_counts()`), while the CSV roster reads the `lezactors_char_count` meta. Choose one for the leaderboard and confirm both give the same top actor, so the page and the CSV don't disagree.
- **Privacy:** respect `hide_actor_data()` the same way the existing prolific callouts and the birthday round-up do.
- **Follow-up (optional):** "currently on-air actors" needs an actor → character → on-air show join. Check whether it can use the `On_Air_Optimized` builder; if the join is heavy, ship it separately.

### Phase 2: remaining intersectional slices

- **Characters by nation × gender**: a two-axis breakdown.
- **Tropes on shows that kill**: of shows with a death, which tropes recur. This also covers "per-trope death likelihood" from `rate-based-stats.md`.

- **Mechanism:** add methods to `Build\Dead` (death axis) or `Build\Taxonomy_Optimized` (tax × tax). Follow the cached-transient + `$wpdb->prepare` grouped-query pattern from `get_terms_per_object_stats()`. Join `wp_posts` and filter on `post_type`/`post_status`, because ACF meta is copied onto revisions.
- **Render:** grouped or stacked bars. Prefer adding a second series to `ranked-bars.php` over creating a new `partials/grouped-bars.php`.

**⚠️ Verify first:**
1. **Query performance**: cross-tax joins over every character can be slow. Cache for a DAY, and consider building them during the existing stats-generation cron instead of on page load.
2. **Unknown/none buckets**: decide for each axis whether those terms count, and document the choice (mirror the "None!" trope exclusion).

## Testing checklist

- [ ] Leaderboard top actor matches a manual query; links resolve; hidden actors stay hidden.
- [ ] Phase 2 totals match the single-axis totals (minus any documented excluded bucket).
- [ ] Cross-tax queries are cached; a second page load hits the transient.
- [ ] Any new CSV export has the BOM + injection hardening.
- [ ] `composer lint` and `composer analyse` clean.

## Global constraints

- Meta-key prefixes `lezactors_*` / `lezchars_*` / `lezshows_*`; taxonomy slugs `lez_*`.
- Read-only with respect to show scoring (`class-calculations.php`).
- All strings i18n-ready with the `'lwtv-underscores'` text domain.

## Out of scope

- Show scoring changes, per-episode/screentime data, a recommendation engine.
