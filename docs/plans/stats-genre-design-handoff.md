# Genre Stats — Design Handoff

**Page:** Statistics → Shows → Genres (`plugins/lwtv-plugin/php/statistics/templates/shows/genres.php`)
**Goal:** Bring Genres up to the same infographic bar as the recent Tropes rework, plus two genre-specific additions.
**Taxonomy:** `lez_genres` — multi-value (a show can carry several genres), archive base `/genre/`. Same shape as `lez_tropes`.

Current state: a ranked-bar list ("Genre Breakdown") with an average/median-per-show callout pair above it. That callout pair is getting replaced by item 1 below — same move already made on Tropes.

---

## 1. Genre Load (waffle) — replaces the avg/median callouts

What it shows: what share of shows carry 0, 1, 2, 3, or 4+ genres, as a 100-dot waffle colored by bucket, with a small legend and a "most genre-loaded show" poster spotlight underneath.

This is a straight port. Powered by `Term_Count_Distribution::build()` / `::to_cells()` / `::top_object()`, pointed at `lez_genres` instead of `lez_tropes`. Mirror the Trope Load panel layout in `tropes.php` (lines ~79–196): waffle + legend side by side, poster spotlight as a footer strip below, not squeezed alongside.

One wrinkle worth designing for: genre counts per show likely run higher than trope counts (most shows carry 2–3 genres, some carry a handful more), so the "4+" overflow bucket may hold a much bigger share of shows here than it does for tropes. Don't assume the bucket sizes will look as evenly spread as the trope version — the waffle should read fine either way, but a design that assumes a gentle taper across all 5 buckets may not match reality once real numbers are in.

## 2. Common Genre Pairings (ranked list)

What it shows: which two genres most often appear on the same show (e.g. "Drama + Comedy"), as a ranked/lollipop list — same visual as Tropes' "Common Pairings" panel.

Straight port of `Intersection_Pairs::count_pairs()` + `::top_pairs()`. You flagged this could land harder than the trope version, and that tracks — genre combos are a "what's the vibe" story in a way trope combos aren't, so this is a good candidate for more visual weight than it got on the Tropes page (bigger panel, maybe promoted out of the side column into a primary spot).

## 3. Broadest-reach show (poster spotlight)

What it shows: the single show spanning the most genres, with poster art — same treatment as the "most trope-loaded show" spotlight.

Straight port of `Term_Count_Distribution::top_object()`. One thing to design for: ties. The method already reports how many shows tied for the top spot (`tied`), and the copy hedges when `tied > 1` ("X is tied with N other shows..."). Genres may tie more often than tropes did, so this needs a layout that doesn't look broken or half-finished when the caption is the longer "tied with N others" version instead of the clean single-winner version.

---

## 4. Underrepresented Genres (new pure logic, no new query)

What it is: the long-tail end of Genre Breakdown, reframed around representation instead of "here are the smallest bars." Same underlying counts as the main ranked list — this is a display/framing layer on top, not new data.

**A framing note worth building the design around:** the database only contains queer shows, so "this genre has very few entries" means "queer TV has barely touched this genre" — it is not a claim about how queer representation compares to straight representation *within* that genre (we don't have the denominator for that). The design and copy need to keep that distinction clear: "these genres are barely explored in queer television" is accurate; "queer people are underrepresented in Western TV" is a claim the data can't back up. Worth a visual treatment that reads as "unexplored territory" rather than "failing grade" — e.g. an empty-map or blank-space metaphor rather than red/warning styling.

This also falls under a standing project convention: stat copy has to be computed from live data (counts, percentages), never a hardcoded claim like "genre X barely exists." Whatever sentence template you design copy around, assume it's rebuilt from the numbers every time the page loads, so avoid layouts that only work for one specific phrasing length.

## 5. Genre by Format or by Decade (bigger lift — new query/class needed)

The idea: has Sci-Fi/Fantasy grown as a share of queer shows over time? Genuinely a good angle, but it's not a clean port — flagging why before you design around it.

The precedent is "Format Mix by Decade" on the Formats page (`formats.php`, `Format_Trend` → `Format_Decade_Buckets`): small compact donuts, one per decade, oldest to newest, each summing to 100%. That works for Formats because `lez_formats` is **single-value** — every show has exactly one format, so decade shares are mutually exclusive and a donut per decade makes sense.

**Genres is multi-value.** A show with three genres contributes to three genres' counts in the same decade. Decade shares will not sum to 100%, so a stacked donut per decade would either mislead (implying exclusivity that isn't there) or need an explicit ">100%, shows can carry multiple genres" disclaimer competing for attention on every tile. Worth designing this as small-multiple line or bar charts (one line per genre across decades, or grouped bars per decade) rather than reaching for the donut-per-decade pattern by default — that pattern doesn't transfer cleanly here.

Needs new plumbing: a genre-equivalent of `Format_Trend`'s year-tally query (this one will tally against `lez_genres`'s multi-value relationships rather than a single term per show) feeding a decade-bucketing pass. No design blocker here, just don't assume the engineering is a copy-paste of `Format_Decade_Buckets` — the bucketing logic is reusable, the tally query underneath it is not.

---

## Visual vocabulary already available

These exist as reusable partials/components — reach for them before inventing new chart types:

- **Waffle** (`partials/waffle.php`) — binary "N filled of 100" or segmented multi-bucket, dots sized/spaced by radius.
- **Donut** (`partials/donut.php`) — full-size with headline + waffle echo, or `compact` layout for small-multiple tiles (used in the decade row).
- **Ranked bars / lollipop** (`partials/ranked-bars.php`) — the main breakdown lists; `mode: 'lollipop'` is the more minimal variant used for pairings.
- **Callouts** (`partials/callouts.php`) — the small stat-sentence cards above a chart.
- **Pullstats cards** — the 4-up icon+number+description cards (Trope Alignment uses this pattern).

Genres' established color family is amber (per the existing template comment); keep new panels in that ramp so the page reads as one coherent set rather than a patchwork of borrowed trope-green pieces.

## Standing constraints

- Stat copy must be generated from live values (sprintf + computed numbers), never a static sentence that data drift could falsify — see items 4 and 5 above especially.
- This is read-only aggregation over existing Show↔Genre taxonomy relationships — no changes to CPT linkages, ACF fields, or the score calculation.
- All UI strings need to stay i18n-ready (`__()` / `_e()` with the `'lwtv'` text domain) — not a design constraint per se, but worth knowing copy will be wrapped that way downstream.
