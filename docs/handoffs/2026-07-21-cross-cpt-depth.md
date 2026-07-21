# Handoff: Cross-CPT Depth (actor leaderboards + intersectional slices)

**Repo:** `LezWatch.TV` (plugin under `plugins/lwtv-plugin/`), theme+plugin on `feat/cliche-stats`.
**Scope:** Stats currently slice one CPT at a time. The site's actual value is the **shows ↔ characters ↔ actors** web — surface it. Two phases: a cheap actor-leaderboard using existing meta, then heavier intersectional queries.

## Goal
Answer questions that need the relationships, not just one table: "which actors have played the most queer roles?", "how do deaths break down by sexuality?", "do shows that kill also carry certain tropes?"

---

## Phase 1 — Actor leaderboards (cheap; uses existing meta)  ⭐ do first
- **Data already exists:** actors carry `lezactors_char_count` (roles played) and `lezactors_dead_count`; taxonomies `lez_actor_gender` / `lez_actor_sexuality`. The redesign's actor CSV roster already reads these.
- **New view:** add an "Actors by roles" leaderboard to the actors sub-nav, reusing `partials/ranked-bars.php` or `partials/leaderboard.php` (rank actors by `lezactors_char_count`, link to `/actor/{slug}/`).
- **Callout:** "The most prolific queer performer is X with N roles" via `phrases.php`.
- **CSV:** extend the actor roster (already whitelisted) or add a leaderboard export.

**⚠️ Verify first:** whether a "currently on-air actors" list needs a live actor→character→show-on-air join (more expensive) or can piggyback on the `On_Air_Optimized` builder. If the join is heavy, ship the roles leaderboard (pure meta sort) first and treat on-air-actors as a follow-up.

---

## Phase 2 — Intersectional slices (heavier; new queries)
Cross-taxonomy / cross-CPT breakdowns:
- **Deaths by sexuality / by gender** — group dead characters by their `lez_sexuality` / `lez_gender` terms.
- **Characters by nation × gender** — two-axis breakdown.
- **Tropes on shows that kill** — of shows with a death, which tropes recur (ties into the rate-based-stats handoff's "per-trope death likelihood").

- **Mechanism:** add methods to the relevant builder (`Build\Dead` for death-axis; `Build\Taxonomy_Optimized` for tax×tax). Follow the cached-transient + `$wpdb->prepare` grouped-query pattern established by `get_terms_per_object_stats()` (INNER JOIN term_relationships/term_taxonomy/posts, GROUP BY, cache in a transient).
- **Render:** stacked or grouped bars — may need a small extension to `ranked-bars.php` (a second series) or a new `partials/grouped-bars.php`. Decide during design; prefer extending the existing partial.

**⚠️ Verify first (critical for Phase 2):**
1. **The actor↔character↔show link** — confirm the mechanism (ACF relationship field like `lezchars_actor` + the shadow taxonomy). Get this exactly right; the CLAUDE.md flags CPT relationships as load-bearing.
2. **Query performance** — cross-tax joins over the full character set can be slow. Cache per the DAY-transient pattern; consider building during the existing stats-generation cron rather than on page load.
3. **Term canonicalisation** — sexuality/gender terms may have "unknown"/"none" buckets; decide inclusion/exclusion per axis (mirror the "None!" trope exclusion already done for tropes/cliches).

## Files (indicative)
- Phase 1: new actors view template + sub-nav entry; reuse ranked-bars/leaderboard; CSV whitelist entry.
- Phase 2: new builder methods (`Build\Dead`, `Build\Taxonomy_Optimized`); possibly `partials/grouped-bars.php`; new view templates + CSV entries.

## Global constraints
- Respect meta-key prefixes: `lezactors_*` / `lezchars_*` / `lezshows_*`; taxonomy slugs `lez_*`.
- Don't alter the show-score calculation (`class-calculations.php`) — read-only here.
- All strings i18n-ready (`'lwtv'`); custom auto-escaped funcs not re-wrapped.
- No new remote `file_get_contents`.

## Testing checklist
- [ ] Phase 1 leaderboard ranks by `lezactors_char_count`; top actor matches a manual query; links resolve to `/actor/{slug}/`.
- [ ] Phase 2 breakdown totals reconcile with the single-axis totals (e.g. deaths-by-sexuality sums to total deaths, minus any excluded bucket — document the exclusion).
- [ ] Cross-tax queries cached; second page load hits the transient (no repeated heavy query).
- [ ] CSV exports present with BOM + injection hardening.
- [ ] `composer lint` clean.

## Out of scope
- Any change to show scoring.
- Per-episode / screentime-level data (not modelled).
- A recommendation engine ("shows like this") — separate, larger project.
